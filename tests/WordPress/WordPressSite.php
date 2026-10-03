<?php

namespace Base\Blog\Tests\WordPress;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * monicaneagoy.info's REST API, as recorded (tests/Fixtures/wordpress: two
 * posts, four pages, two pictures, three categories), answered without a
 * network; each request asked for is kept.
 */
final class WordPressSite
{
    /** @var list<string> */
    public array $requests = [];

    public function client(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url): MockResponse {
            $this->requests[] = $url;
            if (str_contains($url, '/wp-content/uploads/')) {
                return new MockResponse('PNG-or-JPEG bytes of '.basename(parse_url($url, \PHP_URL_PATH)));
            }
            if (!preg_match('#/wp-json/wp/v2/(posts|pages|media|categories|tags)\?#', $url, $m)) {
                return new MockResponse('', ['http_code' => 404]);
            }
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            $all = json_decode((string) file_get_contents(__DIR__.'/../Fixtures/wordpress/'.$m[1].'.json'), true);
            // Two a page, to follow X-WP-TotalPages.
            $page = (int) ($query['page'] ?? 1);
            $slice = \array_slice($all, ($page - 1) * 2, 2);

            return new MockResponse(json_encode($slice), ['response_headers' => ['content-type' => 'application/json', 'x-wp-total' => (string) \count($all), 'x-wp-totalpages' => (string) max(1, (int) ceil(\count($all) / 2))]]);
        }, 'https://monicaneagoy.info');
    }
}
