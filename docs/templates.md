---
title: "In a host's pages"
order: 34
---

# In a host's pages

## Twig functions

| Function | Returns |
|---|---|
| `blog_recent(limit = 3)` | the newest published posts |
| `blog_comments(post = null)` | the visible comments of a post, or of the visitors' book |
| `blog_comments_count(post = null)` | how many |
| `blog_comments_open(post = null)` | whether the visitor may comment there |
| `blog_can_reply()` | whether the visitor may answer a comment |
| `blog_comment_form(post = null)` | the comment form's view, posting to the post or to the book |
| `blog_card(post, width = 800)` | a post's card, the model `_card.html.twig` reads |
| `blog_discussion(post = null, comments = null)` | the discussion model `_comments.html.twig` reads; the comments given, else the visible ones |

## The partials

| Partial | Variables |
|---|---|
| `@Blog/client/_card.html.twig` | `card` (a `Base\Screen\Card`), or a host's own `post` entity, made a card with `blog_card()` |
| `@Blog/client/_meta.html.twig` | the same: the date, the reading time, the taxa and tags |
| `@Blog/client/_comments.html.twig` | `discussion`, or a host's `post` (null: the visitors' book) and `comments`, made a discussion with `blog_discussion()`; `comment_form`, else the blog's form when the discussion is open |
| `@Blog/client/_comment.html.twig` | one comment |

A home page with the three latest posts and the visitors' book:

```twig
{% for post in blog_recent(3) %}
    {% include '@Blog/client/_card.html.twig' with {post: post} %}
{% endfor %}

{% include '@Blog/client/_comments.html.twig' with {post: null} %}
```

## Overriding

A site overrides a template in `templates/bundles/BlogBundle/` (for instance
`templates/bundles/BlogBundle/client/post.html.twig`). Such a template gets
the model (`screen`) and, until omnisense/omnibase 4.0, the former variables
([The pages](pages.md)).

## The stylesheet

`bundles/blog/css/blog.css` is added by every page's `stylesheets` block. Its
custom properties: `--blog-accent`, `--blog-ink`, `--blog-soft`,
`--blog-line`, `--blog-surface`, `--blog-measure` (the page's width, 72rem
by default) and `--blog-measure-post` (a post's text, 46rem).

## Translations

The domain `blog`, in English, French and German (ICU format): `index`,
`post`, `book`, `comment` (the form, its errors, the flashes), `notify`,
`admin`. A site overrides a message in its own
`translations/blog+intl-icu.<locale>.yaml`.
