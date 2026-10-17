<?php

namespace Base\Blog\Controller\Client;

use Base\Blog\Entity\Post;
use Base\Form\Type\CommentType;
use Base\Form\Model\CommentModel;
use Base\Repository\Thread\CommentRepository;
use Base\Blog\Repository\PostRepository;
use Base\Service\CommentGuard;
use Base\Blog\Service\CommentNotifier;
use Base\Blog\Service\CommentPolicy;
use Base\Blog\Screen\BlogScreens;
use Base\Bridge\Symfony\ScreenResponder;
use Base\Screen\View;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A comment left on a post (/chroniques/{slug}/commenter) or in the
 * visitors' book (/livre-d-or): the guard first (trap, speed, flood), then
 * the form - which asks Akismet through omnibase's FormTypeSpamExtension -,
 * then the administrators told. The visitor lands back on the page, at the
 * comment or at a word saying it waits.
 */
class CommentController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly CommentRepository $comments,
        private readonly EntityManagerInterface $entityManager,
        private readonly CommentGuard $guard,
        private readonly CommentNotifier $notifier,
        private readonly TranslatorInterface $translator,
        #[Autowire('%blog.comments.enabled%')] private readonly bool $enabled = true,
        #[Autowire('%blog.comments.guest_allowed%')] private readonly bool $guestAllowed = true,
        #[Autowire('%blog.comments.guest_replies%')] private readonly bool $guestReplies = false,
        #[Autowire('%blog.comments.auto_approve%')] private readonly bool $autoApprove = true,
        #[Autowire('%blog.comments.max_length%')] private readonly int $maxLength = 4000,
        #[Autowire('%blog.comments.min_delay%')] private readonly int $minDelay = 4,
        #[Autowire('%blog.comments.flood_interval%')] private readonly int $floodInterval = 60,
        // glitchr/ux-google, when installed: reCAPTCHA v3 on the form once google.recaptcha.enable is on.
        private readonly ?\Google\Service\GrService $recaptcha = null,
        // Who may write and answer (the same blog.comments.* settings), and the pages shown again with a form's errors.
        private ?CommentPolicy $policy = null,
        private readonly ?BlogScreens $screens = null,
        private readonly ?ScreenResponder $responder = null,
    ) {
        $this->policy ??= new CommentPolicy($this->enabled, $this->guestAllowed, $this->guestReplies);
    }

    /** The visitors' book: the comments with no post, and the form (a template and its view model, plan "omnibase agnostique", C5). */
    #[Route('/livre-d-or', name: 'blog_book')]
    public function book(Request $request): Response
    {
        $screen = $this->screens->book();

        return $this->responder->respond($request, new View('@Blog/client/book.html.twig', $screen, 'Blog/Book'), [
            'comment_form' => $screen->discussion->open ? $this->form(null)->createView() : null,
        ] + BlogController::legacy($this->container->get('twig'), '@Blog/client/book.html.twig', fn () => ['comments' => $this->comments->findVisible(null)]));
    }

    #[Route('/livre-d-or/commenter', name: 'blog_book_comment', methods: ['POST'])]
    public function commentBook(Request $request): Response
    {
        return $this->handle($request, null, $this->generateUrl('blog_book'));
    }

    #[Route('/%blog.path%/{slug}/commenter', name: 'blog_post_comment', methods: ['POST'], requirements: ['slug' => '[a-z0-9\-]+'])]
    public function commentPost(Request $request, string $slug): Response
    {
        $post = $this->posts->findOnePublished($slug) ?? throw $this->createNotFoundException();
        if (!$post->isCommentsOpen()) {
            throw $this->createAccessDeniedException('Comments are closed on this post.');
        }

        return $this->handle($request, $post, $this->generateUrl('blog_post', ['slug' => $slug]));
    }

    /** The form a page embeds (the Twig function blog_comment_form(), @Blog/client/_comments.html.twig). */
    public function form(?Post $post, ?CommentModel $model = null): FormInterface
    {
        $user = $this->getUser();
        $model ??= CommentModel::forUser($user instanceof \App\Entity\User ? $user : null);

        return $this->createForm(CommentType::class, $model, [
            'action' => $post ? $this->generateUrl('blog_post_comment', ['slug' => $post->getSlug()]) : $this->generateUrl('blog_book_comment'),
            'signed_in' => null !== $user,
            'recaptcha' => (bool) $this->recaptcha?->isEnabled(),
            'max_length' => $this->maxLength,
            'placeholders' => ['name' => '@blog.comment.form.name', 'email' => '@blog.comment.form.email', 'content' => '@blog.comment.form.content'],
            'trap_class' => 'blog-trap',
            'translation_domain' => 'blog',
        ]);
    }

    public function isOpen(?Post $post): bool
    {
        return $this->policy->isOpen($post, null !== $this->getUser());
    }

    /** Answering a comment: an account, unless blog.comments.guest_replies opens it to everyone. */
    public function canReply(): bool
    {
        return $this->policy->canReply(null !== $this->getUser());
    }

    private function handle(Request $request, ?Post $post, string $back): Response
    {
        if (!$this->isOpen($post)) {
            throw $this->createAccessDeniedException('Comments are closed.');
        }

        $model = CommentModel::forUser($this->getUser() instanceof \App\Entity\User ? $this->getUser() : null);
        $form = $this->form($post, $model);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $reason = $this->guard->check($form, $request, $this->minDelay, $this->floodInterval);
            // A robot in the trap is thanked and forgotten: it learns nothing.
            if (CommentGuard::TRAPPED === $reason) {
                $this->addFlash('blog', 'comment.flash.pending');

                return $this->redirect($back.'#commentaires');
            }
            if ($reason) {
                $form->addError(new FormError($this->translator->trans('@blog.comment.error.'.$reason)));
            }
            // The "répondre" button is not shown to a visitor without an account: a reply posted anyway is refused.
            if ((int) $form->get('parent')->getData() && !$this->canReply()) {
                $form->addError(new FormError($this->translator->trans('@blog.comment.error.reply_signed_in')));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $comment = $model->toComment($post, $this->autoApprove);
            $comment->setIp($request->getClientIp());
            $comment->setUserAgent($request->headers->get('user-agent'));
            if ($parentId = (int) $form->get('parent')->getData()) {
                $parent = $this->comments->find($parentId);
                if ($parent && $parent->getThread() === $post && $parent->isVisible()) {
                    $comment->setParent($parent);
                }
            }
            $this->entityManager->persist($comment);
            $this->entityManager->flush();
            $this->notifier->commentLeft($comment);
            $this->addFlash('blog', $comment->isVisible() ? 'comment.flash.published' : 'comment.flash.pending');

            return $this->redirect($back.($comment->isVisible() ? '#commentaire-'.$comment->getId() : '#commentaires'));
        }

        // Back on the page with the errors: the post or the book, shown again from their view models (the post without schema.org's description, as before).
        $template = $post ? '@Blog/client/post.html.twig' : '@Blog/client/book.html.twig';
        $view = new View($template, $post ? $this->screens->post($post) : $this->screens->book(), $post ? 'Blog/Post' : 'Blog/Book', Response::HTTP_UNPROCESSABLE_ENTITY);

        return $this->responder->respond($request, $view, ['comment_form' => $form->createView()] + BlogController::legacy($this->container->get('twig'), $template, function () use ($post): array {
            $parameters = ['comments' => $this->comments->findVisible($post)];
            if ($post) {
                [$older, $newer] = $this->posts->findNeighbours($post);
                $parameters += ['post' => $post, 'older' => $older, 'newer' => $newer];
            }

            return $parameters;
        }));
    }
}
