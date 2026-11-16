<?php

namespace Base\Blog\Tests\Service;

use Base\Blog\Service\AkismetReporter;
use Base\Entity\Thread\Comment;
use Base\Routing\AdvancedRouterInterface;
use Base\Service\FormGuard;
use Base\Service\ParameterBagInterface;
use Base\Service\SettingBagInterface;
use Base\Service\SpamChecker;
use Base\Service\TranslatorInterface;
use Omnishield\Registry;
use Omnishield\Testing\FixedGateway;
use Omnishield\Testing\FixedGatewayFactory;
use Base\Contract\Requests;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A moderator's verdict goes back to the classifier glitchr/omnibase's
 * SpamChecker asks: the comment as the visitor sent it - their address and
 * browser, not the moderator's - the site's home page.
 */
final class AkismetReporterTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(Registry::class) || !method_exists(SpamChecker::class, 'report')) {
            self::markTestSkipped('glitchr/omnishield, and glitchr/omnibase with SpamChecker::report().');
        }
    }

    private function checker(?Registry $registry): SpamChecker
    {
        $requests = new RequestStack();
        $requests->push(Request::create('https://site.example/admin/comments/12/spam', 'POST', [], [], [], ['REMOTE_ADDR' => '198.51.100.1', 'HTTP_USER_AGENT' => 'the moderator\'s browser']));
        $router = $this->createMock(AdvancedRouterInterface::class);
        $router->method('getUrlIndex')->willReturn('/');
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('getLocale')->willReturn('fr');
        $translator->method('getFallbackLocales')->willReturn([]);

        return new SpamChecker(self::requests($requests->getCurrentRequest()), $this->createMock(SettingBagInterface::class), $this->createMock(ParameterBagInterface::class), $translator, new MockHttpClient(), false, new FormGuard('secret', ['classifier' => 'comments'], $registry), $router);
    }

    /**
     * The request being answered, as the core's Requests contract reads it: the visitor's address, headers and
     * address asked (the host's adapter, a container service, is not built here: no kernel).
     */
    private static function requests(Request $request): Requests
    {
        return new class($request) implements Requests {
            public function __construct(private readonly Request $request)
            {
            }

            public function has(): bool { return true; }
            public function ip(): ?string { return $this->request->getClientIp(); }
            public function header(string $name): ?string { return $this->request->headers->get($name); }
            public function uri(): ?string { return $this->request->getUri(); }
            public function host(): ?string { return $this->request->getSchemeAndHttpHost(); }
            public function route(): ?string { return $this->request->attributes->get('_route'); }
            public function attribute(string $name, mixed $default = null): mixed { return $this->request->attributes->get($name, $default); }
            public function session(string $key, mixed $default = null): mixed { return $default; }
            public function remember(string $key, mixed $value): void {}
            public function forget(string $key): void {}
            public function flash(string $type, string $message): void {}
            public function flashes(): array { return []; }
            public function request(): ?object { return $this->request; }
        };
    }

    private function comment(): Comment
    {
        return (new Comment())->setName('Anne')->setEmail('anne@example.org')->setContent('Achetez maintenant')->setIp('203.0.113.7')->setUserAgent('Firefox');
    }

    public function testASpamLetThroughIsReportedAsTheVisitorSentIt(): void
    {
        $registry = new Registry([new FixedGatewayFactory()], ['comments' => ['factory' => 'fixed']]);

        $this->assertTrue((new AkismetReporter($this->checker($registry)))->reportSpam($this->comment()));
        $this->assertTrue((new AkismetReporter($this->checker($registry)))->reportHam($this->comment()));

        $gateway = $registry->classifier('comments');
        $this->assertInstanceOf(FixedGateway::class, $gateway);
        [[$submission, $spam], [, $ham]] = $gateway->reports;
        $this->assertTrue($spam);
        $this->assertFalse($ham);
        $this->assertSame('203.0.113.7', $submission->ip, 'the visitor\'s address, not the moderator\'s');
        $this->assertSame('Firefox', $submission->userAgent);
        $this->assertSame('Anne', $submission->author);
        $this->assertSame('Achetez maintenant', $submission->content);
        $this->assertSame('https://site.example/', $submission->site);
        $this->assertNull($submission->permalink, 'not the moderation page');
    }

    public function testWithoutAClassifierNothingIsSent(): void
    {
        $this->assertFalse((new AkismetReporter($this->checker(new Registry([], []))))->reportSpam($this->comment()));
    }
}
