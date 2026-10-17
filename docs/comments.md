# Comments

The blog's comments are omniscient/omnibase's `Base\Entity\Thread\Comment` (table
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
`flood_interval`, and the forms' guard of omniscient/omnibase on top - its stamp,
the lists, the captcha when the site has glitchr/omnishield (printed above the
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
