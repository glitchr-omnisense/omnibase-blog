<?php

namespace Base\Blog\WordPress\Target;

use Base\Blog\Entity\Post;
use Base\Blog\WordPress\Context;
use Base\Blog\WordPress\Item;
use Base\Blog\WordPress\Result;
use Base\Blog\WordPress\TargetInterface;
use Base\Entity\Thread\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * A blog post (omnibase/blog's Post) under the same slug: its title, its
 * excerpt, its text (its pictures copied into the site's uploads), its categories and
 * tags as tags, published at its date (a draft stays a draft). Found again
 * by its slug: left alone, or brought up to date with --update.
 */
final class PostTarget implements TargetInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function getName(): string
    {
        return 'post';
    }

    public function url(Item $item): ?string
    {
        return $this->urls->generate('blog_post', ['slug' => self::slug($item)]);
    }

    public function import(Item $item, Context $context): string
    {
        $slug = self::slug($item);
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
        $this->entityManager->persist($post);

        return $result;
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
