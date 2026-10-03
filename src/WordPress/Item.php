<?php

namespace Base\Blog\WordPress;

/**
 * A post or a page of a WordPress site, as its REST API gives it
 * (wp-json/wp/v2/posts, /pages): what an import target needs, the HTML
 * already cleaned and its links already rewritten to where they lead now.
 */
final class Item
{
    public const POST = 'post';
    public const PAGE = 'page';

    /**
     * @param list<string> $categories their names
     * @param list<string> $tags       their names
     * @param list<string> $ancestors  the parents' slugs, the root first ("about-me" for /about-me/biography)
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $slug,
        public readonly string $title,
        public string $content,
        public readonly ?string $excerpt,
        public readonly \DateTimeImmutable $date,
        public readonly ?\DateTimeImmutable $modified,
        public readonly string $status,
        public readonly string $link,
        public readonly int $parent = 0,
        public readonly array $ancestors = [],
        public readonly int $menuOrder = 0,
        public readonly array $categories = [],
        public readonly array $tags = [],
        public ?string $cover = null,
        public readonly ?string $locale = null,
    ) {
    }

    /** "about-me/biography": a page's path on the old site (its permalink's, else its parents'); a post's slug. */
    public function path(): string
    {
        if (self::PAGE === $this->type && '' !== $this->link && ($path = trim((string) parse_url($this->link, \PHP_URL_PATH), '/')) && !str_contains($path, '?')) {
            return $path;
        }

        return self::PAGE === $this->type ? implode('/', [...$this->ancestors, $this->slug]) : $this->slug;
    }

    public function isPublished(): bool
    {
        return 'publish' === $this->status;
    }

    /** The text without its markup, on one line. */
    public function text(): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($this->content), \ENT_QUOTES | \ENT_HTML5)));
    }
}
