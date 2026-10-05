<?php

namespace Base\Blog;

use Base\Admin\Attribute\OpenToAdmins;
use Base\Admin\Controller\AbstractCrudController;
use Base\Bundle\AbstractBaseBundle;
use Base\Traits\SingletonTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Chronicles and comments on top of omnibase's Thread. A Post IS a Thread
 * (JOINED subclass); its comments are glitchr/omnibase's Base\Entity\Thread\Comment, read by Akismet.
 */
class BlogBundle extends AbstractBaseBundle
{
    use SingletonTrait;

    public function __construct()
    {
        parent::__construct();

        // The CRUD controllers carry omnibase/admin's #[OpenToAdmins]: an
        // omnibase/admin that does not have it yet gets a stand-in of the
        // same name, which opens nothing (compat/OpenToAdmins.php says why).
        if (class_exists(AbstractCrudController::class) && !class_exists(OpenToAdmins::class)) {
            require_once \dirname(__DIR__).'/compat/OpenToAdmins.php';
        }
    }

    /** Modern layout: the class lives in src/, the bundle root is the package root. */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // App\-wins, as omnibase does for its own entities: an application may
        // declare App\Entity\Blog\Post extending ours and take over.
        $this->setMapping($this->getPath().'/src/Entity', 'Base\Blog\Entity', 'App\Entity\Blog');
        $this->setMapping($this->getPath().'/src/Repository', 'Base\Blog\Repository', 'App\Repository\Blog');
    }
}
