---
title: "The pages"
order: 30
---

# The pages

## Routes

With `blog.path: chroniques`:

| Name | Path | Methods | Template | Query |
|---|---|---|---|---|
| `blog_index` | `/chroniques` | any | `@Blog/client/index.html.twig` | `page`, `sujet` (a tag's slug), `classe` (a taxon's slug) |
| `blog_month` | `/chroniques/{year}/{month}` | any | `@Blog/client/index.html.twig` | |
| `blog_feed` | `/chroniques/feed.xml` | any | `@Blog/client/feed.xml.twig` | |
| `blog_post` | `/chroniques/{slug}` | any | `@Blog/client/post.html.twig` | |
| `blog_post_comment` | `/chroniques/{slug}/commenter` | POST | | |
| `blog_book` | `/livre-d-or` | any | `@Blog/client/book.html.twig` | |
| `blog_book_comment` | `/livre-d-or/commenter` | POST | | |

`year` is four digits, `month` one or two, `slug` `[a-z0-9-]+`. The visitors'
book is always at `/livre-d-or`, whatever `blog.path` says. An unknown or
unpublished slug is a 404.

## What each page shows

- The list: the published posts, `posts_per_page` at a time, newest first. On
  the first page, without a topic or a taxon, the headline (the featured post,
  else the newest) is drawn large and left out of the cards. The topics (the
  tags in use), the months with their counts, the previous and next pages,
  the feed's address.
- A month: its posts, on one page.
- A post: its story (title, cover at 1600 pixels, text, tags, taxa, reading
  time), the older and the newer post, its comments and the form when it
  takes comments, its `BlogPosting`.
- The feed: the twenty newest posts, by their absolute addresses, as RSS
  (`Content-Type: application/rss+xml; charset=UTF-8`), its channel titled
  with the site's title, else the host.
- The visitors' book: the comments without a post, and the form.

## View models

Each action hands omnisense/omnibase's `ScreenResponder` a view: a template
and its model, built by `Base\Blog\Screen\BlogScreens`.

| Page | Model | Its fields |
|---|---|---|
| list, month | `BlogPage` | `page`, `pages`, `found`, `next`, `prev`, `articles` (cards), `headline`, `topics`, `topic`, `taxon`, `month`, `months`, `indexUrl`, `feedUrl` |
| post | `PostPage` | `article` (a story), `prev`, `next`, `discussion`, `jsonLd` |
| book | `BookPage` | `discussion` |
| feed | `FeedPage` | `title`, `indexUrl`, `feedUrl`, `items` |

A `Discussion` holds the visible comments, whether the visitor may write
(`open`), whether they may answer (`canReply`), the sign-in address and the
flashes of the `blog` category. Covers are drawn at 800 pixels on a card,
1200 for the headline, 1600 on a post (`BlogScreens::CARD`, `HEADLINE`,
`POST`).

The blog's templates read `screen` and `frame` alone. A template a site
overrides in `templates/bundles/BlogBundle/` also receives the variables the
pages had before the models (`posts`, `post`, `comments`, `headline`...);
those are deprecated and go in omnisense/omnibase 4.0.

## As JSON

A request whose `Accept` header asks for `application/json` (and not
`text/html`) gets the page's model as JSON instead of the HTML, with
`Vary: Accept`:

```bash
curl -H 'Accept: application/json' https://example.org/chroniques
```

## The sitemap

`/chroniques` is in `/sitemap.xml` through its route (priority 0.8, daily).
`Base\Blog\EventListener\SitemapListener` adds every published post on
`SitemapEvent::BUILD`, dated the day it last changed (its update, else its
publication); `blog.sitemap: false` leaves the posts out.
