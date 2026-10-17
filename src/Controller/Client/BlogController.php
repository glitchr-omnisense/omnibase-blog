<?php

namespace Base\Blog\Controller\Client;

use Base\Attributes\Attribute\Sitemap;
use Base\Blog\Screen\BlogScreens;
use Base\Bridge\Symfony\ScreenResponder;
use Base\Repository\Thread\CommentRepository;
use Base\Blog\Repository\PostRepository;
use Base\Blog\Service\JsonLd;
use Base\Screen\View;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The chronicles: the posts a page at a time, by topic (a tag) or by what
 * the site classifies them with (a taxon: a level, a subject), by month,
 * one post with its comments, and the RSS feed. The first segment is
 * blog.path ("chroniques" by default).
 *
 * Each action hands a template and its view model (Base\Blog\Screen\*, plan
 * "omnibase agnostique", C5) to omnibase's ScreenResponder: the templates read
 * `screen` and `frame` alone, a front end gets the same data in JSON. The
 * variables the pages had before (the entities) are passed as well, for the
 * templates a site overrides (wineseller's post page): deprecated, gone in 4.0.
 */
class BlogController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly CommentRepository $comments,
        #[Autowire('%blog.posts_per_page%')] private readonly int $perPage = 12,
        private readonly ?JsonLd $jsonLd = null,
        private readonly ?BlogScreens $screens = null,
        private readonly ?ScreenResponder $responder = null,
        private readonly ?CommentController $commenting = null,
    ) {
    }

    /** @param \Closure(): array<string, mixed> $legacy the variables of before, for a template the site overrides */
    private function screen(Request $request, View $view, \Closure $legacy, array $slots = []): Response
    {
        return $this->responder->respond($request, $view, $slots + self::legacy($this->container->get('twig'), $view->template, $legacy));
    }

    /**
     * The variables a page had before its view model (the entities), given
     * only to a template the site overrides (templates/bundles/BlogBundle/...):
     * the blog's own read the model alone, and the queries are not made twice.
     *
     * @param \Closure(): array<string, mixed> $variables
     *
     * @return array<string, mixed>
     */
    public static function legacy(\Twig\Environment $twig, string $template, \Closure $variables): array
    {
        $path = (string) $twig->getLoader()->getSourceContext($template)->getPath();
        $own = realpath(\dirname(__DIR__, 3).'/templates') ?: \dirname(__DIR__, 3).'/templates';

        return str_starts_with(realpath($path) ?: $path, $own.'/') ? [] : $variables();
    }

    #[Sitemap(priority: 0.8, changefreq: 'daily')]
    #[Route('/%blog.path%', name: 'blog_index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $tag = $request->query->get('sujet') ?: null;
        $taxon = $request->query->get('classe') ?: null;

        return $this->screen($request, new View('@Blog/client/index.html.twig', $this->screens->index($page, $tag, $taxon), 'Blog/Index'), function () use ($page, $tag, $taxon): array {
            $headline = 1 === $page && !$tag && !$taxon ? $this->posts->findHeadline() : null;

            return [
                'headline' => $headline,
                'posts' => array_values(array_filter($this->posts->findPublishedPage($page, $this->perPage, $tag, $taxon), fn ($post) => $post !== $headline)),
                'topics' => $this->posts->findTopics(),
                'topic' => $tag,
                'taxon' => $taxon,
                'months' => $this->posts->countByMonth(),
                'page' => $page,
                'pages' => max(1, (int) ceil($this->posts->countPublished($tag, $taxon) / $this->perPage)),
            ];
        });
    }

    #[Route('/%blog.path%/{year}/{month}', name: 'blog_month', requirements: ['year' => '\d{4}', 'month' => '\d{1,2}'])]
    public function month(Request $request, int $year, int $month): Response
    {
        return $this->screen($request, new View('@Blog/client/index.html.twig', $this->screens->month($year, $month), 'Blog/Index'), fn () => [
            'headline' => null,
            'posts' => $this->posts->findByMonth($year, $month),
            'topics' => $this->posts->findTopics(),
            'topic' => null,
            'taxon' => null,
            'month' => new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)),
            'months' => $this->posts->countByMonth(),
            'page' => 1,
            'pages' => 1,
        ]);
    }

    #[Route('/%blog.path%/feed.xml', name: 'blog_feed', format: 'xml')]
    public function feed(Request $request): Response
    {
        $response = $this->screen($request, new View('@Blog/client/feed.xml.twig', $this->screens->feed($request->getHttpHost()), 'Blog/Feed'), fn () => [
            'posts' => $this->posts->findPublishedPage(1, 20),
        ]);
        if (!str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');
        }

        return $response;
    }

    // Listed in /sitemap.xml by EventListener\SitemapListener, one entry a post: the attribute gives their priority.
    #[Sitemap(priority: 0.6, changefreq: 'monthly')]
    #[Route('/%blog.path%/{slug}', name: 'blog_post', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function post(Request $request, string $slug): Response
    {
        $post = $this->posts->findOnePublished($slug)
            ?? throw $this->createNotFoundException(sprintf('No published post "%s".', $slug));
        $screen = $this->screens->post($post, $request->getSchemeAndHttpHost().$request->getBasePath());

        return $this->screen($request, new View('@Blog/client/post.html.twig', $screen, 'Blog/Post'), function () use ($post, $screen): array {
            [$older, $newer] = $this->posts->findNeighbours($post);

            return ['post' => $post, 'older' => $older, 'newer' => $newer, 'comments' => $this->comments->findVisible($post), 'jsonld' => $screen->jsonLd];
        }, [
            // The comment form (Symfony's, D3): beside the model, built when the page takes comments.
            'comment_form' => $screen->discussion->open ? $this->commenting->form($post)->createView() : null,
        ]);
    }
}
