<?php

namespace Base\Blog\Tests\Controller;

use Base\Blog\Controller\Client\CommentController;
use Base\Service\CommentGuard;
use Base\Service\FormGuard;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The comment form the blog builds (CommentController::form(), the visitors' book's) is guarded twice
 * over, as glitchr/omnibase guards a comment: CommentType's own trap (url) and time (opened), read by
 * Base\Service\CommentGuard with the blog's min_delay, and the forms' guard - its signed stamp, the
 * lists, the captcha when the host has glitchr/omnishield. A comment written as a person writes it goes
 * through; a filled trap, a comment sent faster than the blog's delay and a missing captcha token are
 * refused. Run by a host application's PHPUnit (its test captcha: omnishield's "fixed" gateway).
 */
final class CommentGuardTest extends KernelTestCase
{
    private Request $request;

    protected function setUp(): void
    {
        $_SERVER['KERNEL_CLASS'] ??= $_ENV['KERNEL_CLASS'] ?? 'App\\Kernel';
        if (!class_exists($_SERVER['KERNEL_CLASS'])) {
            self::markTestSkipped('Needs a host application (its kernel).');
        }
        if (!class_exists(FormGuard::class)) {
            self::markTestSkipped('Needs a glitchr/omnibase with the forms\' guard.');
        }
        self::bootKernel();
        $this->request = Request::create('https://localhost/livre-d-or/commenter', 'POST', server: ['REMOTE_ADDR' => '203.0.113.'.random_int(10, 250)]);
        $this->request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($this->request);
    }

    /** @param array<string, ?string> $overrides */
    private function send(array $overrides = []): FormInterface
    {
        $form = static::getContainer()->get(CommentController::class)->form(null);
        $data = [
            'content' => 'Un mot laissé dans le livre d\'or, pour la vérification du formulaire.',
            'name' => 'Camille Weber',
            'email' => 'camille@example.org',
            'url' => '',
            'opened' => (string) (time() - 10),
            'parent' => '',
            'guard_opened' => static::getContainer()->get(FormGuard::class)->stamp(time() - 10),
        ];
        if ($form->has('guard_captcha')) {
            $data['guard_captcha'] = 'omnishield-fixed-token';
        }
        $data = array_intersect_key(array_filter($overrides + $data, static fn ($value) => null !== $value), iterator_to_array($form));
        // The form's CSRF token, as its page prints it (not a child of the form).
        foreach ($form->createView()->children as $name => $child) {
            if (str_contains($name, 'token') && !isset($data[$name])) {
                $data[$name] = $child->vars['value'];
            }
        }
        $form->submit($data, false);

        return $form;
    }

    private function why(FormInterface $form): ?string
    {
        return static::getContainer()->get(CommentGuard::class)->check($form, $this->request, 4, 0);
    }

    public function testACommentWrittenAsAPersonWritesItGoesThrough(): void
    {
        $form = $this->send();
        self::assertNull($this->why($form));
        self::assertCount(0, $form->getErrors(true), (string) $form->getErrors(true));
    }

    public function testAFilledTrapIsARobot(): void
    {
        self::assertSame(CommentGuard::TRAPPED, $this->why($this->send(['url' => 'https://spam.example'])));
    }

    public function testACommentSentFasterThanTheBlogsDelayIsRefused(): void
    {
        self::assertSame(CommentGuard::TOO_FAST, $this->why($this->send(['opened' => (string) time()])));
        self::assertSame(CommentGuard::TOO_FAST, $this->why($this->send(['guard_opened' => static::getContainer()->get(FormGuard::class)->stamp()])), 'the guard\'s stamp too');
    }

    public function testACommentWithoutTheCaptchasTokenIsRefused(): void
    {
        $form = static::getContainer()->get(CommentController::class)->form(null);
        if (!$form->has('guard_captcha')) {
            self::markTestSkipped('The host application has no captcha (glitchr/omnishield).');
        }
        $form = $this->send(['guard_captcha' => '']);
        self::assertGreaterThan(0, $form->get('guard_captcha')->getErrors()->count(), 'refused on the captcha');
    }
}
