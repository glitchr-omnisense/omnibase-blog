<?php

namespace Base\Blog\Twig;

use Base\Blog\Controller\Client\CommentController;
use Base\Blog\Entity\Post;
use Base\Repository\Thread\CommentRepository;
use Base\Blog\Repository\PostRepository;
use Symfony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * What a host's own pages ask the blog: the last posts for a home page, the
 * comments of a post or of the visitors' book, and the comment form to put
 * under them.
 */
final class BlogExtension extends AbstractExtension
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly CommentRepository $comments,
        private readonly CommentController $commentController,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('blog_recent', fn (int $limit = 3): array => $this->posts->findLatest($limit)),
            new TwigFunction('blog_comments', fn (?Post $post = null): array => $this->comments->findVisible($post)),
            new TwigFunction('blog_comments_count', fn (?Post $post = null): int => $this->comments->countVisible($post)),
            new TwigFunction('blog_comments_open', fn (?Post $post = null): bool => $this->commentController->isOpen($post)),
            new TwigFunction('blog_can_reply', fn (): bool => $this->commentController->canReply()),
            new TwigFunction('blog_comment_form', fn (?Post $post = null): FormView => $this->commentController->form($post)->createView()),
        ];
    }
}
