<?php

namespace Base\Blog\DependencyInjection;

use Base\Bundle\AbstractBaseConfiguration;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class BlogConfiguration extends AbstractBaseConfiguration
{
    private bool $childrenDeclared = false;

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = $this->getTreeBuilder();
        if ($this->childrenDeclared) {
            return $treeBuilder;
        }
        $this->childrenDeclared = true;

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('path')->defaultValue('chroniques')->cannotBeEmpty()
                    ->info('The first segment of the blog\'s addresses: /chroniques, /chroniques/{slug}, /chroniques/feed.xml. No slash.')
                    ->validate()
                        ->ifTrue(fn ($path) => !\is_string($path) || !preg_match('#^[a-z0-9][a-z0-9\-_/]*[a-z0-9]$#i', $path))
                        ->thenInvalid('blog.path is one or more URL segments without a leading or trailing slash, %s given.')
                    ->end()
                ->end()
                ->booleanNode('sitemap')->defaultTrue()
                    ->info('The published posts in /sitemap.xml.')->end()
                ->integerNode('posts_per_page')->min(1)->defaultValue(12)
                    ->info('Posts listed per page.')->end()
                ->arrayNode('comments')->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->info('Comments at all.')->end()
                        ->booleanNode('guest_allowed')->defaultTrue()->info('Comments without an account (a name and an e-mail).')->end()
                        ->booleanNode('guest_replies')->defaultFalse()->info('Replies without an account. Off: anyone writes a word, answering one takes an account.')->end()
                        ->booleanNode('auto_approve')->defaultTrue()->info('A comment Akismet finds clean goes online at once; otherwise every comment waits.')->end()
                        ->integerNode('flood_interval')->min(0)->defaultValue(60)->info('Seconds between two comments from the same address.')->end()
                        ->integerNode('min_delay')->min(0)->defaultValue(4)->info('Seconds between the form being opened and sent: faster is a robot.')->end()
                        ->integerNode('max_length')->min(100)->defaultValue(4000)->end()
                    ->end()
                ->end()
            ->end()
        ->end();

        return $treeBuilder;
    }
}
