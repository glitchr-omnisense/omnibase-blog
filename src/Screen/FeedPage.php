<?php

namespace Base\Blog\Screen;

use Base\Screen\Card;
use Base\Screen\Screen;
use Base\Screen\Shape;

/**
 * The RSS feed: the channel's title (the site's name, else the host it is
 * asked at), the list's and the feed's absolute addresses (indexUrl, feedUrl:
 * the frame owns `links`), the last posts (their absolute addresses).
 */
final readonly class FeedPage implements Screen
{
    /**
     * @param list<Card> $items
     */
    public function __construct(
        public string $title,
        public string $indexUrl,
        public string $feedUrl,
        public array $items,
    ) {
    }

    public function toArray(): array
    {
        return ['title' => $this->title, 'indexUrl' => $this->indexUrl, 'feedUrl' => $this->feedUrl, 'items' => Shape::export($this->items)];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
