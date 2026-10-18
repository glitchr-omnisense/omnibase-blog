<?php

namespace Base\Blog\Screen;

use Base\Screen\Card;
use Base\Screen\Screen;
use Base\Screen\Shape;
use Base\Screen\Tag;

/**
 * The chronicles a page at a time (lesringostars' BlogPage: page, pages,
 * found, next, articles), and what omnibase's blog shows around them: the
 * newer page's address, the featured post on the first page, the topics to
 * filter by (the one asked marked current), the topic or taxon asked (their
 * slugs), the month of a month's page (its first day, ISO), the archive's
 * years and months (newest first, each with its count and address), and the
 * list's and the feed's addresses (indexUrl, feedUrl: the frame owns `links`).
 */
final readonly class BlogPage implements Screen
{
    /**
     * @param list<Card>                                                                         $articles
     * @param list<Tag>                                                                          $topics
     * @param list<array{year: int, months: list<array{month: int, count: int, url: string}>}> $months
     */
    public function __construct(
        public int $page,
        public int $pages,
        public int $found,
        public ?string $next,
        public array $articles,
        public ?string $prev = null,
        public ?Card $headline = null,
        public array $topics = [],
        public ?string $topic = null,
        public ?string $taxon = null,
        public ?string $month = null,
        public array $months = [],
        public ?string $indexUrl = null,
        public ?string $feedUrl = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'pages' => $this->pages,
            'found' => $this->found,
            'next' => $this->next,
            'articles' => Shape::export($this->articles),
            'prev' => $this->prev,
            'headline' => $this->headline?->toArray(),
            'topics' => Shape::export($this->topics),
            'topic' => $this->topic,
            'taxon' => $this->taxon,
            'month' => $this->month,
            'months' => $this->months,
            'indexUrl' => $this->indexUrl,
            'feedUrl' => $this->feedUrl,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
