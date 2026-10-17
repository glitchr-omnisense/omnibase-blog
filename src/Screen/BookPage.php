<?php

namespace Base\Blog\Screen;

use Base\Screen\Discussion;
use Base\Screen\Screen;

/** The visitors' book: the comments left on no post, and whether one may write. */
final readonly class BookPage implements Screen
{
    public function __construct(public Discussion $discussion)
    {
    }

    public function toArray(): array
    {
        return ['discussion' => $this->discussion->toArray()];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
