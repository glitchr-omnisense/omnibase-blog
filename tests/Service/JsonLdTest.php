<?php

namespace Base\Blog\Tests\Service;

use Base\Blog\Service\JsonLd;
use Base\Blog\Tests\Fixtures\TestPost;
use PHPUnit\Framework\TestCase;

final class JsonLdTest extends TestCase
{
    public function testAPostIsABlogPosting(): void
    {
        $post = new TestPost('Quelle enseigne choisir ?', 'quelle-enseigne-choisir');
        $post->testExcerpt = '<p>Panneau, caisson ou lettres en relief.</p>';
        $post->testContent = '<p>'.str_repeat('mot ', 300).'</p>';
        $post->testCover = '/srv/app/public/uploads/blog/post/cover/a.jpg';
        $post->testPublishedAt = new \DateTimeImmutable('2026-03-02 09:00:00', new \DateTimeZone('UTC'));
        $post->testUpdatedAt = new \DateTimeImmutable('2026-03-05 10:30:00', new \DateTimeZone('UTC'));
        $post->testOwners = ['La plume'];
        $post->testTags = ['Enseigne', 'Guide'];

        $data = (new JsonLd())->article($post, 'https://example.org/actualites/quelle-enseigne-choisir', 'https://example.org');

        self::assertSame('https://schema.org', $data['@context']);
        self::assertSame('BlogPosting', $data['@type']);
        self::assertSame('Quelle enseigne choisir ?', $data['headline']);
        self::assertSame('https://example.org/actualites/quelle-enseigne-choisir', $data['url']);
        self::assertSame(['@type' => 'WebPage', '@id' => 'https://example.org/actualites/quelle-enseigne-choisir'], $data['mainEntityOfPage']);
        self::assertSame('Panneau, caisson ou lettres en relief.', $data['description']);
        self::assertSame('2026-03-02T09:00:00+00:00', $data['datePublished']);
        self::assertSame('2026-03-05T10:30:00+00:00', $data['dateModified']);
        self::assertSame(['https://example.org/uploads/blog/post/cover/a.jpg'], $data['image']);
        self::assertSame(['@type' => 'Person', 'name' => 'La plume'], $data['author']);
        self::assertSame('Enseigne, Guide', $data['keywords']);
        self::assertSame(440, $data['wordCount'], '300 words: two minutes of reading');
    }

    public function testAPostWithoutCoverNorAuthorKeepsToWhatItKnows(): void
    {
        $post = new TestPost('Une note', 'une-note');
        $post->testPublishedAt = new \DateTimeImmutable('2026-01-10 08:00:00', new \DateTimeZone('UTC'));

        $data = (new JsonLd())->article($post, 'https://example.org/chroniques/une-note');

        self::assertArrayNotHasKey('image', $data);
        self::assertArrayNotHasKey('author', $data);
        self::assertArrayNotHasKey('description', $data);
        self::assertArrayNotHasKey('keywords', $data);
        self::assertSame($data['datePublished'], $data['dateModified'], 'never changed: the day it was published');
    }

    public function testALongTitleIsCutForTheHeadline(): void
    {
        $data = (new JsonLd())->article(new TestPost(str_repeat('Signalétique ', 12)), 'https://example.org/chroniques/x');

        self::assertLessThanOrEqual(110, mb_strlen($data['headline']));
    }

    public function testTheCoversAddressOnTheSite(): void
    {
        $post = new TestPost('A');
        self::assertNull($post->getCoverUrl());
        $post->testCover = '/srv/app/public/uploads/a.jpg';
        self::assertSame('/uploads/a.jpg', $post->getCoverUrl());
        $post->testCover = 'https://cdn.example.org/a.jpg';
        self::assertSame('https://cdn.example.org/a.jpg', $post->getCoverUrl());
    }
}
