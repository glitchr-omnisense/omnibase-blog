<?php

namespace Base\Blog\Controller\Client;

use Base\Attributes\Attribute\Sitemap;
use Base\Repository\Thread\CommentRepository;
use Base\Blog\Repository\PostRepository;
use Base\Blog\Service\JsonLd;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The chronicles: the posts a page at a time, by topic (a tag) or by what
 * the site classifies them with (a taxon: a level, a subject), by month,
 * one post with its comments, and the RSS feed. The first segment is
 * blog.path ("chroniques" by default).
 */
class BlogController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly CommentRepository $comments,
        #[Autowire('%blog.posts_per_page%')] private readonly int $perPage = 12,
        private readonly ?JsonLd $jsonLd = null,
    ) {
    }

    #[Sitemap(priority: 0.8, changefreq: 'daily')]
    #[Route('/%blog.path%', name: 'blog_index')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $tag = $request->query->get('sujet') ?: null;
        $taxon = $request->query->get('classe') ?: null;
        $posts = $this->posts->findPublishedPage($page, $this->perPage, $tag, $taxon);
        $headline = 1 === $page && !$tag && !$taxon ? $this->posts->findHeadline() : null;

        return $this->render('@Blog/client/index.html.twig', [
            'headline' => $headline,
            'posts' => array_values(array_filter($posts, fn ($post) => $post !== $headline)),
            'topics' => $this->posts->findTopics(),
            'topic' => $tag,
            'taxon' => $taxon,
            'months' => $this->posts->countByMonth(),
            'page' => $page,
            'pages' => max(1, (int) ceil($this->posts->countPublished($tag, $taxon) / $this->perPage)),
        ]);
    }

    #[Route('/%blog.path%/{year}/{month}', name: 'blog_month', requirements: ['year' => '\d{4}', 'month' => '\d{1,2}'])]
    public function month(int $year, int $month): Response
    {
        return $this->render('@Blog/client/index.html.twig', [
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
    public function feed(): Response
    {
        $response = $this->render('@Blog/client/feed.xml.twig', [
            'posts' => $this->posts->findPublishedPage(1, 20),
        ]);
        $response->headers->set('Content-Type', 'application/rss+xml; charset=UTF-8');

        return $response;
    }

    // Listed in /sitemap.xml by EventListener\SitemapListener, one entry a post: the attribute gives their priority.
    #[Sitemap(priority: 0.6, changefreq: 'monthly')]
    #[Route('/%blog.path%/{slug}', name: 'blog_post', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function post(Request $request, string $slug): Response
    {
        $post = $this->posts->findOnePublished($slug)
            ?? throw $this->createNotFoundException(sprintf('No published post "%s".', $slug));
        [$older, $newer] = $this->posts->findNeighbours($post);

        return $this->render('@Blog/client/post.html.twig', [
            'post' => $post,
            'older' => $older,
            'newer' => $newer,
            'comments' => $this->comments->findVisible($post),
            'jsonld' => $this->jsonLd?->article(
                $post,
                $this->generateUrl('blog_post', ['slug' => $post->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                $request->getSchemeAndHttpHost().$request->getBasePath(),
            ),
        ]);
    }
}
