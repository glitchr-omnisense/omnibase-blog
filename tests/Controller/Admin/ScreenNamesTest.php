<?php

namespace Base\Blog\Tests\Controller\Admin;

use Base\Admin\Config\Crud;
use Base\Admin\Twig\EntityNameTwigExtension;
use Base\Blog\Controller\Admin\Crud\CommentCrudController;
use Base\Blog\Entity\Post;
use Base\Service\TranslatorInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The back office's screens of the blog are titled in the reader's language:
 * the posts' by the entity's name (entities+intl-icu.*.yaml), the comments'
 * by the blog's own words, the core naming neither (they read "Post" and
 * "Comment" in every language).
 */
final class ScreenNamesTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application.');
        }

        self::bootKernel();
        $request = Request::create('/admin/comments');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function languages(): array
    {
        return [
            'fr' => ['fr', 'Articles', 'Commentaires', 'Commentaire'],
            'en' => ['en', 'Posts', 'Comments', 'Comment'],
            'de' => ['de', 'Beiträge', 'Kommentare', 'Kommentar'],
        ];
    }

    /** @dataProvider languages */
    #[\PHPUnit\Framework\Attributes\DataProvider('languages')]
    public function testTheScreensAreNamed(string $locale, string $posts, string $comments, string $comment): void
    {
        static::getContainer()->get('translator')->setLocale($locale);

        $names = new EntityNameTwigExtension(static::getContainer()->get(TranslatorInterface::class));
        $this->assertSame($posts, $names->entityName(Post::class, true));

        $controller = static::getContainer()->get(CommentCrudController::class);
        $crud = (new \ReflectionMethod($controller, 'getCrudConfig'))->invoke($controller, Crud::PAGE_INDEX, 'index');
        $this->assertSame($comments, $crud->getEntityLabelInPlural());
        $this->assertSame($comment, $crud->getEntityLabelInSingular());
    }
}
