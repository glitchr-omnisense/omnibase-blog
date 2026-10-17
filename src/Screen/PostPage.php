<?php

namespace Base\Blog\Screen;

use Base\Screen\Discussion;
use Base\Screen\Link;
use Base\Screen\Screen;
use Base\Screen\Story;

/**
 * One chronicle (lesringostars' StoryPage: article, prev, next, connex; its
 * comments in a Discussion): the post, the older one (prev) and the newer
 * (next), no connex, its comments and whether one may write, and schema.org's
 * BlogPosting (null on the page shown again with a form's errors).
 */
final readonly class PostPage implements Screen
{
    /** @param array<string, mixed>|null $jsonLd */
    public function __construct(
        public Story $article,
        public ?Link $prev,
        public ?Link $next,
        public Discussion $discussion,
        public ?Link $connex = null,
        public ?array $jsonLd = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'article' => $this->article->toArray(),
            'prev' => $this->prev?->toArray(),
            'next' => $this->next?->toArray(),
            'connex' => $this->connex?->toArray(),
            'discussion' => $this->discussion->toArray(),
            'jsonLd' => $this->jsonLd,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
