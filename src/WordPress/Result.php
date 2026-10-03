<?php

namespace Base\Blog\WordPress;

/** What a target did with an item, and the run's count of each. */
final class Result
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const KEPT = 'kept';
    public const SKIPPED = 'skipped';

    /** @var array<string, array<string, int>> target => result => count */
    public array $counts = [];

    /** @var list<string> */
    public array $warnings = [];

    public int $media = 0;

    public function add(string $target, string $result): void
    {
        $this->counts[$target][$result] = ($this->counts[$target][$result] ?? 0) + 1;
    }

    public function count(string $result, ?string $target = null): int
    {
        $total = 0;
        foreach ($this->counts as $name => $counts) {
            if (null === $target || $name === $target) {
                $total += $counts[$result] ?? 0;
            }
        }

        return $total;
    }
}
