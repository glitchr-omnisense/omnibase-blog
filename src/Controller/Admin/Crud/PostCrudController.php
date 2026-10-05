<?php

namespace Base\Blog\Controller\Admin\Crud;

use Base\Blog\Controller\Admin\OpenToAdminsTrait;
use Base\Admin\Attribute\OpenToAdmins;
use Base\Admin\Config\Actions;
use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filters;
use Base\Blog\Entity\Post;
use Base\Field\BooleanField;
use Base\Field\DateTimeField;
use Base\Field\EditorField;
use Base\Field\IdField;
use Base\Field\ImageField;
use Base\Field\SelectField;
use Base\Field\SlugField;
use Base\Field\StateField;
use Base\Field\TextareaField;
use Base\Field\TextField;

/**
 * Writing a chronicle: a title, a line under it, the preview shown on the
 * list, the text (EditorJS, autosaved), its subjects (tags), its cover.
 * Published, it is on /chroniques at its date (a date to come schedules it).
 */
#[OpenToAdmins]
class PostCrudController extends AbstractCrudController
{
    use OpenToAdminsTrait; // for an omnibase/admin without #[OpenToAdmins]

    public static function getEntityFqcn(): string
    {
        return Post::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-feather';
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('state')->add('featured')->add('tags');
    }

    public function configureActions(Actions $actions): Actions
    {
        // #[OpenToAdmins] opens the screen; the trait does on an omnibase/admin that does not have it.
        return $this->openToAdmins(parent::configureActions($actions));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('title', '@blog.admin.post.title')->setColumns(8);
        yield StateField::new('state')->setColumns(4);
        yield SlugField::new('slug')->setColumns(4)->hideOnIndex();
        yield DateTimeField::new('publishedAt', '@blog.admin.post.published_at')->setColumns(4);
        yield BooleanField::new('featured', '@blog.admin.post.featured')->setColumns(2);
        yield BooleanField::new('commentsOpen', '@blog.admin.post.comments_open')->setColumns(2);
        yield TextField::new('headline', '@blog.admin.post.headline')->setColumns(12)->hideOnIndex();
        yield TextareaField::new('excerpt', '@blog.admin.post.excerpt')->hideOnIndex()->setHelp('@blog.admin.post.excerpt_help');
        yield EditorField::new('content', '@blog.admin.post.content')->hideOnIndex()->setFormTypeOption('collab_autosave', true)->setFormTypeOption('collab_live', true);
        yield SelectField::new('tags', '@blog.admin.post.tags')->allowMultipleChoices()->setRequired(false)->setColumns(6)->hideOnIndex();
        yield SelectField::new('taxa', '@blog.admin.post.taxa')->allowMultipleChoices()->setRequired(false)->setColumns(6)->hideOnIndex();
        yield ImageField::new('cover', '@blog.admin.post.cover')->setColumns(6)->hideOnIndex();
    }

    public function createEntity(string $entityFqcn): object
    {
        $post = new Post();
        $user = $this->getUser();
        if ($user instanceof \Base\Entity\User) {
            $post->addOwner($user);
        }

        return $post;
    }
}
