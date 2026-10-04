<?php

namespace Base\Blog\EventListener;

use Base\Blog\Repository\PostRepository;
use Base\Event\SitemapEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * The posts' own pages in /sitemap.xml: omnibase lists the routes it can
 * generate by itself (the blog's first page), and a post is behind a slug
 * it cannot know. Each carries the day it was last changed, so a crawler
 * comes back to the ones that move. blog.sitemap: false leaves them out.
 */
#[AsEventListener(event: SitemapEvent::BUILD)]
final class SitemapListener
{
    public function __construct(
        private readonly PostRepository $posts,
        #[Autowire('%blog.sitemap%')] private readonly bool $enabled = true,
    ) {
    }

    public function __invoke(SitemapEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }
        $sitemap = $event->getSitemapper();
        foreach ($this->posts->findAllPublished() as $post) {
            $sitemap->register('blog_post', ['slug' => $post->getSlug()], ($post->getUpdatedAt() ?? $post->getPublishedAt())?->format('c'));
        }
    }
}
