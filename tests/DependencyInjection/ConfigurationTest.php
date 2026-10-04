<?php

namespace Base\Blog\Tests\DependencyInjection;

use Base\Blog\DependencyInjection\BlogConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testASiteThatSaysNothingKeepsChroniques(): void
    {
        $config = (new Processor())->processConfiguration(new BlogConfiguration(), []);

        self::assertSame('chroniques', $config['path']);
        self::assertTrue($config['sitemap']);
        self::assertSame(12, $config['posts_per_page']);
    }

    public function testASitesOwnPath(): void
    {
        $config = (new Processor())->processConfiguration(new BlogConfiguration(), [['path' => 'actualites', 'sitemap' => false]]);

        self::assertSame('actualites', $config['path']);
        self::assertFalse($config['sitemap']);
    }

    public function testAPathWithSlashesAroundIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new BlogConfiguration(), [['path' => '/actualites']]);
    }
}
