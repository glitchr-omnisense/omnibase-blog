<?php

namespace Base\Blog\Service;

use Base\Entity\Thread\Comment;
use Base\Service\SpamChecker;
use Omnishield\Exception\OmnishieldException;
use Omnishield\Model\Submission;
use Psr\Log\LoggerInterface;

/**
 * Teaching the classifier: a comment it let through that was spam
 * (submit-spam), one it held that was not (submit-ham). The classifier is
 * the one glitchr/omnibase's SpamChecker asks - Akismet with the site's key
 * (api.spam.akismet, from base.spam.akismet), or base.guard.classifier -
 * told the comment as it was classified: its text, its author, the address
 * and the browser it came from, the site's home page. Without a classifier
 * nothing is sent; a provider's error is logged, not thrown at the
 * moderator.
 */
final class AkismetReporter
{
    public function __construct(
        private readonly SpamChecker $spam,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function reportSpam(Comment $comment): bool { return $this->report($comment, true); }
    public function reportHam(Comment $comment): bool { return $this->report($comment, false); }

    private function report(Comment $comment, bool $spam): bool
    {
        if (!class_exists(Submission::class)) {
            return false; // glitchr/omnishield is not installed: no classifier
        }

        try {
            // What the visitor sent, not the moderator's request.
            return $this->spam->report($this->spam->submission($comment, array_filter([
                'user_ip' => $comment->getIp(),
                'user_agent' => $comment->getUserAgent(),
                'comment_author_url' => $comment->getWebsite(),
                'permalink' => '',
                'referrer' => '',
            ], static fn ($value) => null !== $value)), $spam);
        } catch (OmnishieldException $e) {
            $this->logger?->warning('The classifier could not be told about comment {id}: {message}', ['id' => $comment->getId(), 'message' => $e->getMessage()]);

            return false;
        }
    }
}
