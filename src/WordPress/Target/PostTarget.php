<?php

namespace Base\Blog\WordPress\Target;

use Base\Blog\Entity\Post;
use Base\Blog\WordPress\Context;
use Base\Blog\WordPress\Item;
use Base\Blog\WordPress\Result;
use Base\Blog\WordPress\TargetInterface;
use Base\Entity\Thread;
use Base\Entity\Thread\Tag;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * A blog post (omnibase/blog's Post) under the same slug: its title, its
 * excerpt, its text (its pictures copied into the site's uploads), its
 * featured picture as its cover, its categories and tags as tags,
 * published at its date (a draft stays a draft). Found again
 * by its slug: left alone, or brought up to date with --update.
 */
final class PostTarget implements TargetInterface
{
    /** @var array<int, string> an item's id => its post's slug */
    private array $slugs = [];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urls,
        #[Autowire(service: 'local.uploads')] private readonly ?FilesystemOperator $uploads = null,
    ) {
    }

    public function getName(): string
    {
        return 'post';
    }

    public function url(Item $item): ?string
    {
        return $this->urls->generate('blog_post', ['slug' => $this->slugFor($item)]);
    }

    public function import(Item $item, Context $context): string
    {
        $slug = $this->slugFor($item);
        $post = $this->entityManager->getRepository(Post::class)->findOneBy(['slug' => $slug]);
        if ($post && !$context->update) {
            return Result::KEPT;
        }
        $result = $post ? Result::UPDATED : Result::CREATED;
        $post ??= new Post($context->author instanceof \Base\Entity\User ? $context->author : null, null, $item->title, $slug);

        $post->setTitle($item->title);
        $post->setExcerpt($item->excerpt);
        $post->setContent($item->content);
        foreach ([...$item->categories, ...$item->tags] as $label) {
            $tag = $this->tag($label);
            if ($tag && !$post->getTags()->contains($tag)) {
                $post->addTag($tag);
            }
        }
        $post->publish($item->isPublished(), $item->date);
        // The featured picture as the cover, once: a cover set since (by hand, by an earlier run) stays.
        if (!$post->hasCover() && ($cover = $this->coverFile($item))) {
            $post->setCover($cover);
        }
        $this->entityManager->persist($post);

        return $result;
    }

    /**
     * The item's featured picture as a file to upload: its copy in the
     * uploads ("/uploads/wordpress/2014/05/a.jpg") read back into a
     * temporary file, which omnibase's Uploader moves where covers live.
     */
    private function coverFile(Item $item): ?File
    {
        if (null === $item->cover || !$this->uploads || !str_starts_with($item->cover, '/uploads/')) {
            return null;
        }
        try {
            $key = substr($item->cover, \strlen('/uploads/'));
            if (!$this->uploads->fileExists($key)) {
                return null;
            }
            $extension = strtolower(pathinfo($key, \PATHINFO_EXTENSION)) ?: 'jpg';
            $tmp = tempnam(sys_get_temp_dir(), 'blog');
            if (false === $tmp || !rename($tmp, $tmp .= '.'.$extension) || false === file_put_contents($tmp, $this->uploads->read($key))) {
                return null;
            }

            return new File($tmp);
        } catch (\Throwable) {
            // No cover: the post is imported all the same.
            return null;
        }
    }

    /**
     * The post's slug: the item's - unless a thread that is no post holds it
     * already (a product, a page of the same name: omnibase keeps a thread's
     * slug unique whatever its kind), and then the item's with its number on
     * the old site. Always the same for one item: a second run finds the post
     * again instead of making "slug-2", "slug-3"...
     */
    private function slugFor(Item $item): string
    {
        $slug = self::slug($item);
        if (isset($this->slugs[$item->id])) {
            return $this->slugs[$item->id];
        }
        try {
            $holder = $this->entityManager->getRepository(Thread::class)->findOneBy(['slug' => $slug]);
        } catch (\Throwable) {
            $holder = null;
        }

        return $this->slugs[$item->id] = $holder && !$holder instanceof Post ? $slug.'-'.$item->id : $slug;
    }

    private static function slug(Item $item): string
    {
        return (new AsciiSlugger())->slug($item->slug)->lower()->truncate(180)->toString();
    }

    /** "Uncategorized" and its translations are no tag. */
    private function tag(string $label): ?Tag
    {
        if (\in_array(mb_strtolower($label), ['uncategorized', 'non classé', 'allgemein', 'sin categoría'], true)) {
            return null;
        }
        $slug = (new AsciiSlugger())->slug($label)->lower()->toString();
        $tags = $this->entityManager->getRepository(Tag::class);
        $tag = $tags->findOneBy(['slug' => $slug]);
        if (!$tag) {
            $tag = new Tag($label, $slug);
            $this->entityManager->persist($tag);
            // Its id first: Thread::$tags is ordered (an #[OrderColumn]).
            $this->entityManager->flush();
        }

        return $tag;
    }
}
