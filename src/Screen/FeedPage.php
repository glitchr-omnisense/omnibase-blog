<?php

namespace Base\Blog\Screen;

use Base\Screen\Card;
use Base\Screen\Screen;
use Base\Screen\Shape;

/**
 * The RSS feed: the channel's title (the site's name, else the host it is
 * asked at), the list's and the feed's absolute addresses, the last posts
 * (their absolute addresses).
 */
final readonly class FeedPage implements Screen
{
    /**
     * @param array{index: string, feed: string} $links
     * @param list<Card>                         $items
     */
    public function __construct(
        public string $title,
        public array $links,
        public array $items,
    ) {
    }

    public function toArray(): array
    {
        return ['title' => $this->title, 'links' => $this->links, 'items' => Shape::export($this->items)];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
