---
title: "Comments"
order: 32
---

# Comments

The blog's comments are omnisense/omnibase's `Base\Entity\Thread\Comment` (table
`threadComment`): a post is a thread, so a comment under a post has the post as
its `thread`; a comment with no thread belongs to the visitors' book
(`/livre-d-or`). The bundle no longer has a Comment entity, enum, form,
repository or guard of its own - it uses the core's:

| Was | Is |
|---|---|
| `Base\Blog\Entity\Comment` (`blog_comment`, `post`) | `Base\Entity\Thread\Comment` (`threadComment`, `thread`) |
| `Base\Blog\Enum\CommentState` | `Base\Enum\CommentState` |
| `Base\Blog\Form\CommentType`, `Form\Model\CommentModel` | `Base\Form\Type\CommentType` (options `placeholders`, `trap_class`), `Base\Form\Model\CommentModel` |
| `Base\Blog\Repository\CommentRepository` | `Base\Repository\Thread\CommentRepository` |
| `Base\Blog\Service\CommentGuard` | `Base\Service\CommentGuard` (the blog passes its `min_delay` and `flood_interval`) |
| `Post::getComments()`, `getVisibleComments()` | `blog_comments(post)`, `blog_comments_count(post)` in Twig, or the repository |

The guard is the core's: `CommentType`'s own trap (`url`) and time (`opened`),
read by `Base\Service\CommentGuard` with the blog's `min_delay` and
`flood_interval`, and the forms' guard of omnisense/omnibase on top - its stamp,
the lists, the captcha when the site has omnisense/omnishield (printed above the
button). A comment form built by hand in a test posts the guard's signed stamp
(`FormGuard::stamp()`) and, with omnishield's `fixed` test gateway,
`omnishield-token=omnishield-fixed-token`.

What stays the blog's: the routes (`blog_book`, `blog_book_comment`,
`blog_post_comment`), the configuration (`blog.comments.*`), the moderation
screen (`CommentCrudController`, `/admin/comments`, filter `thread`), the
pending-comments tile (`blog_pending_comments`), the notifications and the
Akismet reports.

## Moving a site

A site that had the blog before: its migration copies `blog_comment` into
`threadComment` (`post_id` becomes `thread_id`, the discriminator `comment`) and
drops `blog_comment`. Apfelschule's `migrations/Version20261004090000.php` is the
model. In the site's code: `Base\Blog\Entity\Comment` → `Base\Entity\Thread\Comment`,
`Base\Blog\Enum\CommentState` → `Base\Enum\CommentState`.

## Who may write

`Base\Blog\Service\CommentPolicy` reads `blog.comments.*`:

| Question | Answer |
|---|---|
| may one comment here? | comments are `enabled`, the visitor is signed in or `guest_allowed` is on, and the post keeps its comments open (`Post::isCommentsOpen()`); the visitors' book has no post to close |
| may one answer a comment? | the visitor is signed in, or `guest_replies` is on |

A post with closed comments refuses a comment sent anyway with a 403; closed
comments everywhere (or no account when one is needed) refuse it the same
way. A reply sent by a visitor who may not reply is refused on the form
(`@blog.comment.error.reply_signed_in`), and a reply to a comment that is not
visible, or not under the same post, is kept as a comment of its own.

## What happens to a comment

`CommentController` (`blog_post_comment`, `blog_book_comment`) handles the
form in this order:

1. the guard (`Base\Service\CommentGuard`, with `min_delay` and
   `flood_interval`): a robot caught in the trap is thanked as if its comment
   waited (`comment.flash.pending`) and nothing is kept; a comment sent too
   fast or too soon after the last one from the same address gets the error
   `@blog.comment.error.<reason>`;
2. the form's validation, with omnisense/omnibase's spam extension: the
   comment is classified (Akismet with the site's key, `base.spam.akismet`),
   a blatant spam refused, a doubtful one kept as pending;
3. the comment is saved with the visitor's address and browser, online at
   once when `auto_approve` is on and the classifier found it clean, pending
   otherwise;
4. the administrators are told ([Notifications](#notifications));
5. the visitor goes back to the page: at `#commentaire-<id>` with
   `comment.flash.published`, or at `#commentaires` with
   `comment.flash.pending`.

An invalid form shows the post (or the book) again with its errors, status
422. The flashes are in the `blog` category; the page's view model reads
them.

## Notifications

`Base\Blog\Service\CommentNotifier` sends every user whose roles reach
`ROLE_ADMIN` a notification in omnisense/omnibase's notification centre (the
`notify` channel, the bell): `notify.pending` or `notify.approved` (domain
`blog`, with the visitor's name and the post's title, or the book's), the
first 240 characters of the comment, and a link to the comments screen
filtered on the comment's state. No e-mail is sent.

## Teaching the classifier

`Base\Blog\Service\AkismetReporter` tells the classifier when a moderator
corrects it: approving a comment held as spam, or one with a spam score,
reports it as ham; marking as spam a comment that was online, or one with no
score, reports it as spam. It sends what the visitor sent (text, author,
address, browser, website). Without omnisense/omnishield nothing is sent; a
provider's error is logged, not shown to the moderator.
