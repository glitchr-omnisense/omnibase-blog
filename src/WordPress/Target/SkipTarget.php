<?php

namespace Base\Blog\WordPress\Target;

use Base\Blog\WordPress\Context;
use Base\Blog\WordPress\Item;
use Base\Blog\WordPress\Result;
use Base\Blog\WordPress\TargetInterface;

/** Left behind: a template page, a form the new site does again, a page with nothing in it. */
final class SkipTarget implements TargetInterface
{
    public function getName(): string
    {
        return 'skip';
    }

    public function url(Item $item): ?string
    {
        return null;
    }

    public function import(Item $item, Context $context): string
    {
        return Result::SKIPPED;
    }
}
