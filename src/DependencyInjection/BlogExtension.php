<?php

namespace Base\Blog\DependencyInjection;

use Base\Bundle\AbstractBaseExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class BlogExtension extends AbstractBaseExtension
{
    public function getConfiguration(array $config, ContainerBuilder $container): BlogConfiguration
    {
        return new BlogConfiguration();
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2).'/config'));
        $loader->load('services.php');

        $configuration = new BlogConfiguration();
        $config = (new Processor())->processConfiguration($configuration, $configs);

        // Flat parameters: blog.posts_per_page, blog.comments.auto_approve...
        $this->setConfiguration($container, $config, $configuration->getTreeBuilder()->buildTree()->getName());
    }
}
