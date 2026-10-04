<?php

namespace Base\Blog\Service;

use Base\Blog\Entity\Post;

/**
 * A post as schema.org reads it: an Article (BlogPosting) with its
 * headline, its dates, its author, its cover and its subjects. Plain
 * arrays: the template encodes them.
 */
final class JsonLd
{
    /**
     * @param string $url  the post's absolute address
     * @param string $base the site's scheme and host ("https://example.org"), put before the cover's path
     *
     * @return array<string, mixed>
     */
    public function article(Post $post, string $url, string $base = ''): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => mb_strimwidth((string) $post->getTitle(), 0, 110, '…'),
            'url' => $url,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
        ];
        $description = trim(strip_tags((string) ($post->getExcerpt() ?? $post->getHeadline() ?? '')));
        if ('' !== $description) {
            $data['description'] = $description;
        }
        if ($post->getPublishedAt()) {
            $data['datePublished'] = $post->getPublishedAt()->format('c');
        }
        if ($post->getUpdatedAt() ?? $post->getPublishedAt()) {
            $data['dateModified'] = ($post->getUpdatedAt() ?? $post->getPublishedAt())->format('c');
        }
        $cover = $post->getCoverUrl();
        if ($cover) {
            $data['image'] = [str_starts_with($cover, '/') ? $base.$cover : $cover];
        }
        $authors = [];
        foreach ($post->getOwners() as $owner) {
            if ('' !== trim((string) $owner)) {
                $authors[] = ['@type' => 'Person', 'name' => trim((string) $owner)];
            }
        }
        if ($authors) {
            $data['author'] = 1 === \count($authors) ? $authors[0] : $authors;
        }
        $keywords = [];
        foreach ($post->getTags() as $tag) {
            $keywords[] = (string) $tag;
        }
        if ($keywords) {
            $data['keywords'] = implode(', ', $keywords);
        }
        $data['wordCount'] = $post->getReadingMinutes() * Post::WORDS_PER_MINUTE;

        return $data;
    }
}
