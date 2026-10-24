<?php

namespace Base\Blog\Screen;

use Base\Blog\Entity\Post;
use Base\Blog\Repository\PostRepository;
use Base\Blog\Service\CommentPolicy;
use Base\Blog\Service\JsonLd;
use Base\Repository\Thread\CommentRepository;
use Base\Screen\Builder\Stories;
use Base\Screen\Card;
use Base\Screen\ContextProvider;
use Base\Screen\Discussion;
use Base\Screen\Shape;
use Omnibridge\Contract\Urls;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The blog's screens (plan "omnibase agnostique", C5): its pages as view
 * models, from its repositories, omnibase's Stories builder, the Urls
 * contract and the request's context. What its controllers hand to their
 * templates; the Twig functions blog_card() and blog_discussion() give a host
 * page the same models from its own entities.
 *
 * Covers at the width each place shows them: a card 800, the headline 1200,
 * a post 1600.
 */
class BlogScreens
{
    public const CARD = 800;
    public const HEADLINE = 1200;
    public const POST = 1600;

    public function __construct(
        private readonly PostRepository $posts,
        private readonly CommentRepository $comments,
        private readonly Stories $stories,
        private readonly Urls $urls,
        private readonly ContextProvider $context,
        private readonly CommentPolicy $policy,
        #[Autowire('%blog.posts_per_page%')] private readonly int $perPage = 12,
        private readonly ?JsonLd $jsonLd = null,
    ) {
    }

    /** A page of the list, of a topic (a tag's slug) or a taxon's when one is asked. */
    public function index(int $page, ?string $topic = null, ?string $taxon = null): BlogPage
    {
        $page = max(1, $page);
        $posts = $this->posts->findPublishedPage($page, $this->perPage, $topic, $taxon);
        $headline = 1 === $page && !$topic && !$taxon ? $this->posts->findHeadline() : null;
        $found = $this->posts->countPublished($topic, $taxon);
        $pages = max(1, (int) ceil($found / $this->perPage));
        $at = fn (int $page): string => $this->urls->path('blog_index', ['page' => $page > 1 ? $page : null, 'sujet' => $topic, 'classe' => $taxon]);

        return new BlogPage(
            page: $page,
            pages: $pages,
            found: $found,
            next: $page < $pages ? $this->urls->path('blog_index', ['page' => $page + 1, 'sujet' => $topic, 'classe' => $taxon]) : null,
            articles: array_map(fn (Post $post) => $this->card($post), array_values(array_filter($posts, fn ($post) => $post !== $headline))),
            prev: $page > 1 ? $at($page - 1) : null,
            headline: $headline ? $this->card($headline, self::HEADLINE) : null,
            topics: $this->topics($topic),
            topic: $topic,
            taxon: $taxon,
            months: $this->months(),
            indexUrl: $this->urls->path('blog_index'),
            feedUrl: $this->urls->path('blog_feed'),
        );
    }

    /** The posts of one month. */
    public function month(int $year, int $month): BlogPage
    {
        $posts = $this->posts->findByMonth($year, $month);

        return new BlogPage(
            page: 1,
            pages: 1,
            found: \count($posts),
            next: null,
            articles: array_map(fn (Post $post) => $this->card($post), $posts),
            topics: $this->topics(null),
            month: Shape::moment(new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month))),
            months: $this->months(),
            indexUrl: $this->urls->path('blog_index'),
            feedUrl: $this->urls->path('blog_feed'),
        );
    }

    /**
     * A post, its neighbours and its comments; $base the site's scheme and
     * host for schema.org's description, which only the page asked whole has
     * (null: none, as on the page shown again with a form's errors).
     */
    public function post(Post $post, ?string $base = null): PostPage
    {
        [$older, $newer] = $this->posts->findNeighbours($post);
        $url = fn (Post $post): string => $this->urls->path('blog_post', ['slug' => $post->getSlug()]);
        $discussion = $this->discussion($post);

        return new PostPage(
            article: $this->stories->story(
                $post,
                $url($post),
                $this->tagUrl(...),
                $this->taxonUrl(...),
                cover: $post->hasCover() ? (string) $post->getCover() : null,
                coverWidth: self::POST,
                readingMinutes: $post->getReadingMinutes(),
                commentable: $post->isCommentsOpen(),
            ),
            prev: $this->stories->link($older, $older ? $url($older) : null),
            next: $this->stories->link($newer, $newer ? $url($newer) : null),
            discussion: $discussion,
            jsonLd: null === $base ? null : $this->jsonLd?->article($post, $this->urls->url('blog_post', ['slug' => $post->getSlug()]), $base),
        );
    }

    /** The visitors' book. */
    public function book(): BookPage
    {
        return new BookPage($this->discussion(null));
    }

    /** The feed: the last twenty posts, by their absolute addresses; $host the host it is asked at, the channel's title for a site without a name. */
    public function feed(string $host): FeedPage
    {
        return new FeedPage(
            title: $this->context->frame()->site->title ?? $host,
            indexUrl: $this->urls->url('blog_index'),
            feedUrl: $this->urls->url('blog_feed'),
            items: array_map(fn (Post $post) => $this->stories->card($post, $this->urls->url('blog_post', ['slug' => $post->getSlug()]), readingMinutes: $post->getReadingMinutes()), $this->posts->findPublishedPage(1, 20)),
        );
    }

    /** A post on a list, its cover at $width. */
    public function card(Post $post, int $width = self::CARD): Card
    {
        return $this->stories->card(
            $post,
            $this->urls->path('blog_post', ['slug' => $post->getSlug()]),
            $this->tagUrl(...),
            $this->taxonUrl(...),
            cover: $post->hasCover() ? (string) $post->getCover() : null,
            coverWidth: $width,
            readingMinutes: $post->getReadingMinutes(),
        );
    }

    /**
     * The comments of a post, or of the visitors' book (null), and whether one
     * may write; the comments given (a host's own list), else those online.
     * The messages left for the page are read (and so taken) here.
     *
     * @param iterable<\Base\Entity\Thread\Comment>|null $comments
     */
    public function discussion(?Post $post, ?iterable $comments = null): Discussion
    {
        $signedIn = null !== $this->context->viewer();
        try {
            $signIn = $this->urls->path('security_login');
        } catch (\Throwable) {
            $signIn = null;
        }

        return new Discussion(
            comments: $this->stories->comments($comments ?? $this->comments->findVisible($post)),
            open: $this->policy->isOpen($post, $signedIn),
            canReply: $this->policy->canReply($signedIn),
            signIn: $signIn,
            flashes: $this->context->flashes('blog'),
        );
    }

    /** @return list<\Base\Screen\Tag> */
    private function topics(?string $current): array
    {
        return $this->stories->tags($this->posts->findTopics(), $this->tagUrl(...), $current, true);
    }

    /** @return list<array{year: int, months: list<array{month: int, count: int, url: string}>}> newest first */
    private function months(): array
    {
        $years = [];
        foreach ($this->posts->countByMonth() as $year => $byMonth) {
            krsort($byMonth);
            $months = [];
            foreach ($byMonth as $month => $count) {
                $months[] = ['month' => (int) $month, 'count' => (int) $count, 'url' => $this->urls->path('blog_month', ['year' => $year, 'month' => $month])];
            }
            $years[] = ['year' => (int) $year, 'months' => $months];
        }

        return $years;
    }

    private function tagUrl(object $tag): string
    {
        return $this->urls->path('blog_index', ['sujet' => $tag->getSlug()]);
    }

    private function taxonUrl(object $taxon): string
    {
        return $this->urls->path('blog_index', ['classe' => $taxon->getSlug()]);
    }
}
