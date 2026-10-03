<?php

namespace Base\Blog\WordPress;

/**
 * The run as a target sees it: whether an existing record is brought up to
 * date (--update) or left alone, the site read, the options a target was
 * given in the map ("schedule-2016=agenda:2016" gives "2016"), the
 * author of what is created, and where to say what went wrong.
 */
final class Context
{
    /** @param array<string, string> $options item slug => what follows the target's name in the map */
    public function __construct(
        public readonly string $site,
        public readonly bool $update = false,
        public readonly ?object $author = null,
        public readonly array $options = [],
        public readonly ?Result $result = null,
    ) {
    }

    public function option(Item $item): ?string
    {
        return $this->options[$item->slug] ?? null;
    }

    public function warn(string $message): void
    {
        if ($this->result) {
            $this->result->warnings[] = $message;
        }
    }
}
