---
title: "Configuration reference"
order: 40
---

# Configuration reference

## The `blog` tree

Dumped with Symfony's `YamlReferenceDumper` from
`Base\Blog\DependencyInjection\BlogConfiguration`:

```yaml
blog:

    # The first segment of the blog's addresses: /chroniques, /chroniques/{slug}, /chroniques/feed.xml. No slash.
    path:                 chroniques

    # The published posts in /sitemap.xml.
    sitemap:              true

    # Posts listed per page.
    posts_per_page:       12
    comments:

        # Comments at all.
        enabled:              true

        # Comments without an account (a name and an e-mail).
        guest_allowed:        true

        # Replies without an account. Off: anyone writes a word, answering one takes an account.
        guest_replies:        false

        # A comment Akismet finds clean goes online at once; otherwise every comment waits.
        auto_approve:         true

        # Seconds between two comments from the same address.
        flood_interval:       60

        # Seconds between the form being opened and sent: faster is a robot.
        min_delay:            4
        max_length:           4000
```

| Key | Type | Default | Read by |
|---|---|---|---|
| `path` | string | `chroniques` | the routes' first segment; one or more segments, no slash at either end (`/blog` is refused) |
| `sitemap` | boolean | true | `SitemapListener` |
| `posts_per_page` | integer, at least 1 | 12 | the list |
| `comments.enabled` | boolean | true | `CommentPolicy` |
| `comments.guest_allowed` | boolean | true | `CommentPolicy`: without it, commenting takes an account |
| `comments.guest_replies` | boolean | false | `CommentPolicy`: with it, answering a comment takes no account |
| `comments.auto_approve` | boolean | true | a comment the classifier finds clean goes online at once; off, every comment waits |
| `comments.flood_interval` | integer, at least 0 | 60 | the guard: seconds between two comments from one address |
| `comments.min_delay` | integer, at least 0 | 4 | the guard: seconds between the form's opening and its sending |
| `comments.max_length` | integer, at least 100 | 4000 | the comment form's `max_length` |

Each key is a container parameter: `%blog.path%`, `%blog.sitemap%`,
`%blog.posts_per_page%`, `%blog.comments.enabled%`...
`%blog.comments.max_length%`.

## Services

`config/services.php` autowires and autoconfigures the bundle's classes; the
admin screens and the widget are loaded with omnibase/admin.

| Service | Role |
|---|---|
| `Base\Blog\Repository\PostRepository` | the post queries ([Posts](concepts.md)) |
| `Base\Blog\Screen\BlogScreens` | the pages' view models, `card()`, `discussion()` |
| `Base\Blog\Service\CommentPolicy` | `isOpen($post, $signedIn)`, `canReply($signedIn)` |
| `Base\Blog\Service\CommentNotifier` | `commentLeft($comment)` |
| `Base\Blog\Service\AkismetReporter` | `reportSpam($comment)`, `reportHam($comment)` |
| `Base\Blog\Service\JsonLd` | `article($post, $url, $base)` |
| `Base\Blog\EventListener\SitemapListener` | the posts of `/sitemap.xml` |
| `Base\Blog\Twig\BlogExtension` | the Twig functions |
| `Base\Blog\Command\ImportWordPressCommand` | `blog:import-wordpress` |

A service tagged `blog.wordpress_target` (`Base\Blog\WordPress\TargetInterface`,
autoconfigured) is a target of the WordPress import
([Moving a WordPress site in](wordpress-import.md)).
