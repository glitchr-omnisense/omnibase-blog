<?php

namespace Base\Blog\Tests\WordPress;

use Base\Blog\WordPress\Cleaner;
use Base\Blog\WordPress\Client;
use Base\Blog\WordPress\Context;
use Base\Blog\WordPress\Importer;
use Base\Blog\WordPress\Item;
use Base\Blog\WordPress\LinkRewriter;
use Base\Blog\WordPress\Result;
use Base\Blog\WordPress\Target\SkipTarget;
use Base\Blog\WordPress\TargetInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;

/**
 * monicaneagoy.info read through its REST API (a recorded excerpt): every
 * page of every list, the pages' paths, the HTML cleaned, the pictures
 * copied, the links rewritten, each item to its target - and a second run
 * with --update doubles nothing.
 */
final class ImporterTest extends TestCase
{
    public function testTheClientFollowsEveryPage(): void
    {
        $site = new WordPressSite();
        $pages = (new Client($site->client()))->all('https://monicaneagoy.info', 'pages');

        $this->assertCount(4, $pages);
        $this->assertCount(2, $site->requests, 'two to a page, two pages');
        $this->assertSame('https://monicaneagoy.info/wp-json/wp/v2/', Client::api('https://monicaneagoy.info/'));
    }

    public function testTheHtmlIsCleaned(): void
    {
        $html = '<p class="x" style="color:red">Hello</p><p>&nbsp;</p>[caption id="1"]<img class="alignleft" src="/a.jpg" srcset="/a-300.jpg 300w" width="10">[/caption]<div class="hupso_c"><script>var hupso=1;</script></div><script>alert(1)</script>';
        $this->assertSame('<p>Hello</p><img loading="lazy" src="/a.jpg" width="10">', (new Cleaner())->clean($html));
    }

    public function testTheLinksFollowWhatWasImported(): void
    {
        $links = new LinkRewriter('https://monicaneagoy.info', ['2014/05/book-a.jpg' => '/uploads/wordpress/2014/05/book-a.jpg'], [48 => '/biography'], ['about-me/biography' => '/biography', 'news/a-post' => '/chroniques/a-post']);

        $this->assertSame('/uploads/wordpress/2014/05/book-a.jpg', $links->to('https://monicaneagoy.info/wp-content/uploads/2014/05/book-a-150x206.jpg'));
        $this->assertSame('/biography', $links->to('https://monicaneagoy.info/?page_id=48'));
        $this->assertSame('/biography#yoga', $links->to('https://www.monicaneagoy.info/about-me/biography/#yoga'));
        $this->assertSame('/chroniques/a-post', $links->to('https://monicaneagoy.info/2017/08/a-post/'));
        $this->assertSame('/', $links->to('https://monicaneagoy.info/'));
        $this->assertNull($links->to('https://www.corwin.com/books/Book235914'), 'another site');
        $this->assertNull($links->to('https://monicaneagoy.info/?page_id=999'), 'a page not imported stays on the old site');
        $this->assertSame('<a href="/biography">Bio</a>', $links->rewrite('<a href="https://monicaneagoy.info/?page_id=48">Bio</a>'));
    }

    public function testAnImportRunTwiceDoublesNothing(): void
    {
        $site = new WordPressSite();
        $store = new MemoryTarget('page');
        $posts = new MemoryTarget('post');
        $uploads = new Filesystem(new LocalFilesystemAdapter(sys_get_temp_dir().'/blog-wordpress-'.bin2hex(random_bytes(4))));
        $importer = new Importer(new Client($site->client()), new Cleaner(), $this->createStub(EntityManagerInterface::class), $site->client(), [$store, $posts, new SkipTarget()], $uploads);

        $map = ['about-me/biography' => 'page:bio', 'contact' => 'skip', 'about-me' => 'skip'];
        $first = $importer->import('https://monicaneagoy.info', $map);

        $this->assertSame(2, $first->count(Result::CREATED, 'post'));
        $this->assertSame(2, $first->count(Result::CREATED, 'page'), 'the biography and the books');
        $this->assertSame(2, $first->count(Result::SKIPPED));
        $this->assertSame(2, $first->media);
        $this->assertTrue($uploads->fileExists('wordpress/2014/05/book-a.jpg'));
        $this->assertSame('bio', $store->options['biography']);

        $books = $store->items['books'];
        $this->assertSame('services/books', $books->path());
        $this->assertStringContainsString('src="/uploads/wordpress/2014/05/book-a.jpg"', $books->content);
        $this->assertStringNotContainsString('hupso', $books->content);
        $this->assertStringContainsString('href="http://www.corwin.com/books/Book235914"', $books->content, 'the publisher\'s link stays');
        $bio = $store->items['biography'];
        $this->assertSame('Biography of Monica Neagoy', $bio->title);
        $this->assertStringContainsString('international consultant', $bio->text());
        $post = $posts->items['monica-sur-bmftv-une-methode-pour-assimiler-les-maths'];
        $this->assertSame(['Radio/TV shows'], $post->categories);
        $this->assertSame('2018-02-15', $post->date->format('Y-m-d'));

        $second = $importer->import('https://monicaneagoy.info', $map, update: true);
        $this->assertSame(0, $second->count(Result::CREATED));
        $this->assertSame(4, $second->count(Result::UPDATED));
        $this->assertSame(0, $second->media, 'the pictures are there already');
        $this->assertCount(2, $posts->items);
        $this->assertCount(2, $store->items);

        $third = $importer->import('https://monicaneagoy.info', $map);
        $this->assertSame(4, $third->count(Result::KEPT), 'without --update, left alone');
    }

    public function testAnUnknownTargetIsRefused(): void
    {
        $site = new WordPressSite();
        $importer = new Importer(new Client($site->client()), new Cleaner(), $this->createStub(EntityManagerInterface::class), $site->client(), [new SkipTarget()]);

        $this->expectException(\InvalidArgumentException::class);
        $importer->import('https://monicaneagoy.info', ['*post' => 'nowhere'], media: false);
    }
}

/** A target that keeps what it is given, by slug. */
final class MemoryTarget implements TargetInterface
{
    /** @var array<string, Item> */
    public array $items = [];

    /** @var array<string, string> */
    public array $options = [];

    public function __construct(private readonly string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function url(Item $item): ?string
    {
        return '/'.$this->name.'/'.$item->slug;
    }

    public function import(Item $item, Context $context): string
    {
        if (null !== $option = $context->option($item)) {
            $this->options[$item->slug] = $option;
        }
        if (isset($this->items[$item->slug]) && !$context->update) {
            return Result::KEPT;
        }
        $result = isset($this->items[$item->slug]) ? Result::UPDATED : Result::CREATED;
        $this->items[$item->slug] = $item;

        return $result;
    }
}
