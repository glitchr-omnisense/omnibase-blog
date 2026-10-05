<?php

namespace Base\Blog\Controller\Admin;

use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;

/**
 * This bundle's screens are written by the site's administrators
 * (ROLE_ADMIN: a teacher, an editor), not only by the super-admin the
 * admin bundle requires by default for anything that writes. They say so
 * with omnibase/admin's #[OpenToAdmins]; this trait is what they said it
 * with before, kept for an omnibase/admin that does not have the attribute
 * yet (before its commit 7474f85) and doing nothing on one that has it. It
 * goes when no application mounts the bundle on such an admin any more.
 * $custom names the screen's own actions (#[AdminAction]).
 */
trait OpenToAdminsTrait
{
    protected function openToAdmins(Actions $actions, string ...$custom): Actions
    {
        if (method_exists($actions, 'openTo')) {
            return $actions; // this omnibase/admin applies the attribute the controller carries
        }

        return $actions->setPermissions(array_fill_keys(array_merge([
            Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE,
            Action::SAVE_AND_RETURN, Action::SAVE_AND_CONTINUE, Action::SAVE_AND_ADD_ANOTHER,
        ], $custom), 'ROLE_ADMIN'));
    }
}
