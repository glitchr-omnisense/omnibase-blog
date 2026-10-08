<?php

namespace Base\Blog\Controller\Admin\Crud;

use Base\Admin\Attribute\AdminAction;
use Base\Admin\Attribute\OpenToAdmins;
use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filters;
use Base\Entity\Thread\Comment;
use Base\Enum\CommentState;
use Base\Blog\Service\AkismetReporter;
use Base\Field\AssociationField;
use Base\Field\DateTimeField;
use Base\Field\IdField;
use Base\Field\SelectField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Moderating the comments: what waits, what is online, what Akismet held.
 * Three buttons on each - approve, spam, trash - and two that teach Akismet
 * (a spam it let through, a comment it held wrongly).
 */
#[OpenToAdmins(actions: ['approve', 'spam', 'trash'])]
class CommentCrudController extends AbstractCrudController
{

    private AkismetReporter $akismet;

    #[Required]
    public function setBlogServices(AkismetReporter $akismet): void
    {
        $this->akismet = $akismet;
    }

    public static function getEntityFqcn(): string
    {
        return Comment::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-comments';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('state')->add('thread');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield SelectField::new('state', '@blog.admin.comment.state')->setColumns(3);
        yield TextField::new('name', '@blog.admin.comment.name')->setColumns(3);
        yield TextField::new('email', '@blog.admin.comment.email')->setColumns(3)->hideOnIndex();
        yield TextField::new('website', '@blog.admin.comment.website')->setColumns(3)->hideOnIndex();
        yield AssociationField::new('thread', '@blog.admin.comment.post')->setColumns(6)->setRequired(false);
        yield AssociationField::new('parent', '@blog.admin.comment.parent')->setColumns(6)->setRequired(false)->hideOnIndex();
        yield TextareaField::new('content', '@blog.admin.comment.content');
        yield DateTimeField::new('createdAt', '@blog.admin.comment.created_at')->onlyOnIndex();
        yield TextField::new('ip', '@blog.admin.comment.ip')->onlyOnDetail();
        yield TextField::new('spamScore', '@blog.admin.comment.spam_score')->onlyOnDetail();
    }

    public function configureActions(Actions $actions): Actions
    {
        // Comments come from the visitors: moderated here, never written here.
        // (#[OpenToAdmins] opens the screen and its approve, spam and trash.)
        $actions = parent::configureActions($actions)->disable(Action::NEW);
        foreach ([Actions::PAGE_INDEX, Actions::PAGE_DETAIL] as $page) {
            $actions
                ->add($page, Action::new('approve', '@blog.admin.comment.action.approve', 'fa-solid fa-check')->linkToCrudAction('approve'))
                ->add($page, Action::new('spam', '@blog.admin.comment.action.spam', 'fa-solid fa-ban')->linkToCrudAction('spam'))
                ->add($page, Action::new('trash', '@blog.admin.comment.action.trash', 'fa-solid fa-trash-can')->linkToCrudAction('trash'));
        }

        return $actions;
    }

    #[AdminAction('/{entityId}/approve')]
    public function approve(string $entityId): Response
    {
        /** @var Comment $comment */
        $comment = $this->findEntity($entityId);
        $wasSpam = CommentState::SPAM === $comment->getState();
        $comment->approve();
        $this->entityManager->flush();
        // Held as spam, now approved: Akismet is told it was ham.
        if ($wasSpam || ($comment->getSpamScore() ?? 0) > 0) {
            $this->akismet->reportHam($comment);
        }
        $this->addFlash('success', '@blog.admin.comment.flash.approved');

        return $this->redirectToIndex();
    }

    #[AdminAction('/{entityId}/spam')]
    public function spam(string $entityId): Response
    {
        /** @var Comment $comment */
        $comment = $this->findEntity($entityId);
        $letThrough = $comment->isVisible();
        $comment->markAsSpam();
        $this->entityManager->flush();
        if ($letThrough || 0 === ($comment->getSpamScore() ?? 0)) {
            $this->akismet->reportSpam($comment);
        }
        $this->addFlash('success', '@blog.admin.comment.flash.spam');

        return $this->redirectToIndex();
    }

    #[AdminAction('/{entityId}/trash')]
    public function trash(string $entityId): Response
    {
        /** @var Comment $comment */
        $comment = $this->findEntity($entityId);
        $comment->trash();
        $this->entityManager->flush();
        $this->addFlash('success', '@blog.admin.comment.flash.trashed');

        return $this->redirectToIndex();
    }
}
