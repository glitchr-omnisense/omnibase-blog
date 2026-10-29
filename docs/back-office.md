---
title: "The back office"
order: 36
---

# The back office

The screens are loaded when omnibase/admin is installed. Both carry
omnibase/admin's `#[OpenToAdmins]`: the site's administrator (`ROLE_ADMIN`: a
teacher, an editor) writes in them, not the super-admin only.

## Posts

`Base\Blog\Controller\Admin\Crud\PostCrudController` creates, edits and
deletes a post: title, state, slug (following the title), publication date,
featured, comments open, headline, excerpt (with a help text), content (the
editor, with collaborative autosave and live editing), tags, taxa and cover.

A post published with a date to come waits for it ([Posts](concepts.md)).

## Comments

`Base\Blog\Controller\Admin\Crud\CommentCrudController` moderates the
comments (omnisense/omnibase's `Base\Entity\Thread\Comment`, every thread's).
There is no "new" action: comments come from the visitors. Fields: state,
name, e-mail, website, post (`thread`), parent, content, date; the address and
the spam score on the detail page. Filters: state, post.

Three actions, on the index and on the detail page, open to the
administrator (`#[OpenToAdmins(actions: ['approve', 'spam', 'trash'])]`):

| Action | Path under the screen | Does | The classifier is told |
|---|---|---|---|
| `approve` | `/{entityId}/approve` | its state becomes approved: online | ham, when it was held as spam or had a spam score |
| `spam` | `/{entityId}/spam` | its state becomes spam | spam, when it was online or had no score |
| `trash` | `/{entityId}/trash` | its state becomes trash | nothing |

Each flashes `@blog.admin.comment.flash.approved`, `.spam` or `.trashed` and
returns to the index. The notifications of a new comment link to this screen,
filtered on the comment's state ([Comments](comments.md)).

## The dashboard tile

`Base\Blog\Admin\Widget\PendingCommentsWidgetType` is the widget
`blog_pending_comments`: how many comments wait (`countPending()`) and the
five latest (`findPending(5)`), rendered by
`@Blog/admin/widget/pending_comments.html.twig`.

```php
yield MenuItem::block('blog_pending_comments', 'Commentaires en attente', 'fa-solid fa-comments')->setSize(2);
yield MenuItem::linkToCrud(\Base\Blog\Entity\Post::class, 'Chroniques', 'fa-solid fa-newspaper');
yield MenuItem::linkToCrud(\Base\Entity\Thread\Comment::class, 'Commentaires', 'fa-solid fa-comments');
```
