<?php

namespace Base\Blog\WordPress;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Where an imported post or page goes: a blog post (`post`), nowhere
 * (`skip`), or anything a site declares - a biography, a page of its own,
 * an event, a service. A service implementing this is a target, named by
 * getName(); `blog:import-wordpress --map slug=name` sends a page to it.
 */
#[AutoconfigureTag('blog.wordpress_target')]
interface TargetInterface
{
    public function getName(): string;

    /**
     * Its new address, before anything is written - the links of every
     * other page are rewritten to it. Null: no page of its own.
     */
    public function url(Item $item): ?string;

    /**
     * Write it (persist, do not flush). Its content's links are rewritten
     * already, its pictures copied.
     *
     * @return string one of Result::*
     */
    public function import(Item $item, Context $context): string;
}
