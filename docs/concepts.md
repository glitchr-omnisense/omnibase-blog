---
title: "Posts"
order: 20
---

# Posts

## A post is a thread

`Base\Blog\Entity\Post` extends omnisense/omnibase's `Base\Entity\Thread`
(table `blog_post`, discriminator `blog_post`). From the thread it has:

- its texts, translatable: `title`, `headline`, `excerpt`, `content` (EditorJS
  JSON or HTML);
- its `slug`, its `owners` (the authors), its `tags` (the topics) and `taxa`
  (what the site classifies with: a level, a subject);
- its `state` (`ThreadState`: draft, published...), `publishedAt`, its
  revisions, followers, likes and soft delete.

It adds three fields:

| Field | Column | Meaning |
|---|---|---|
| `cover` | text, nullable | an image (8 MB at most, `image/*`), stored by omnisense/omnibase's Uploader |
| `featured` | boolean | the post the first page heads with |
| `commentsOpen` | boolean, default true | whether visitors comment under it |

## Methods

| Method | Returns |
|---|---|
| `getCover()` | the cover's stored public path (in Twig, `post.cover` is that path, drawn with `|picture`) |
| `getCoverFile()` | the cover as a file |
| `hasCover()` | whether a cover is set, without asking the storage |
| `getCoverUrl()` | the cover's address on the site (`/uploads/...`); an absolute URL as it is |
| `getReadingMinutes()` | minutes to read it at 220 words a minute (`WORDS_PER_MINUTE`), rounded up, one at least; the words of an EditorJS text are read from its blocks |
| `publish(bool $published = true, ?DateTimeInterface $at = null)` | sets the state to published (or draft) and the publication date to `$at`, the date it had, or now |

## Published

A post is on the site when its state is published and its publication date
is empty or past: a post published with a date to come stays off every list,
page, feed and sitemap until then (`PostRepository::published()`).

## The repository

| Method | Returns |
|---|---|
| `findPublishedPage($page, $perPage, $tag, $taxon)` | a page of the published posts, newest first, of one tag or one taxon (by slug) when given |
| `countPublished($tag, $taxon)` | how many |
| `findAllPublished()` | every published post, for the sitemap |
| `findLatest($limit = 3)` | the newest posts |
| `findHeadline()` | the featured post, else the newest |
| `findOnePublished($slug)` | one published post |
| `findNeighbours($post)` | the older and the newer post |
| `findTopics()` | the tags of the published posts, by priority then slug |
| `countByMonth()` | year, then month, then the count, for the archive |
| `findByMonth($year, $month)` | the posts of a month |

> [!WARNING]
> Known defect: `findTopics()` reads the state only, not the publication
> date: the tag of a post scheduled for later is listed among the topics, and
> its topic page is empty until the post comes out.

Pages are cut by hand (first result, max results), not with Doctrine's
paginator, whose output walker does not work with omnisense/omnibase's SQL
walker.

## JSON-LD

`Base\Blog\Service\JsonLd::article($post, $url, $base)` returns schema.org's
`BlogPosting`: `headline` (the title, cut at 110 characters), `url`,
`mainEntityOfPage`, `description` (the excerpt, else the headline, without
tags), `datePublished`, `dateModified`, `image` (the cover, `$base` before a
site path), `author` (a `Person` per owner), the tags as `keywords` (joined
by commas) and `wordCount`, the reading time times 220 (a rounded figure, not
the exact count). The post's page prints it in a
`<script type="application/ld+json">`.
