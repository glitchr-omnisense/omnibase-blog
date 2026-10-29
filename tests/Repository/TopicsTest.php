<?php

namespace Tests\Base\Blog\Repository;

use Base\Blog\Entity\Post;
use Base\Entity\Thread\Tag;
use Doctrine\ORM\EntityManagerInterface;
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
        $em = $this->em();
        foreach ([1, 2] as $_) { // a thread removed once is in the bin (Trasheable), removed again it is gone
            $em->clear();
            if ($em->getFilters()->isEnabled('trash_filter')) {
                $em->getFilters()->disable('trash_filter');
            }
            foreach (array_reverse($this->created) as [$class, $id]) {
                if (null !== $entity = $em->find($class, $id)) {
                    $em->remove($entity);
                    $em->flush();
                }
            }
        }
        $this->created = [];
        parent::tearDown();
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
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

        $slugs = array_map(static fn (Tag $t) => $t->getSlug(), $em->getRepository(Post::class)->findTopics());
        self::assertContains($now->getSlug(), $slugs);
        self::assertNotContains($later->getSlug(), $slugs, 'scheduled for later: not on the site yet');
    }
}
