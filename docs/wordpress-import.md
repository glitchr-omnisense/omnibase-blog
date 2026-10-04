# Moving a WordPress site in: `blog:import-wordpress`

```bash
bin/console blog:import-wordpress https://example.org
bin/console blog:import-wordpress https://example.org --map about-me/biography=bio --map contact=skip --update
bin/console blog:import-wordpress https://example.org --map var/wordpress-map.json --author=admin
bin/console blog:import-wordpress https://example.org --targets      # the targets this site has
```

It reads the site's REST API (`wp-json/wp/v2`, open on any public WordPress:
no key) - every page of `posts`, `pages`, `media`, `categories`, `tags` -
and for each post and page:

1. **cleans its HTML**: the scripts and styles plugins add (share buttons,
   forms that need their JavaScript), unexpanded shortcodes, empty
   paragraphs, the theme's classes, inline sizes and `srcset`;
2. **copies its pictures** into the uploads (`local.uploads`, under
   `--uploads`, `wordpress/` by default: `/uploads/wordpress/2014/05/book-a.jpg`) -
   what is there already is left; `--no-media` leaves them on the old site;
3. **rewrites its links**: a picture to its copy (a thumbnail `-300x225` to
   the full picture), a post or a page to its new address - by permalink,
   by `?p=123`, by `?page_id=123`, a dated permalink by its slug -, the old
   home page to `/`; a page that was not imported keeps its old address;
4. **sends it to its target**.

## Targets and the map

| Target | Of omnibase/blog | |
|---|---|---|
| `post` | yes | an omnibase/blog `Post` under the same slug: title, excerpt, text, its featured picture as its cover, published at its date (a draft stays one), its categories and tags as tags |
| `skip` | yes | left behind |
| anything else | the site's | a class implementing `Base\Blog\WordPress\TargetInterface` |

By default a post goes to `post`, a page to `page` when the site declares a
target of that name, else to `skip`. The map says otherwise, for one item - by
its slug or its path (`about-me/biography`) - or for all of a kind (`*post`,
`*page`): `--map slug=target`, repeatable, or a JSON file
(`{"about-me/biography": "bio", "schedule-2016": "agenda:2016", "*page": "page"}`).
What follows the colon is handed to the target (`$context->option($item)`).

```php
namespace App\Import;

use Base\Blog\WordPress\{Context, Item, Result, TargetInterface};

final class BiographyTarget implements TargetInterface      // autoconfigured: tag blog.wordpress_target
{
    public function getName(): string { return 'bio'; }

    public function url(Item $item): ?string { return '/biography'; }   // before anything is written: the links follow

    public function import(Item $item, Context $context): string
    {
        $bio = $this->bios->findOneFor(...);
        if ($bio && !$context->update) {
            return Result::KEPT;
        }
        // $item->title, ->content (cleaned, links rewritten), ->text(), ->excerpt, ->date, ->categories...
        $this->em->persist($bio);                                      // persist; the importer flushes

        return $bio->getId() ? Result::UPDATED : Result::CREATED;
    }
}
```

## The cover

A post's featured picture (`featured_media`) becomes its cover: the importer
gives every item the address of that picture's copy (`$item->cover`,
`/uploads/wordpress/2014/05/a.jpg`; `$item->featuredMedia` is its id on the
old site), and the `post` target uploads it as `Post::$cover` - once: a cover
set since, by hand or by an earlier run, is kept. With `--no-media` there is
no copy, so no cover. A site's own target reads `$item->cover` the same way.

## A slug already taken

omnibase keeps a thread's slug unique whatever its kind: a post named like a
product or a page cannot have that slug. The `post` target then takes the
slug with the post's number on the old site (`trophees-publicitaires-2170`),
the same at every run, so the post is found again and never doubled.

## Run it again

Without `--update`, what an earlier run imported is left alone (`kept`);
with it, brought up to date. Either way nothing is doubled: a target finds
its record again (a post by its slug). The summary counts, per target, what
was created, updated, kept and skipped, and the pictures copied.

## In code

```php
$result = $importer->import('https://example.org', ['contact' => 'skip'], update: true);
$items = $importer->read('https://example.org');   // the posts and pages, cleaned, not imported
```

`Base\Blog\WordPress\Client` reads the API alone (`all($site, 'pages')`).
