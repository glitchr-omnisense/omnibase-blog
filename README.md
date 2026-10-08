# Blog

Chronicles and their comments for [omnibase](https://github.com/glitchr-studio/omnibase):
the posts of a site - a teacher's, a studio's - and what visitors leave under
them, or in the visitors' book of the home page, with no account needed.

A `Post` **is** an omnibase `Thread` (a JOINED subclass), so it inherits what
every thread has - a title, a headline, an excerpt, the text (EditorJS or
HTML), tags, taxa, owners, followers, likes, a slug, publish states and
scheduling, revisions, soft delete, translations - and the bundle only adds
a cover, a "featured" flag and the reading time.

A comment is glitchr/omnibase's `Base\Entity\Thread\Comment` (table
`threadComment`, its `thread` the post - none: the visitors' book; see
`docs/comments.md`): a name, an e-mail, a text, a parent for a reply, and a
state. It implements omnibase's `SpamProtectionInterface`, so the
`FormTypeSpamExtension` sends every comment to **Akismet** (`base.spam.akismet`)
before it is saved: a blatant spam is refused, a doubtful one waits for
moderation (`PENDING`), a clean one goes online at once when
`blog.comments.auto_approve` says so. A honeypot field and a minimum delay
between the form's opening and its sending catch the robots that never reach
Akismet, and `flood_interval` seconds must pass between two comments from the
same address. When `glitchr/ux-google` is installed and `google.recaptcha.enable`
is on, Google reCAPTCHA v3 is added to the form as well.

## Install

```bash
composer require omnibase/blog:dev-main
```

```php
// config/bundles.php
Base\Blog\BlogBundle::class => ['all' => true],
```

```yaml
# config/routes.yaml
blog_controller:
    resource: "@BlogBundle/src/Controller/Client"
    type: attribute
    prefix: /
```

```yaml
# config/packages/blog.yaml (every key optional)
blog:
    path: chroniques            # the first segment of every address: /chroniques, /chroniques/{slug}, /chroniques/feed.xml
    sitemap: true               # the published posts in /sitemap.xml
    posts_per_page: 12
    comments:
        enabled: true
        guest_allowed: true     # comments without an account
        guest_replies: false    # answering a comment takes an account; anyone still writes a word
        auto_approve: true      # a clean comment goes online at once
        flood_interval: 60      # seconds between two comments from one address
        min_delay: 4            # seconds a human needs to fill the form
```

Then `bin/console doctrine:migrations:diff && bin/console doctrine:migrations:migrate`
and `bin/console assets:install` (the stylesheet lives in `public/css/blog.css`).

## Addresses, sitemap, JSON-LD

- `blog.path` (default `chroniques`) is the first segment of the blog's
  routes: a site of news sets `actualites`, an English one `blog`. The route
  names do not change (`blog_index`, `blog_post`, `blog_month`, `blog_feed`,
  `blog_post_comment`); after changing it, `bin/console cache:pool:clear --all`
  (omnibase keeps the routes in a pool).
- Every published post is in omnibase's `/sitemap.xml`
  (`EventListener\SitemapListener`, on `SitemapEvent::BUILD`) with the day it
  last changed; `blog.sitemap: false` leaves them out.
- A post's page carries schema.org's `BlogPosting` (`Service\JsonLd::article()`:
  headline, description, dates, author, cover, keywords, word count), in a
  `<script type="application/ld+json">`.
- The templates draw a cover through omnibase's `|picture` (`post.getCover()|picture(800)`:
  in Twig `post.cover` is the stored name, not an address).
- `Post::hasCover()` tells whether a cover is set without asking the storage;
  `Post::getCoverUrl()` is its address on the site (`/uploads/…`).

## What the host provides

The templates extend `layout1.html.twig` and fill `title`, `description`,
`content`, `stylesheets` and `javascripts`: that is the whole contract. Two
Twig functions help a home page: `blog_recent(n)` lists the last posts and
`blog_comments(null)` the visitors' book; `{% include '@Blog/client/_comments.html.twig' with {post: null} %}`
renders the book with its form.

The back office gets `Post` and `Comment` CRUDs (approve, mark as spam,
restore; report a comment to Akismet as spam or ham) and a dashboard widget,
`blog_pending_comments`, counting what waits.

The two CRUDs are written by the site's administrator (`ROLE_ADMIN`: a
teacher, an editor), not by the super-admin only: they carry omnibase/admin's
`#[OpenToAdmins]` - creating, editing and deleting a post; deleting a comment
and its `approve`, `spam` and `trash`. The attribute is
omnibase/admin's from 7474f85.

## Moving a WordPress site in

`bin/console blog:import-wordpress https://example.org` reads a WordPress
site's REST API: its posts become posts here, its pages go where `--map`
sends them (the site's own targets), its pictures are copied, its links
rewritten, and a second run (`--update`) doubles nothing - see
[docs/wordpress-import.md](docs/wordpress-import.md).

## Tests

`vendor/bin/phpunit` (or `php vendor/bin/phpunit -c vendor/omnibase/blog/phpunit.xml.dist`
inside a host): the WordPress import on a recorded excerpt of a real site's API;
inside a host only, who writes in the back office
(`tests/Controller/Admin/OpenToAdminsTest`).

## License

MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
