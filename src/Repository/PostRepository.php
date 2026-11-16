<?php

namespace Base\Blog\Repository;

use Base\Blog\Entity\Post;
use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Group;
use Base\Database\Repository\Finder\Parser;
use Base\Database\Repository\Repositories;
use Base\Entity\Thread\Tag;
use Base\Enum\ThreadState;
use Base\Database\Repository\Finders;
use Base\Database\Repository\ServiceEntityRepository;

/**
 * The chronicles, on omnibase's finder language: the same queries whatever persistence runs them.
 *
 * @method Post|null find($id, $lockMode = null, $lockVersion = null)
 * @method Post|null findOneBy(array $criteria, ?array $orderBy = null)
 * @method Post[]    findAll()
 * @method Post[]    findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null)
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(Finders $finders, private readonly Repositories $repositories)
    {
        parent::__construct($finders, Post::class);
    }

    /**
     * A page of the published posts, newest first - of one topic when a tag
     * slug is given, of one taxon (a level, a subject) when a taxon slug is.
     *
     * @return list<Post>
     */
    public function findPublishedPage(int $page = 1, int $perPage = 10, ?string $tag = null, ?string $taxon = null): array
    {
        return $this->findBy($this->filtered($this->published(), $tag, $taxon), ['publishedAt' => 'DESC', 'id' => 'DESC'], $perPage, max(0, $page - 1) * $perPage);
    }

    public function countPublished(?string $tag = null, ?string $taxon = null): int
    {
        return $this->count($this->filtered($this->published(), $tag, $taxon));
    }

    /** @return list<Post> every published post, newest first: for a sitemap */
    public function findAllPublished(): array
    {
        return $this->findBy($this->published(), ['publishedAt' => 'DESC', 'id' => 'DESC']);
    }

    /** @return list<Post> */
    public function findLatest(int $limit = 3): array
    {
        return $this->findPublishedPage(1, $limit);
    }

    /** The featured post, or the newest one. */
    public function findHeadline(): ?Post
    {
        return $this->findOneBy($this->published(), ['featured' => 'DESC', 'publishedAt' => 'DESC']);
    }

    public function findOnePublished(string $slug): ?Post
    {
        return $this->findOneBy([...$this->published(), 'slug' => $slug]);
    }

    /** @return array{0: ?Post, 1: ?Post} the older one, the newer one */
    public function findNeighbours(Post $post): array
    {
        $at = $post->getPublishedAt();
        $older = $this->findOneBy([...$this->published(), Group::of(Parser::SEPARATOR_AND, Criterion::of('publishedAt', $at, Parser::OPTION_LOWER))], ['publishedAt' => 'DESC']);
        $newer = $this->findOneBy([...$this->published(), Group::of(Parser::SEPARATOR_AND, Criterion::of('publishedAt', $at, Parser::OPTION_GREATER))], ['publishedAt' => 'ASC']);

        return [$older, $newer];
    }

    /**
     * The topics of the posts on the site: published, and their publication date come - the tags of
     * the published posts, highest priority first, then by slug, each once.
     *
     * @return list<Tag>
     */
    public function findTopics(): array
    {
        $posts = $this->findBy($this->published());
        if (!$posts) {
            return [];
        }

        $topics = [];
        foreach ($this->repositories->of(Tag::class)->findBy(['threads' => $posts], ['priority' => 'DESC', 'slug' => 'ASC']) as $tag) {
            $topics[spl_object_id($tag)] ??= $tag;
        }

        return array_values($topics);
    }

    /** The published posts, by year then month, for the chronicle's archive. @return array<int, array<int, int>> year => month => count */
    public function countByMonth(): array
    {
        $months = [];
        foreach ($this->findBy($this->published()) as $post) {
            $at = $post->getPublishedAt() ?? new \DateTime(); // as before: a post without a date counted under this month
            $months[(int) $at->format('Y')][(int) $at->format('n')] = ($months[(int) $at->format('Y')][(int) $at->format('n')] ?? 0) + 1;
        }
        krsort($months);

        return $months;
    }

    /** A page of the posts of one month. @return list<Post> */
    public function findByMonth(int $year, int $month): array
    {
        $from = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));

        return $this->findBy([...$this->published(), Group::of(Parser::SEPARATOR_AND,
            Criterion::of('publishedAt', $from, Parser::OPTION_GREATER_EQUAL),
            Criterion::of('publishedAt', $from->modify('+1 month'), Parser::OPTION_LOWER),
        )], ['publishedAt' => 'DESC']);
    }

    /**
     * @param array<int|string, mixed> $criteria
     *
     * @return array<int|string, mixed>
     */
    private function filtered(array $criteria, ?string $tag, ?string $taxon): array
    {
        if ($tag) {
            $criteria['tags.slug'] = $tag;
        }
        if ($taxon) {
            $criteria['taxa.slug'] = $taxon;
        }

        return $criteria;
    }

    /**
     * The criteria of the posts on the site: published, and their publication date come (none, or now or before).
     *
     * @return array<int|string, mixed>
     */
    private function published(): array
    {
        return [
            'state' => ThreadState::PUBLISH,
            Group::of(Parser::SEPARATOR_OR,
                Criterion::of('publishedAt', null, Parser::OPTION_NULL),
                Criterion::of('publishedAt', new \DateTime(), Parser::OPTION_LOWER_EQUAL),
            ),
        ];
    }
}
