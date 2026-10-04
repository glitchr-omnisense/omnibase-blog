<?php

namespace Base\Blog\Tests\Fixtures;

use Base\Blog\Entity\Post;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * A Post that keeps its texts in plain properties: omnibase's Thread reads
 * them from a translation entity through the kernel's localizer, which a
 * unit test has none of. What the blog adds is the real one.
 */
class TestPost extends Post
{
    public ?string $testExcerpt = null;
    public ?string $testHeadline = null;
    public ?string $testContent = null;
    public ?string $testCover = null;
    public ?\DateTimeInterface $testPublishedAt = null;
    public ?\DateTimeInterface $testUpdatedAt = null;
    /** @var list<string> */
    public array $testOwners = [];
    /** @var list<string> */
    public array $testTags = [];

    public function __construct(private ?string $testTitle = null, ?string $slug = null)
    {
        // Thread's constructor is not run: it needs the kernel.
        $this->slug = $slug;
    }

    public function getTitle(?string $locale = null, int $inheritanceDepthIfNotSet = 0): ?string { return $this->testTitle; }
    public function getExcerpt(?string $locale = null, int $inheritanceDepthIfNotSet = 0): ?string { return $this->testExcerpt; }
    public function getHeadline(?string $locale = null, int $inheritanceDepthIfNotSet = 0): ?string { return $this->testHeadline; }
    public function getContent(?string $locale = null, int $inheritanceDepthIfNotSet = 0): ?string { return $this->testContent; }
    public function getPublishedAt(): ?\DateTimeInterface { return $this->testPublishedAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->testUpdatedAt; }
    public function getOwners(): Collection { return new ArrayCollection($this->testOwners); }
    public function getTags(): Collection { return new ArrayCollection($this->testTags); }

    /** The cover without the storage (Uploader asks the kernel where it is): the path given here. */
    public function getCover(): ?string { return $this->testCover; }
    public function hasCover(): bool { return null !== $this->testCover; }
}
