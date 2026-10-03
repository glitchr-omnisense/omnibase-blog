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
