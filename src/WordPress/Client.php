<?php

namespace Base\Blog\WordPress;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A WordPress site's REST API (wp-json/wp/v2), read whole: every page of
 * posts, pages, media, categories and tags, a hundred at a time, following
 * X-WP-TotalPages. Nothing is written to the site; no key is needed for what
 * a public site publishes.
 */
final class Client
{
    public function __construct(private readonly HttpClientInterface $http)
    {
    }

    /**
     * @param string $type posts, pages, media, categories, tags
     *
     * @return list<array<string, mixed>>
     */
    public function all(string $site, string $type, array $query = []): array
    {
        $base = self::api($site);
        $all = [];
        $page = 1;
        do {
            $response = $this->http->request('GET', $base.$type, ['query' => $query + ['per_page' => 100, 'page' => $page], 'headers' => ['Accept' => 'application/json']]);
            if (400 === $response->getStatusCode() && $page > 1) {
                break; // past the last page
            }
            $items = $response->toArray();
            array_push($all, ...$items);
            $pages = (int) ($response->getHeaders()['x-wp-totalpages'][0] ?? 1);
        } while ($items && ++$page <= $pages);

        return $all;
    }

    /** "https://example.org" or "https://example.org/wp-json/wp/v2/" → the API's root, with its slash. */
    public static function api(string $site): string
    {
        $site = rtrim($site, '/');

        return str_contains($site, '/wp-json') ? $site.'/' : $site.'/wp-json/wp/v2/';
    }
}
