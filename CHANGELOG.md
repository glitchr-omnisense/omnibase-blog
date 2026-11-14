# Changelog

## [Unreleased]

## [1.0.1] - 2026-11-14

### Changed

- The classes omnisense/omnibase 5.0 moved to omnibridge/symfony named by their new names:
  `Omnibridge\Symfony\Omnibase\Bundle\{AbstractBaseBundle, AbstractBaseExtension, AbstractBaseConfiguration}`
  in place of `Base\Bundle\*`, former names the core keeps only as aliases until 6.0. No behaviour change.
- BlogPagesSnapshotTest probes `Omnibridge\Symfony\Omnibase\ScreenResponder`. Kept: `Base\Bridge\Symfony\ScreenResponder`
  in BlogController and CommentController, an optional autowired service that omnibridge/symfony registers under
  its former id only (its new name would be autowired null).

## [1.0.0] - 2026-11-13

### Changed

- Installs with the default minimum-stability: stable requirements (`omnisense/omnibase` ^5.1, `omnibridge/symfony` ^1.1, `omnibase/admin` ^1.0), a branch alias,
  no `minimum-stability: dev` left to the sites.
- CI: glitchr-studio/ci's package workflow (the bundle installed alone, composer validate, PHPUnit,
  PHPStan against its baseline, a consumer install without require-dev) and its release workflow.
- `doctrine/doctrine-bundle` required: the repository extends its `ServiceEntityRepository`.
- Neutral example data in the tests (an invented WordPress recording) and a comment.
- Two kernel tests skipped without a host no longer fail in their tearDown.

### Changed
- Accepts omnisense/omnibase 5.0 (`3.0.*-dev || 4.0.*-dev || 5.0.*-dev`; plan "omnibase agnostic", step S): the bundle runs
  unchanged on it, `Base\Bundle\AbstractBaseBundle` and the other former names it uses being aliases of
  omnibridge/symfony's classes there. The sites on 4.x resolve as before.
- The comments are asked through the core's interface `Base\Repository\Thread\Comments`, not the class
  `CommentRepository` (BlogController, CommentController, BlogScreens, BlogExtension, PendingCommentsWidgetType; omnibase's
  plan "omnibase agnostic", step E2'). omnibase 5.0's interface declares `findVisible()`, `countVisible()`,
  `countPending()` and `findPending()`; 4.x's has the interface, the container's alias of it and the repository with those
  methods, which is what the container hands out, so the 4.x sites change nothing.
- `Post::__toLink()` link through `Base\Contract\Link::to()` (the Urls contract; the same address under
  Symfony, in the core 3.x and 4.x) instead of BaseTrait's `getRouter()->generate()`, which the
  core deprecates since BaseTrait names contracts only (omnisense/omnibase 4.x, step F2f).

### Fixed
- `PostRepository::findTopics()` lists the topics of the posts on the site: published and their
  publication date come. It read the state alone, so the tag of a post scheduled for later was
  listed, and its topic page was empty until then.
- The pages' snapshot test sets the host's built core assets aside (`/bundles/base/<entry>.<hash>`
  stylesheets and scripts), printed only where the host built them and renamed at each build: it
  failed in a host without them, or after a rebuild.
