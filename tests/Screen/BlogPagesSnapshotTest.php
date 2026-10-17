<?php

namespace Base\Blog\Tests\Screen;

use Base\Blog\Entity\Post;
use Base\Entity\Thread\Comment;
use Base\Entity\Thread\Tag;
use Base\Entity\Thread\Taxon;
use Base\Enum\CommentState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The blog's pages, as the host's kernel answers them, equal to the HTML
 * recorded in tests/Screen/snapshots/ before the pages went through view
 * models (plan "omnibase agnostique", C5): the list (first page with its
 * headline, the second page, by topic, by taxon, by month), a post seen by a
 * visitor and by a member (with a message left for the page), the feed, the
 * visitors' book, a comment refused and the post shown again with the form's
 * errors (422), and the partials a host page includes with its own entities
 * (apfelschule's home: `_card.html.twig` over blog_recent(), `_comments.html.twig`
 * with post: null).
 *
 * Every moment is fixed (published, created, updated, the comments' dates), so
 * the pages are the same from one run to the next; the values a page draws
 * anew at each request (the forms' CSRF token, their opening time and the
 * guard's stamp) are set aside. A snapshot missing is written, and the test
 * then says so (incomplete): BLOG_SNAPSHOT_RECORD=1 writes them all again.
 */
final class BlogPagesSnapshotTest extends KernelTestCase
{
    private const SNAPSHOTS = __DIR__.'/snapshots';

    /** @var list<object> what the test wrote, removed after */
    private array $created = [];

    /** @var array<string, int> */
    private array $ids = [];

    protected function setUp(): void
    {
        $_SERVER['KERNEL_CLASS'] ??= $_ENV['KERNEL_CLASS'] ?? 'App\\Kernel';
        if (!class_exists($_SERVER['KERNEL_CLASS']) || !class_exists('App\\Entity\\User')) {
            self::markTestSkipped('Needs a host application (its kernel and its User).');
        }
        self::bootKernel();
        $_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] ??= 'phpunit';
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $connection = $this->em()->getConnection();
        foreach (array_reverse($this->created) as [$class, $id]) {
            $entity = $this->em()->find($class, $id);
            if (null !== $entity) {
                $this->em()->remove($entity);
                $this->em()->flush();
                // Trasheable: a thread removed once is in the bin; removed again, gone.
                if (null !== $this->em()->find($class, $id)) {
                    $this->em()->remove($this->em()->find($class, $id));
                    $this->em()->flush();
                }
            }
        }
        unset($connection);
        parent::tearDown();
    }

    public function testTheBlogsPagesAreThoseRecorded(): void
    {
        $this->seed();
        $member = $this->em()->find('App\\Entity\\User', $this->ids['member']);
        $slug = 'chronique-commentee';

        $pages = [
            'index' => $this->page('/chroniques'),
            'index-page-2' => $this->page('/chroniques?page=2'),
            'index-topic' => $this->page('/chroniques?sujet=sorties'),
            'index-taxon' => $this->page('/chroniques?classe=cycle-3'),
            'month' => $this->page('/chroniques/2026/4'),
            'post' => $this->page('/chroniques/'.$slug),
            'post-member' => $this->page('/chroniques/'.$slug, $member, flashes: ['blog' => ['comment.flash.pending']]),
            'post-html' => $this->page('/chroniques/chronique-en-html'),
            'feed' => $this->page('/chroniques/feed.xml'),
            'book' => $this->page('/livre-d-or'),
            'post-refused' => $this->page('/chroniques/'.$slug.'/commenter', $member, 'POST', ['comment' => ['content' => '', 'parent' => '', 'url' => '', 'opened' => '0']], 422),
            'host-partials' => $this->hostPartials(),
        ];

        $written = [];
        foreach ($pages as $name => $html) {
            $file = self::SNAPSHOTS.'/'.$name.'.html';
            $html = $this->normalize($html);
            if (!is_file($file) || getenv('BLOG_SNAPSHOT_RECORD')) {
                @mkdir(self::SNAPSHOTS, 0777, true);
                file_put_contents($file, $html);
                $written[] = $name;
                continue;
            }
            self::assertSame(file_get_contents($file), $html, sprintf('The page "%s" is not the one recorded (%s).', $name, $file));
        }
        if ($written) {
            self::markTestIncomplete('Snapshots written: '.implode(', ', $written).'.');
        }
    }

    /** The values a page draws anew at each request, set aside; the comments' ids by their names (another suite may have numbered before). */
    private function normalize(string $html): string
    {
        $names = array_flip(array_filter($this->ids, static fn ($id, $name) => str_starts_with($name, 'comment:'), \ARRAY_FILTER_USE_BOTH));
        $html = preg_replace_callback('~(commentaire-|data-blog-reply=")(\d+)~', static fn ($m) => $m[1].($names[(int) $m[2]] ?? $m[2]), $html);
        $html = preg_replace('~(name="[^"]*\[(?:_token|_csrf_token|opened|guard_opened|guard_captcha)\]"[^>]*?value=")[^"]*(")~', '$1*$2', $html);
        $html = preg_replace('~(value=")[^"]*("[^>]*name="[^"]*\[(?:_token|_csrf_token|opened|guard_opened|guard_captcha)\]")~', '$1*$2', $html);

        return $html;
    }

    /**
     * @param array<string, mixed>        $parameters
     * @param array<string, list<string>> $flashes    messages left for the page
     */
    private function page(string $path, ?object $user = null, string $method = 'GET', array $parameters = [], int $status = 200, array $flashes = []): string
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $request = Request::create('https://localhost'.$path, $method, $parameters);
        if ($user || $flashes) {
            $session = static::getContainer()->get('session.factory')->createSession();
            if ($user) {
                $user = $this->em()->find($user::class, $user->getId());
                $session->set('_security_main', serialize(new UsernamePasswordToken($user, 'main', $user->getRoles())));
            }
            foreach ($flashes as $type => $messages) {
                foreach ($messages as $message) {
                    $session->getFlashBag()->add($type, $message);
                }
            }
            $session->save();
            $request->cookies->set($session->getName(), $session->getId());
        }
        $response = static::$kernel->handle($request);
        self::assertSame($status, $response->getStatusCode(), $path.': '.substr((string) $response->getContent(), 0, 2000));

        return (string) $response->getContent();
    }

    /** What a host page includes with its own entities: the last posts' cards, the visitors' book's comments. */
    private function hostPartials(): string
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $request = Request::create('https://localhost/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
        $twig = static::getContainer()->get('twig');

        return $twig->createTemplate(
            "{% for post in blog_recent(2) %}{% include '@Blog/client/_card.html.twig' %}{% endfor %}\n"
            ."{% include '@Blog/client/_comments.html.twig' with {post: null, heading: false} %}"
        )->render([]);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    private function seed(): void
    {
        $em = $this->em();
        $user = 'App\\Entity\\User';
        $author = $this->user('Camille Autrice', ['ROLE_ADMIN']);
        $member = $this->user('Louis Membre', ['ROLE_USER']);
        $this->ids['member'] = $member->getId();

        $sorties = new Tag('Sorties', 'sorties');
        $lecture = new Tag('Lecture', 'lecture');
        $cycle = new Taxon('Cycle 3', 'cycle-3');
        foreach ([$sorties, $lecture, $cycle] as $each) {
            $em->persist($each);
        }
        $em->flush();
        $this->created[] = [Tag::class, $sorties->getId()];
        $this->created[] = [Tag::class, $lecture->getId()];
        $this->created[] = [Taxon::class, $cycle->getId()];

        $editor = json_encode(['time' => 1, 'blocks' => [
            ['id' => 'h1', 'type' => 'header', 'data' => ['text' => 'Le départ à l\'aube', 'level' => 2]],
            ['id' => 'p1', 'type' => 'paragraph', 'data' => ['text' => 'Nous sommes partis à <b>sept heures</b>, les sacs prêts depuis la veille.']],
            ['id' => 'l1', 'type' => 'list', 'data' => ['style' => 'unordered', 'items' => ['Une gourde', 'Un carnet']]],
            ['id' => 'h2', 'type' => 'header', 'data' => ['text' => 'Le retour', 'level' => 2]],
            ['id' => 'p2', 'type' => 'paragraph', 'data' => ['text' => 'Fatigués, contents.']],
        ]]);

        // Fourteen posts: twelve a page, the headline taken out of the first.
        $posts = [];
        for ($i = 1; $i <= 14; ++$i) {
            $slug = match ($i) { 14 => 'chronique-commentee', 13 => 'chronique-en-html', default => sprintf('chronique-%02d', $i) };
            $post = new Post($author, null, match ($i) { 14 => 'La sortie au musée', 13 => 'Une page écrite en HTML', default => sprintf('Chronique numéro %d', $i) }, $slug);
            $post->setExcerpt(1 === $i % 3 ? null : sprintf('Ce qui s\'est passé la semaine %d, en quelques lignes qui donnent envie de lire la suite et dépassent un peu les cent quatre-vingts signes pour que la carte les coupe au bon endroit.', $i));
            $post->setHeadline(14 === $i ? 'Une journée entière au musée des beaux-arts' : null);
            $post->setContent(match ($i) { 13 => '<h2>Un titre en HTML</h2><p>Un paragraphe écrit avec <em>Quill</em>, accentué : é, à, ç.</p>', 12 => null, default => $editor });
            $post->publish(true, new \DateTime(sprintf('2026-%02d-%02d 09:30:00', $i <= 7 ? 3 : 4, ($i % 7) + 2)));
            if (0 === $i % 2) {
                $post->addTag($sorties);
            }
            if (0 === $i % 3) {
                $post->addTag($lecture);
                $post->addTaxon($cycle);
            }
            if (5 === $i) {
                $post->setFeatured(true);
            }
            if (11 === $i) {
                $post->setCommentsOpen(false);
            }
            if (14 === $i) {
                // A cover the storage does not know: the page prints what |picture gives it.
                $post->setCover('/uploads/blog/couverture-introuvable.jpg');
            }
            $em->persist($post);
            $posts[$i] = $post;
        }
        $em->flush();
        foreach ($posts as $post) {
            $this->created[] = [Post::class, $post->getId()];
        }

        $commented = $posts[14];
        $first = (new Comment($commented))->setName('Inès')->setEmail('ines@example.org')->setWebsite('https://ines.example.org')->setContent("Merci pour ce récit !\nOn y retourne quand ?")->approve();
        $staff = (new Comment($commented, $author))->setContent('Bientôt, promis.')->approve();
        $em->persist($first);
        $em->persist($staff);
        $em->flush();
        $reply = (new Comment($commented, $member))->setParent($first)->setContent('Moi aussi je veux y aller.')->approve();
        $hidden = (new Comment($commented))->setName('Robot')->setEmail('robot@example.org')->setContent('Achetez maintenant')->markAsSpam();
        $book = (new Comment(null))->setName('Une collègue')->setEmail('collegue@example.org')->setContent('De passage, bravo pour ce blog.')->approve();
        foreach ([$reply, $hidden, $book] as $each) {
            $em->persist($each);
        }
        $em->flush();
        foreach (['first' => $first, 'staff' => $staff, 'reply' => $reply, 'hidden' => $hidden, 'book' => $book] as $name => $each) {
            $this->created[] = [Comment::class, $each->getId()];
            $this->ids['comment:'.$name] = $each->getId();
        }

        // Every moment fixed: the comments' (written at creation), the threads' own.
        $connection = $em->getConnection();
        $comments = $em->getClassMetadata(Comment::class)->getTableName();
        foreach ([[$first, '2026-04-10 18:05:00'], [$staff, '2026-04-11 08:00:00'], [$reply, '2026-04-10 20:15:00'], [$hidden, '2026-04-12 10:00:00'], [$book, '2026-04-01 12:00:00']] as [$comment, $at]) {
            $connection->update($comments, ['createdAt' => $at, 'moderatedAt' => $at], ['id' => $comment->getId()]);
        }
        $threads = $em->getClassMetadata(Post::class)->getTableName();
        $root = $em->getClassMetadata(\Base\Entity\Thread::class)->getTableName();
        foreach ($posts as $i => $post) {
            $connection->update($root, ['createdAt' => '2026-02-01 10:00:00', 'updatedAt' => sprintf('2026-05-%02d 10:00:00', $i)], ['id' => $post->getId()]);
        }
        unset($threads);
        $em->clear();
    }

    /** @param list<string> $roles */
    private function user(string $name, array $roles): object
    {
        $class = 'App\\Entity\\User';
        $user = new $class();
        $handle = strtolower(str_replace(' ', '.', iconv('UTF-8', 'ASCII//TRANSLIT', $name)));
        if (method_exists($user, 'setUsername')) {
            $user->setUsername($handle);
        }
        $user->setEmail($handle.'@example.org');
        $user->setPlainPassword('test-'.$handle.'-password');
        $user->setRoles($roles);
        $this->em()->persist($user);
        $this->em()->flush();
        $this->created[] = [$class, $user->getId()];

        return $user;
    }
}
