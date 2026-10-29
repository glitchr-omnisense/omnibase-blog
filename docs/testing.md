---
title: "Testing"
order: 60
---

# Testing

## Running the suite

From the package's checkout, with its dev dependencies:

```bash
vendor/bin/phpunit
```

Inside a host application:

```bash
php vendor/bin/phpunit -c vendor/omnibase/blog/phpunit.xml.dist
```

In omnisense/omnibase-harness, mounting the checkout in place of the
installed package:

```bash
docker run --rm --network none \
    -v "$PWD":/srv/demo/vendor/omnibase/blog:ro \
    omnisense/omnibase-harness:latest test blog
```

On this checkout, in the harness:

```text
Tests: 31, Assertions: 177, Failures: 1, Skipped: 1.
```

The skipped test is the captcha's: the harness asks the captcha only after
refused tries (`base.guard.captcha_after`). The failure is the snapshot test
([below](#the-snapshots)).

## What the tests check

| Test | Checks |
|---|---|
| `Controller\Admin\OpenToAdminsTest` | the administrator writes in both screens; anyone else is refused (needs a host kernel) |
| `Controller\CommentGuardTest` | a comment written as a person goes through; a filled trap is a robot; a comment sent faster than `min_delay` is refused; a comment without the captcha's token is refused, when the host has a captcha |
| `Controller\CrudRequiredFieldsTest` | omnibase/admin's `CrudFieldsTestCase` on the two screens |
| `DependencyInjection\ConfigurationTest` | a site that says nothing keeps `chroniques`; a site's own path; a path with slashes around is refused |
| `Screen\BlogPagesSnapshotTest` | the pages equal to the recorded HTML; the screens given as data |
| `Service\AkismetReporterTest` | a spam let through is reported as the visitor sent it; without a classifier nothing is sent |
| `Service\JsonLdTest` | a post is a `BlogPosting`; a post without cover nor author keeps to what it knows; a long title is cut; the cover's address |
| `WordPress\ImporterTest` | on a recorded excerpt of a real site's API: the client follows every page, the HTML is cleaned, the links follow what was imported, a second run doubles nothing, no media means no cover, an unknown target is refused |

## The snapshots

`Screen\BlogPagesSnapshotTest` renders the pages through the host's kernel
(the list and its variants, a post seen by a visitor and by a member, the
feed, the book, a refused comment shown again with status 422, the partials
a host page includes) and compares them with `tests/Screen/snapshots/*.html`.
Every date is fixed; the CSRF token, the form's opening time and the guard's
stamp are set aside. A missing snapshot is written and the test marked
incomplete; `BLOG_SNAPSHOT_RECORD=1` writes them all again.

The host's built core assets (`/bundles/base/<entry>.<hash>.css|js`: the `base-async`,
`base-defer`, `form-defer` entry points) are set aside on both sides: they are printed only
where the host built them, and their hash changes at each build.
