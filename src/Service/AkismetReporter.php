<?php

namespace Base\Blog\Service;

use Base\Entity\Thread\Comment;
use Base\Service\SettingBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Teaching Akismet: a comment it let through that was spam (submit-spam),
 * one it held that was not (submit-ham). The key is omnibase's
 * (api.spam.akismet, from base.spam.akismet); without it nothing is sent.
 */
final class AkismetReporter
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly SettingBagInterface $settings,
        private readonly RequestStack $requests,
    ) {
    }

    public function reportSpam(Comment $comment): bool { return $this->submit('submit-spam', $comment); }
    public function reportHam(Comment $comment): bool { return $this->submit('submit-ham', $comment); }

    private function submit(string $call, Comment $comment): bool
    {
        $key = $this->settings->getScalar('api.spam.akismet');
        if (!$key) {
            return false;
        }
        $request = $this->requests->getCurrentRequest();
        $this->client->request('POST', sprintf('https://%s.rest.akismet.com/1.1/%s', $key, $call), ['body' => array_filter([
            'blog' => $request?->getSchemeAndHttpHost(),
            'user_ip' => $comment->getIp(),
            'user_agent' => $comment->getUserAgent(),
            'comment_type' => 'comment',
            'comment_author' => $comment->getName(),
            'comment_author_email' => $comment->getEmail(),
            'comment_author_url' => $comment->getWebsite(),
            'comment_content' => $comment->getContent(),
            'comment_date_gmt' => $comment->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ])]);

        return true;
    }
}
