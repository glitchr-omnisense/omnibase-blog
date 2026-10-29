# omnibase/blog

omnibase/blog is the chronicles of a site of the omnibase family - a
teacher's, a studio's, a wine seller's - and what visitors leave under them or
in the visitors' book, with no account needed. Its namespace is
`Base\Blog\`; it is built on omnisense/omnibase and omnibase/admin.

## What it adds

- A `Post` that is omnisense/omnibase's `Thread` (a JOINED subclass, table
  `blog_post`): title, headline, excerpt, text (EditorJS or HTML), tags, taxa,
  owners, slug, states and scheduling, revisions, soft delete and
  translations come from the thread; the post adds a cover, a "featured" flag,
  open or closed comments and its reading time.
- Comments that are omnisense/omnibase's `Base\Entity\Thread\Comment`: under a
  post, or in the visitors' book when they have no post.
- Seven routes: the list (by page, by topic, by taxon), a month, a post, the
  RSS feed, the visitors' book, and the two comment forms. The first segment
  of the blog's addresses is `blog.path` (`chroniques` by default).
- View models for every page (`Base\Blog\Screen\*`): the templates read them,
  and a client asking for JSON gets the same data.
- The comment flow: the guard, the spam classifier, moderation, the
  administrators notified, the classifier taught by the moderator's choices.
- schema.org's `BlogPosting` on a post, the posts in `/sitemap.xml`.
- Twig functions for a host's own pages: the latest posts, the comments, the
  form, the cards.
- Two back office screens (posts, comments) open to the site's
  administrator, and a dashboard tile of the comments waiting.
- `blog:import-wordpress`, which moves a WordPress site in through its REST
  API.
- English, French and German messages (domain `blog`).

## Pages

- [Installation](installation.md)
- [Posts](concepts.md)
- [The pages](pages.md)
- [Comments](comments.md)
- [In a host's pages](templates.md)
- [Moving a WordPress site in](wordpress-import.md)
- [The back office](back-office.md)
- [Configuration reference](configuration.md)
- [Testing](testing.md)
