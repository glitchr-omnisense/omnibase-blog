<?php

namespace Tests\Base\Blog\Repository;

use Base\Blog\Entity\Post;
use Base\Blog\Repository\PostRepository;
use Base\Entity\Thread\Tag;
use Base\Contract\Backend;
use Base\Contract\UnitOfWork;
use Base\Database\Attribute\Backend\Backends;
use Base\Database\Repository\Repositories;
use Base\Database\Scope\TrashScope;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * findTopics() lists the topics of the posts on the site: published, and their publication date
 * come, as every other list of the blog. It read the state alone, so the tag of a post scheduled
 * for later was listed, and its topic page was empty until then.
 */
final class TopicsTest extends KernelTestCase
{
    /** @var list<array{class-string, int}> */
    private array $created = [];

    protected function setUp(): void
    {
        $_SERVER['KERNEL_CLASS'] ??= $_ENV['KERNEL_CLASS'] ?? 'App\\Kernel';
        if (!class_exists($_SERVER['KERNEL_CLASS']) || !class_exists('App\\Entity\\User')) {
            self::markTestSkipped('Needs a host application (its kernel and its User).');
        }
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        if (!class_exists($_SERVER['KERNEL_CLASS']) || !class_exists('App\\Entity\\User')) {
            parent::tearDown(); // skipped in setUp(): no kernel to clean up after

            return;
        }
        $em = $this->em();
        $repositories = static::getContainer()->get(Repositories::class);
        foreach ([1, 2] as $_) { // a thread removed once is in the bin (Trasheable), removed again it is gone
            $repositories->of(Post::class)->clear();
            $backend = Backends::of($em, Backend::class);
            if ($backend->isScopeEnabled(TrashScope::NAME)) {
                $backend->disableScope(TrashScope::NAME);
            }
            foreach (array_reverse($this->created) as [$class, $id]) {
                if (null !== $entity = $repositories->of($class)->find($id)) {
                    $em->remove($entity);
                    $em->flush();
                }
            }
        }
        $this->created = [];
        parent::tearDown();
    }

    private function em(): UnitOfWork
    {
        return static::getContainer()->get(UnitOfWork::class);
    }

    public function testAScheduledPostsTopicIsNotListedYet(): void
    {
        $em = $this->em();
        $class = 'App\\Entity\\User';
        $author = new $class();
        $handle = 'topics.'.bin2hex(random_bytes(3));
        if (method_exists($author, 'setUsername')) {
            $author->setUsername($handle);
        }
        $author->setEmail($handle.'@example.org');
        $author->setPlainPassword('test-'.$handle.'-password');
        $author->setRoles(['ROLE_ADMIN']);
        $em->persist($author);
        $now = new Tag('Maintenant', 'maintenant-'.bin2hex(random_bytes(3)));
        $later = new Tag('Plus tard', 'plus-tard-'.bin2hex(random_bytes(3)));
        $em->persist($now);
        $em->persist($later);
        $em->flush();
        $this->created[] = [$class, $author->getId()];
        $this->created[] = [Tag::class, $now->getId()];
        $this->created[] = [Tag::class, $later->getId()];

        foreach ([[$now, '-1 day'], [$later, '+10 days']] as $i => [$tag, $when]) {
            $post = new Post($author, null, 'Chronique '.$i, 'topics-'.$i.'-'.bin2hex(random_bytes(3)));
            $post->publish(true, new \DateTime($when));
            $post->addTag($tag);
            $em->persist($post);
            $em->flush();
            $this->created[] = [Post::class, $post->getId()];
        }

        $slugs = array_map(static fn (Tag $t) => $t->getSlug(), static::getContainer()->get(PostRepository::class)->findTopics());
        self::assertContains($now->getSlug(), $slugs);
        self::assertNotContains($later->getSlug(), $slugs, 'scheduled for later: not on the site yet');
    }
}
