<?php

namespace Base\Blog\Twig;

use Base\Blog\Controller\Client\CommentController;
use Base\Blog\Entity\Post;
use Base\Repository\Thread\CommentRepository;
use Base\Blog\Repository\PostRepository;
use Base\Blog\Screen\BlogScreens;
use Base\Screen\Card;
use Base\Screen\Discussion;
use Symfony\Component\Form\FormView;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * What a host's own pages ask the blog: the last posts for a home page, the
 * comments of a post or of the visitors' book, and the comment form to put
 * under them. blog_card() and blog_discussion() give the view models the
 * blog's partials read (plan "omnibase agnostique", C5) from a host page's own
 * entities: `_card.html.twig` and `_meta.html.twig` with a `post`,
 * `_comments.html.twig` with a `post` (null: the visitors' book) and its
 * `comments`.
 */
final class BlogExtension extends AbstractExtension
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly CommentRepository $comments,
        private readonly CommentController $commentController,
        private readonly ?BlogScreens $screens = null,
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
            new TwigFunction('blog_card', fn (Post $post, int $width = BlogScreens::CARD): Card => $this->screens->card($post, $width)),
            new TwigFunction('blog_discussion', fn (?Post $post = null, ?iterable $comments = null): Discussion => $this->screens->discussion($post, $comments)),
        ];
    }
}
