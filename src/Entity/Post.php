<?php

namespace Base\Blog\Entity;

use Base\Blog\Repository\PostRepository;
use Base\Database\Attribute\DiscriminatorEntry;
use Base\Database\Attribute\Uploader;
use Base\Entity\Thread;
use Base\Enum\ThreadState;
use Base\Service\Model\LinkableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A chronicle: an article, a tutorial, a note from the classroom. An
 * omnibase thread - title, headline, excerpt and text translated, slug,
 * publication state and date, owners (the author), tags (its subjects),
 * taxa (a level, a discipline, when the site has them) - written in the
 * back office (Controller\Admin\Crud\PostCrudController), with a cover,
 * a "featured" flag and the reading time on top.
 */
#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Table(name: 'blog_post')]
#[DiscriminatorEntry(value: 'blog_post')]
class Post extends Thread implements LinkableInterface
{
    /** Words read in a minute, for the reading time. */
    public const WORDS_PER_MINUTE = 220;

    public static function __iconizeStatic(): ?array
    {
        return ['fa-solid fa-feather'];
    }

    public function __toLink(array $routeParameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): ?string
    {
        return $this->getRouter()->generate('blog_post', array_merge($routeParameters, ['slug' => $this->getSlug()]), $referenceType);
    }

    /** The picture at the top of the post and on its card: an upload. */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Uploader(max_size: '8MB', mime_types: ['image/*'])]
    protected $cover = null;

    /** Shown first and larger on the list, whatever its date. */
    #[ORM\Column(type: 'boolean')]
    protected bool $featured = false;

    /** Comments closed on this post alone (the site's setting is blog.comments.enabled). */
    #[ORM\Column(type: 'boolean')]
    protected bool $commentsOpen = true;

    public function __construct(?\Base\Entity\User $owner = null, ?Thread $parent = null, ?string $title = null, ?string $slug = null)
    {
        parent::__construct($owner, $parent, $title, $slug);
    }

    public function __toString(): string
    {
        return $this->getTitle() ?? '';
    }

    public function getCover(): ?string { return Uploader::getPublic($this, 'cover'); }
    public function getCoverFile(): ?File { return Uploader::get($this, 'cover'); }
    public function setCover($cover): self { $this->cover = $cover; return $this; }

    public function isFeatured(): bool { return $this->featured; }
    public function setFeatured(bool $featured): self { $this->featured = $featured; return $this; }

    public function isCommentsOpen(): bool { return $this->commentsOpen; }
    public function setCommentsOpen(bool $open): self { $this->commentsOpen = $open; return $this; }

    /** Minutes to read it, rounded up, one at least. */
    public function getReadingMinutes(): int
    {
        $text = (string) $this->getContent();
        if (str_starts_with(ltrim($text), '{')) {
            // EditorJS JSON: the words are in the blocks' text values.
            $data = json_decode($text, true);
            $text = implode(' ', array_map(fn ($block) => implode(' ', array_filter(array_map(fn ($v) => \is_string($v) ? $v : (\is_array($v) ? implode(' ', array_filter($v, 'is_string')) : ''), $block['data'] ?? []))), $data['blocks'] ?? []));
        }
        $words = str_word_count(strip_tags($text), 0, 'àâäçéèêëîïôöùûüÿœæÀÂÄÇÉÈÊËÎÏÔÖÙÛÜŸŒÆßäöüÄÖÜ\'-');

        return max(1, (int) ceil($words / self::WORDS_PER_MINUTE));
    }

    /** Publish it now (or at $at), or take it back to draft. */
    public function publish(bool $published = true, ?\DateTimeInterface $at = null): self
    {
        $this->setState($published ? ThreadState::PUBLISH : ThreadState::DRAFT);
        if ($published) {
            $this->setPublishedAt($at ? \DateTime::createFromInterface($at) : ($this->getPublishedAt() ?? new \DateTime()));
        }

        return $this;
    }
}
