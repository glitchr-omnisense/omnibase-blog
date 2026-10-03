<?php

namespace Base\Blog\WordPress;

use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A WordPress site moved in: its posts and pages read from its REST API,
 * each sent to a target (a blog post by default, anything a site declares
 * through the map), their HTML cleaned of what plugins add, their pictures
 * copied into the uploads, their links rewritten - to the pictures copied,
 * to the posts and pages imported (by permalink, ?p=, ?page_id=). Run it
 * again with --update: what was imported is found again and brought up to
 * date, never doubled.
 */
class Importer
{
    /** @var array<string, TargetInterface> */
    private array $targets = [];

    /** @param iterable<TargetInterface> $targets */
    public function __construct(
        private readonly Client $client,
        private readonly Cleaner $cleaner,
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $http,
        #[AutowireIterator('blog.wordpress_target')] iterable $targets = [],
        #[Autowire(service: 'local.uploads')] private readonly ?FilesystemOperator $uploads = null,
    ) {
        foreach ($targets as $target) {
            $this->targets[$target->getName()] = $target;
        }
    }

    /** @return array<string, TargetInterface> */
    public function getTargets(): array
    {
        return $this->targets;
    }

    /**
     * Every post and page of the site, cleaned (links not rewritten yet).
     *
     * @return list<Item>
     */
    public function read(string $site): array
    {
        $names = [];
        foreach (['categories', 'tags'] as $taxonomy) {
            foreach ($this->client->all($site, $taxonomy) as $term) {
                $names[$taxonomy][(int) $term['id']] = html_entity_decode((string) $term['name'], \ENT_QUOTES | \ENT_HTML5);
            }
        }
        $raw = [];
        foreach (['posts' => Item::POST, 'pages' => Item::PAGE] as $endpoint => $type) {
            foreach ($this->client->all($site, $endpoint) as $entry) {
                $raw[(int) $entry['id']] = ['type' => $type] + $entry;
            }
        }

        $items = [];
        foreach ($raw as $id => $entry) {
            $ancestors = [];
            for ($parent = (int) ($entry['parent'] ?? 0), $guard = 0; $parent && isset($raw[$parent]) && $guard < 10; $parent = (int) ($raw[$parent]['parent'] ?? 0), ++$guard) {
                array_unshift($ancestors, (string) $raw[$parent]['slug']);
            }
            // The excerpt from the cleaned text: WordPress' own carries what plugins print (a share
            // button's script as words), and its "[…] Continue reading".
            $content = $this->cleaner->clean((string) ($entry['content']['rendered'] ?? ''));
            $excerpt = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) preg_replace('#<(br|/p|/li|/h\d)\b[^>]*>#i', ' $0', $content)), \ENT_QUOTES | \ENT_HTML5)));
            $items[] = new Item(
                id: $id,
                type: $entry['type'],
                slug: (string) $entry['slug'],
                title: trim(html_entity_decode(strip_tags((string) ($entry['title']['rendered'] ?? '')), \ENT_QUOTES | \ENT_HTML5)),
                content: $content,
                excerpt: '' !== $excerpt ? mb_strimwidth($excerpt, 0, 280, '…') : null,
                date: new \DateTimeImmutable((string) ($entry['date'] ?? 'now')),
                modified: isset($entry['modified']) ? new \DateTimeImmutable((string) $entry['modified']) : null,
                status: (string) ($entry['status'] ?? 'publish'),
                link: (string) ($entry['link'] ?? ''),
                parent: (int) ($entry['parent'] ?? 0),
                ancestors: $ancestors,
                menuOrder: (int) ($entry['menu_order'] ?? 0),
                categories: array_values(array_filter(array_map(fn ($c) => $names['categories'][(int) $c] ?? null, $entry['categories'] ?? []))),
                tags: array_values(array_filter(array_map(fn ($t) => $names['tags'][(int) $t] ?? null, $entry['tags'] ?? []))),
            );
        }
        usort($items, static fn (Item $a, Item $b) => [$a->type, \count($a->ancestors), $a->menuOrder, $a->id] <=> [$b->type, \count($b->ancestors), $b->menuOrder, $b->id]);

        return $items;
    }

    /**
     * @param array<string, string> $map    an item's slug or path ("about-me/biography"), or "*post" / "*page" => "target" or "target:option"
     * @param string                $folder the pictures' folder in the uploads
     */
    public function import(string $site, array $map = [], bool $update = false, ?object $author = null, string $folder = 'wordpress', bool $media = true): Result
    {
        $result = new Result();
        $items = $this->read($site);

        // Each item's target, and what the map says after its name.
        $routes = [];
        $options = [];
        foreach ($items as $item) {
            $spec = $map[$item->path()] ?? $map[$item->slug] ?? $map['*'.$item->type] ?? (Item::POST === $item->type ? 'post' : (isset($this->targets['page']) ? 'page' : 'skip'));
            [$name, $option] = array_pad(explode(':', $spec, 2), 2, null);
            if (!isset($this->targets[$name])) {
                throw new \InvalidArgumentException(sprintf('No import target "%s" (for "%s"); there are: %s.', $name, $item->path(), implode(', ', array_keys($this->targets))));
            }
            $routes[$item->id] = $this->targets[$name];
            if (null !== $option) {
                $options[$item->slug] = $option;
            }
        }

        // Where everything will be, before anything is written: the links follow.
        $byId = [];
        $byPath = [];
        foreach ($items as $item) {
            if ($url = $routes[$item->id]->url($item)) {
                $byId[$item->id] = $url;
                $byPath[$item->path()] = $url;
                if ($link = parse_url($item->link, \PHP_URL_PATH)) {
                    $byPath[trim($link, '/')] = $url;
                }
            }
        }
        $pictures = $media ? $this->copyMedia($site, $folder, $result) : [];
        $links = new LinkRewriter($site, $pictures, $byId, $byPath);

        $context = new Context($site, $update, $author, $options, $result);
        foreach ($items as $item) {
            $item->content = $links->rewrite($item->content);
            $result->add($routes[$item->id]->getName(), $routes[$item->id]->import($item, $context));
        }
        $this->entityManager->flush();

        return $result;
    }

    /**
     * The site's media copied into the uploads (uploads/<folder>/2014/05/book-a.jpg):
     * what is already there is left.
     *
     * @return array<string, string> an upload's key (LinkRewriter::mediaKey()) => its new address
     */
    private function copyMedia(string $site, string $folder, Result $result): array
    {
        if (!$this->uploads) {
            return [];
        }
        $folder = trim($folder, '/');
        $map = [];
        foreach ($this->client->all($site, 'media') as $medium) {
            $source = (string) ($medium['source_url'] ?? '');
            if ('' === $source || !str_contains($source, '/wp-content/uploads/')) {
                continue;
            }
            $key = LinkRewriter::mediaKey($source);
            $target = $folder.'/'.$key;
            try {
                if (!$this->uploads->fileExists($target)) {
                    $response = $this->http->request('GET', $source);
                    $this->uploads->write($target, $response->getContent());
                    ++$result->media;
                }
                $map[$key] = '/uploads/'.$target;
            } catch (\Throwable $e) {
                $result->warnings[] = sprintf('%s: %s', $source, $e->getMessage());
            }
        }

        return $map;
    }
}
