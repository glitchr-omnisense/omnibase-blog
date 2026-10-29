---
title: "Installation"
order: 10
---

# Installation

## Requirements

- PHP 8.2 or later;
- doctrine/orm 2.15 or 3;
- omnisense/omnibase 3.0 or 4.0, omnibridge/symfony 1.x, omnibase/admin;
- league/flysystem 3 (the importer's pictures);
- Symfony 6, 7 or 8 (config, console, dependency-injection, form,
  http-client, http-kernel, security-bundle, string);
- an `App\Entity\User` class: a signed-in commenter, and the administrators
  notified.

Suggested: glitchr/ux-google, for Google reCAPTCHA v3 on the comment form when
`google.recaptcha.enable` is on; omnisense/omnishield, for the captcha of the
forms' guard and the spam classifier's reports.

## The bundle

```bash
composer require omnibase/blog:dev-main
```

```php
// config/bundles.php
Base\Blog\BlogBundle::class => ['all' => true],
```

## The routes

```yaml
# config/routes.yaml
blog_controller:
    resource: "@BlogBundle/src/Controller/Client"
    type: attribute
    prefix: /
```

The paths start with `%blog.path%`. After changing `blog.path`, clear the
route pool: `bin/console cache:pool:clear --all` (omnisense/omnibase keeps the
routes in a cache pool).

## The configuration

Every key is optional ([Configuration reference](configuration.md)):

```yaml
# config/packages/blog.yaml
blog:
    path: chroniques
    sitemap: true
    posts_per_page: 12
    comments:
        enabled: true
        guest_allowed: true
        guest_replies: false
        auto_approve: true
        flood_interval: 60
        min_delay: 4
        max_length: 4000
```

## The schema and the assets

```bash
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate   # blog_post (the comments are the core's threadComment)
bin/console assets:install                # public/bundles/blog/css/blog.css
```

A site that had the blog's former comment table moves its rows into
`threadComment` ([Comments](comments.md)).

## The layout

The templates extend the site's `layout1.html.twig` and fill `title`,
`description`, `content`, `stylesheets` and `javascripts`: that is the whole
contract. `blog.css` follows the custom properties `--blog-accent`,
`--blog-ink`, `--blog-soft`, `--blog-line`, `--blog-surface`,
`--blog-measure` and `--blog-measure-post`.

## Overriding the entity

The bundle maps `Base\Blog\Entity\*` to `App\Entity\Blog\*` and `Base\Blog\Repository\*` to `App\Repository\Blog\*` (omnisense/omnibase's
App-wins aliasing): an `App\Entity\Blog\Post` extending `Base\Blog\Entity\Post`
takes over.
