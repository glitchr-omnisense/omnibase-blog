<?php

namespace Base\Blog\Repository;

use Base\Blog\Entity\Post;
use Base\Entity\Thread\Tag;
use Base\Enum\ThreadState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Post> */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * A page of the published posts, newest first - of one topic when a tag
     * slug is given, of one taxon (a level, a subject) when a taxon slug is.
     * Paged by hand: Doctrine's Paginator walks the SQL with an output walker
     * omnibase's own SqlWalker is incompatible with.
     *
     * @return list<Post>
     */
    public function findPublishedPage(int $page = 1, int $perPage = 10, ?string $tag = null, ?string $taxon = null): array
    {
        return $this->filtered($this->published(), $tag, $taxon)
            ->orderBy('p.publishedAt', 'DESC')->addOrderBy('p.id', 'DESC')
            ->setFirstResult(max(0, $page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()->getResult();
    }

    public function countPublished(?string $tag = null, ?string $taxon = null): int
    {
        return (int) $this->filtered($this->published(), $tag, $taxon)
            ->select('COUNT(DISTINCT p.id)')
            ->getQuery()->getSingleScalarResult();
    }

    /** @return list<Post> every published post, newest first: for a sitemap */
    public function findAllPublished(): array
    {
        return $this->published()
            ->orderBy('p.publishedAt', 'DESC')->addOrderBy('p.id', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return list<Post> */
    public function findLatest(int $limit = 3): array
    {
        return $this->findPublishedPage(1, $limit);
    }

    /** The featured post, or the newest one. */
    public function findHeadline(): ?Post
    {
        return $this->published()
            ->orderBy('p.featured', 'DESC')->addOrderBy('p.publishedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    public function findOnePublished(string $slug): ?Post
    {
        return $this->published()
            ->andWhere('p.slug = :slug')->setParameter('slug', $slug)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return array{0: ?Post, 1: ?Post} the older one, the newer one */
    public function findNeighbours(Post $post): array
    {
        $older = $this->published()
            ->andWhere('p.publishedAt < :at')->setParameter('at', $post->getPublishedAt())
            ->orderBy('p.publishedAt', 'DESC')->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
        $newer = $this->published()
            ->andWhere('p.publishedAt > :at')->setParameter('at', $post->getPublishedAt())
            ->orderBy('p.publishedAt', 'ASC')->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        return [$older, $newer];
    }

    /** @return list<Tag> the topics of the published posts */
    public function findTopics(): array
    {
        return $this->getEntityManager()->getRepository(Tag::class)->createQueryBuilder('t')
            ->innerJoin('t.threads', 'p')
            ->andWhere('p INSTANCE OF :class')->setParameter('class', $this->getClassMetadata()->discriminatorValue)
            ->andWhere('p.state = :published')->setParameter('published', ThreadState::PUBLISH)
            ->groupBy('t.id')
            ->orderBy('t.priority', 'DESC')->addOrderBy('t.slug', 'ASC')
            ->getQuery()->getResult();
    }

    /** The published posts, by year then month, for the chronicle's archive. @return array<int, array<int, int>> year => month => count */
    public function countByMonth(): array
    {
        $rows = $this->published()
            ->select('p.publishedAt AS at')
            ->getQuery()->getArrayResult();
        $months = [];
        foreach ($rows as $row) {
            $at = $row['at'] instanceof \DateTimeInterface ? $row['at'] : new \DateTime((string) $row['at']);
            $months[(int) $at->format('Y')][(int) $at->format('n')] = ($months[(int) $at->format('Y')][(int) $at->format('n')] ?? 0) + 1;
        }
        krsort($months);

        return $months;
    }

    /** A page of the posts of one month. @return list<Post> */
    public function findByMonth(int $year, int $month): array
    {
        $from = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));

        return $this->published()
            ->andWhere('p.publishedAt >= :from AND p.publishedAt < :to')
            ->setParameter('from', $from)->setParameter('to', $from->modify('+1 month'))
            ->orderBy('p.publishedAt', 'DESC')
            ->getQuery()->getResult();
    }

    private function filtered(QueryBuilder $query, ?string $tag, ?string $taxon): QueryBuilder
    {
        if ($tag) {
            $query->innerJoin('p.tags', 't')->andWhere('t.slug = :tag')->setParameter('tag', $tag);
        }
        if ($taxon) {
            $query->innerJoin('p.taxa', 'x')->andWhere('x.slug = :taxon')->setParameter('taxon', $taxon);
        }

        return $query;
    }

    private function published(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.state = :published')->setParameter('published', ThreadState::PUBLISH)
            ->andWhere('p.publishedAt IS NULL OR p.publishedAt <= CURRENT_TIMESTAMP()');
    }
}
