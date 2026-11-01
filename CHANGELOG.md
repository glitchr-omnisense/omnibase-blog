# Changelog

## [Unreleased]

### Changed
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
