<?php

namespace Base\Blog\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Widget\DashboardWidgetTypeInterface;
use Base\Repository\Thread\CommentRepository;

/**
 * The dashboard's tile: how many comments wait, the last few of them, each
 * opening the comments screen. `yield MenuItem::block('blog_pending_comments', ...)`
 * in the dashboard's configureWidgetItems() places it.
 */
final class PendingCommentsWidgetType implements DashboardWidgetTypeInterface
{
    public function __construct(private readonly CommentRepository $comments)
    {
    }

    public static function getName(): string
    {
        return 'blog_pending_comments';
    }

    public function getTemplate(): string
    {
        return '@Blog/admin/widget/pending_comments.html.twig';
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        return [
            'pending' => $this->comments->countPending(),
            'comments' => $this->comments->findPending(5),
        ];
    }
}
