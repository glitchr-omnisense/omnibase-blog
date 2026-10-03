<?php

namespace Base\Blog\WordPress;

/**
 * The old site's links, to where their targets are now: a picture of its
 * uploads (wp-content/uploads/..., a thumbnail "-300x225" to its full size)
 * to the copy imported, an imported post or page (by its permalink, by
 * "?p=123" or "?page_id=123") to its new address, the old home page to the
 * new one. A link to a page that was not imported is left to the old site.
 */
final class LinkRewriter
{
    /**
     * @param array<string, string> $media  an upload's URL on the old site (without its size suffix) => its new URL
     * @param array<int, string>    $byId   a post's or a page's id => its new URL
     * @param array<string, string> $byPath a permalink's path ("about-me/biography") => its new URL
     */
    public function __construct(
        private readonly string $site,
        private readonly array $media = [],
        private readonly array $byId = [],
        private readonly array $byPath = [],
        private readonly string $home = '/',
    ) {
    }

    public function rewrite(string $html): string
    {
        return (string) preg_replace_callback('#\b(href|src)=(["\'])(.*?)\2#i', function (array $m) {
            $to = $this->to(html_entity_decode($m[3], \ENT_QUOTES | \ENT_HTML5));

            return null === $to ? $m[0] : $m[1].'="'.htmlspecialchars($to, \ENT_QUOTES).'"';
        }, $html);
    }

    /** The new address of an old one, or null to leave it. */
    public function to(string $url): ?string
    {
        $host = parse_url($this->site, \PHP_URL_HOST);
        $parts = parse_url($url);
        if (false === $parts) {
            return null;
        }
        $urlHost = $parts['host'] ?? null;
        if (null !== $urlHost && preg_replace('/^www\./', '', $urlHost) !== preg_replace('/^www\./', '', (string) $host)) {
            return null; // another site
        }
        if (null === $urlHost && !str_starts_with($url, '/')) {
            return null; // relative, an anchor, a mailto:
        }
        $path = $parts['path'] ?? '/';

        if (str_contains($path, '/wp-content/uploads/')) {
            $key = self::mediaKey($url);

            return $this->media[$key] ?? null;
        }
        parse_str($parts['query'] ?? '', $query);
        foreach (['p', 'page_id'] as $param) {
            if (isset($query[$param]) && isset($this->byId[(int) $query[$param]])) {
                return $this->byId[(int) $query[$param]].(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
            }
        }
        $trimmed = trim($path, '/');
        if ('' === $trimmed && !isset($query['p']) && !isset($query['page_id'])) {
            return $this->home;
        }
        if (isset($this->byPath[$trimmed])) {
            return $this->byPath[$trimmed].(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
        }
        // A dated permalink (/2017/08/15/a-post/) or a category's: by its last segment.
        $last = basename($trimmed);
        foreach ($this->byPath as $known => $new) {
            if (basename($known) === $last) {
                return $new;
            }
        }

        return null;
    }

    /** An upload's address without its host nor its size suffix: "2014/05/book-a.jpg". */
    public static function mediaKey(string $url): string
    {
        $path = (string) (parse_url($url, \PHP_URL_PATH) ?? $url);
        $path = substr($path, (int) strpos($path, '/wp-content/uploads/') + \strlen('/wp-content/uploads/'));

        return (string) preg_replace('#-\d+x\d+(\.[a-z0-9]+)$#i', '$1', $path);
    }
}
