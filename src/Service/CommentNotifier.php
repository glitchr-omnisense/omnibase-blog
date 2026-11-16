<?php

namespace Base\Blog\Service;

use App\Entity\User;
use Base\Entity\Thread\Comment;
use Base\Entity\User\Notification;
use Base\Database\Repository\Finder\Criterion;
use Base\Database\Repository\Finder\Group;
use Base\Database\Repository\Finder\Parser;
use Base\Database\Repository\Repositories;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The administrators told of a new comment: in the bell of omnibase's
 * notification centre, with a link to the comments screen. A
 * comment that waits says so; one that went online says that.
 */
final class CommentNotifier
{
    public function __construct(
        private readonly Repositories $repositories,
        private readonly RoleHierarchyInterface $roles,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function commentLeft(Comment $comment): void
    {
        $key = $comment->isPending() ? 'notify.pending' : 'notify.approved';
        $where = $comment->getThread()?->getTitle() ?? $this->translator->trans('book.title', [], 'blog');
        $title = $this->translator->trans($key, ['name' => $comment->getDisplayName(), 'where' => $where], 'blog');
        $url = $this->urls->generate('admin_crud_comments_index', ['filters' => ['state' => $comment->getState()->value]]);

        foreach ($this->administrators() as $admin) {
            $notification = new Notification($title);
            $notification->setUser($admin);
            $notification->setTitle($title);
            $notification->setSubject($title);
            $notification->setContent(mb_strimwidth((string) $comment->getContent(), 0, 240, '…'));
            $notification->setUrl($url);
            // 'notify': the in-app channel (the bell); an e-mail would need a template of its own.
            $notification->send('notify');
        }
    }

    /** @return list<User> */
    private function administrators(): array
    {
        // Every account above a plain member (roles <> 'ROLE_USER'), then those who reach ROLE_ADMIN.
        $users = $this->repositories->of(User::class)->findBy([Group::of(Parser::SEPARATOR_AND, Criterion::of('roles', 'ROLE_USER', Parser::OPTION_NOT_EQUAL))]);

        return array_values(array_filter($users, fn (User $user) => \in_array('ROLE_ADMIN', $this->roles->getReachableRoleNames($user->getRoles()), true)));
    }
}
