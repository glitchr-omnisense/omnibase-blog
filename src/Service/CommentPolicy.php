<?php

namespace Base\Blog\Service;

use Base\Blog\Entity\Post;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Who may write a comment, and answer one (blog.comments.*): comments at all,
 * then without an account when guest_allowed, and on a post that keeps them
 * open; an answer takes an account unless guest_replies. Framework-free: the
 * caller says whether someone is signed in (CommentController by Symfony's
 * user, the screens by their viewer).
 */
final class CommentPolicy
{
    public function __construct(
        #[Autowire('%blog.comments.enabled%')] private readonly bool $enabled = true,
        #[Autowire('%blog.comments.guest_allowed%')] private readonly bool $guestAllowed = true,
        #[Autowire('%blog.comments.guest_replies%')] private readonly bool $guestReplies = false,
    ) {
    }

    public function isOpen(?Post $post, bool $signedIn): bool
    {
        return $this->enabled && ($this->guestAllowed || $signedIn) && (null === $post || $post->isCommentsOpen());
    }

    public function canReply(bool $signedIn): bool
    {
        return $this->guestReplies || $signedIn;
    }
}
