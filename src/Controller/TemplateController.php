<?php
// src/Controller/TemplateController.php
namespace App\Controller;

use App\Entity\Comment;
use App\Entity\FormSubmission;
use App\Entity\Like;
use App\Entity\Question;
use App\Entity\Template;
use App\Repository\LikeRepository;
use App\Repository\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TemplateController extends AbstractController
{
    #[Route('/template/{id}/delete', name: 'template_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            $this->logger->error("No authenticated user for delete action on template ID: $id");
            return new JsonResponse(['error' => 'Пользователь не авторизован'], 403);
        }

        $template = $entityManager->getRepository(Template::class)->find($id);
        if (!$template) {
            $this->logger->error("Template ID $id not found for delete action");
            return new JsonResponse(['error' => 'Шаблон не найден'], 404);
        }

        if ($template->getAuthor() !== $user) {
            $this->logger->warning("User {$user->getId()} attempted to delete template ID $id that they do not own");
            return new JsonResponse(['error' => 'Вы можете удалять только свои шаблоны'], 403);
        }

        try {
            $entityManager->remove($template);
            $entityManager->flush();

            $this->logger->info("Template ID $id deleted by user {$user->getId()}");
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            $this->logger->error("Failed to delete template ID $id: {$e->getMessage()}");
            return new JsonResponse(['error' => 'Ошибка при удалении шаблона: ' . $e->getMessage()], 500);
        }
    }
    private LoggerInterface $logger;
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    #[Route('/template/new', name: 'app_template_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $template = new Template();
        if ($request->isMethod('POST')) {
            try {
                $template->setName($request->request->get('name'))
                    ->setDescription($request->request->get('description'))
                    ->setTags(explode(',', $request->request->get('tags', '')))
                    ->setStatus($request->request->get('status', 'public'))
                    ->setAuthor($this->getUser());

                $entityManager->persist($template);
                $entityManager->flush();

                $this->logger->info("Template created: ID {$template->getId()} by user {$this->getUser()->getId()}");
                return $this->redirectToRoute('app_template_show', ['id' => $template->getId()]);
            } catch (\Exception $e) {
                $this->logger->error("Failed to create template: {$e->getMessage()}");
                throw $this->createAccessDeniedException('Ошибка при создании шаблона');
            }
        }

        return $this->render('template/new.html.twig');
    }
    #[Route('/template/{id}', name: 'app_template_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): Response
    {
        try {
            $template = $entityManager->getRepository(Template::class)->find($id);

            if (!$template) {
                $this->addFlash('error', 'Шаблон не найден.');
                $this->logger->warning("Template with ID $id not found.");
                return $this->redirectToRoute('app_home');
            }

            if ($template->getStatus() === 'private' && $this->getUser() !== $template->getAuthor()) {
                $this->logger->warning("Access denied to private template ID: {$template->getId()}");
                throw $this->createAccessDeniedException('This template is private.');
            }

            return $this->render('/home/show.html.twig', [
                'template' => $template,
            ]);
        } catch (\Exception $e) {
            $this->logger->error("Error in show method for template ID $id: {$e->getMessage()}");
            throw $this->createNotFoundException('Произошла ошибка при загрузке шаблона: ' . $e->getMessage());
        }
    }
    #[Route('/template/{id}/like', name: 'template_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function like(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            $this->logger->error("No authenticated user for like action on template ID: $id");
            return new JsonResponse(['error' => 'Пользователь не авторизован'], 403);
        }

        $template = $entityManager->getRepository(Template::class)->find($id);
        if (!$template) {
            $this->logger->error("Template ID $id not found for like action");
            return new JsonResponse(['error' => 'Шаблон не найден'], 404);
        }

        $existingLike = $entityManager->getRepository(Like::class)->findOneBy([
            'user' => $user,
            'template' => $template,
        ]);

        if ($existingLike) {
            $likesCount = $entityManager->getRepository(Like::class)->count(['template' => $template]);
            $this->logger->info("Like already exists for template ID: $id by user {$user->getId()}, count: $likesCount");
            return new JsonResponse(['isLiked' => true, 'likes' => $likesCount]);
        }

        try {
            $like = new Like();
            $like->setUser($user);
            $like->setTemplate($template);
            $entityManager->persist($like);
            $entityManager->flush();

            $likesCount = $entityManager->getRepository(Like::class)->count(['template' => $template]);
            $this->logger->info("Like added to template ID: $id by user {$user->getId()}, new count: $likesCount");
            return new JsonResponse(['isLiked' => true, 'likes' => $likesCount]);
        } catch (\Exception $e) {
            $this->logger->error("Failed to add like to template ID: $id - {$e->getMessage()}");
            return new JsonResponse(['error' => 'Ошибка при добавлении лайка: ' . $e->getMessage()], 500);
        }
    }
    #[Route('/template/{id}/unlike', name: 'template_unlike', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function unlike(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            $this->logger->error("No authenticated user for unlike action on template ID: $id");
            return new JsonResponse(['error' => 'Пользователь не авторизован'], 403);
        }

        $template = $entityManager->getRepository(Template::class)->find($id);
        if (!$template) {
            $this->logger->error("Template ID $id not found for unlike action");
            return new JsonResponse(['error' => 'Шаблон не найден'], 404);
        }

        $existingLike = $entityManager->getRepository(Like::class)->findOneBy([
            'user' => $user,
            'template' => $template,
        ]);

        if (!$existingLike) {
            $likesCount = $entityManager->getRepository(Like::class)->count(['template' => $template]);
            $this->logger->info("No like to remove for template ID: $id by user {$user->getId()}, count: $likesCount");
            return new JsonResponse(['isLiked' => false, 'likes' => $likesCount]);
        }

        try {
            $entityManager->remove($existingLike);
            $entityManager->flush();

            $likesCount = $entityManager->getRepository(Like::class)->count(['template' => $template]);
            $this->logger->info("Like removed from template ID: $id by user {$user->getId()}, new count: $likesCount");
            return new JsonResponse(['isLiked' => false, 'likes' => $likesCount]);
        } catch (\Exception $e) {
            $this->logger->error("Failed to remove like from template ID: $id - {$e->getMessage()}");
            return new JsonResponse(['error' => 'Ошибка при удалении лайка: ' . $e->getMessage()], 500);
        }
    }
    #[Route('/template/{id}/like/status', name: 'template_like_status', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function likeStatus(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            $this->logger->error("No authenticated user for like status check on template ID: $id");
            return new JsonResponse(['error' => 'Пользователь не авторизован'], 403);
        }

        $template = $entityManager->getRepository(Template::class)->find($id);
        if (!$template) {
            $this->logger->error("Template ID $id not found for like status check");
            return new JsonResponse(['error' => 'Шаблон не найден'], 404);
        }

        $isLiked = $entityManager->getRepository(Like::class)->findOneBy([
                'user' => $user,
                'template' => $template,
            ]) !== null;

        $likesCount = $entityManager->getRepository(Like::class)->count(['template' => $template]);
        return new JsonResponse(['isLiked' => $isLiked, 'likes' => $likesCount]);
    }
    #[Route('/template/{id}/comment', name: 'survey_template_comment', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function addComment(Request $request, Template $template, EntityManagerInterface $entityManager): Response
    {
        $content = $request->request->get('content');

        if (!$content) {
            $this->addFlash('error', 'Комментарий не может быть пустым.');
            return $this->redirectToRoute('app_template_show', ['id' => $template->getId()]);
        }

        $comment = new Comment();
        $comment->setContent($content)
            ->setName($this->getUser())
            ->setTemplate($template);

        $entityManager->persist($comment);
        $entityManager->flush();

        $this->addFlash('success', 'Комментарий успешно добавлен.');
        return $this->redirectToRoute('app_template_show', ['id' => $template->getId()]);
    }
    public function template(int $id, EntityManagerInterface $entityManager): Response
    {

        $template = $entityManager->getRepository(Template::class)->find($id);

        // Если шаблон не найден, перенаправляем на главную страницу с ошибкой
        if (!$template) {
            $this->addFlash('error', 'Шаблон не найден.');
            return $this->redirectToRoute('app_home');
        }

        // Проверяем, является ли текущий пользователь автором
        if ($this->getUser() !== $template->getAuthor()) {
            $this->addFlash('error', 'Вы не являетесь автором этого шаблона.');
            return $this->redirectToRoute('app_home');
        }

        // Если проверка пройдена, отображаем форму для добавления вопросов
        return $this->render('survey/newTemplateTest.html.twig', [
            'template' => $template,
        ]);
    }
    #[Route('/template/{id}/delete', name: 'template_delete', methods: ['DELETE'])]
    public function deleteTemplate(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $template = $entityManager->getRepository(Template::class)->find($id);

        if (!$template) {
            return new JsonResponse(['success' => false, 'message' => 'Шаблон не найден'], 404);
        }

        // Проверяем, является ли текущий пользователь автором шаблона
        if ($this->getUser() !== $template->getAuthor() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['success' => false, 'message' => 'Вы можете удалять только свои шаблоны'], 403);
        }

        try {
            // 1. Удаляем FormSubmissions (зависит от Question)
            foreach ($template->getFormSubmissions() as $submission) {
                $entityManager->remove($submission);
            }

            // 2. Удаляем Questions (зависит от Template)
            foreach ($template->getQuestions() as $question) {
                $entityManager->remove($question);
            }

            // 3. Удаляем Comments (зависит от Template)
            foreach ($template->getComments() as $comment) {
                $entityManager->remove($comment);
            }

            // 4. Удаляем сам Template
            $entityManager->remove($template);

            // Применяем изменения в базе данных
            $entityManager->flush();

            $this->logger->info("Template ID $id deleted by user {$this->getUser()->getId()}");
            return new JsonResponse(['success' => true]);
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->logger->error("Foreign key constraint violation while deleting template ID $id: {$e->getMessage()}");
            return new JsonResponse(['success' => false, 'message' => 'Невозможно удалить шаблон из-за связанных данных'], 500);
        } catch (\Exception $e) {
            $this->logger->error("Failed to delete template ID $id: {$e->getMessage()}");
            return new JsonResponse(['success' => false, 'message' => 'Ошибка при удалении шаблона: ' . $e->getMessage()], 500);
        }
    }
    #[Route('/survey/template/{id}/add_questions', name: 'survey_template_add_questions', methods: ['POST'])]
    public function addQuestions(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $template = $entityManager->getRepository(Template::class)->find($id);

        if ($this->getUser() !== $template->getAuthor() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Недостаточно прав для добавления вопросов.');
            return $this->redirectToRoute('app_home');
        }

        $postData = $request->request->all();
        $questionsData = $postData['questions'] ?? [];

        // Собираем ID вопросов из формы
        $submittedQuestionIds = array_filter(array_column($questionsData, 'id'));

        // Удаляем только те вопросы, которые отсутствуют в новом списке и не имеют связанных ответов
        $existingQuestions = $template->getQuestions()->toArray();
        foreach ($existingQuestions as $question) {
            if (!in_array($question->getId(), $submittedQuestionIds)) {
                $submissionCount = $entityManager->getRepository(FormSubmission::class)
                    ->createQueryBuilder('fs')
                    ->select('COUNT(fs.id)')
                    ->where('fs.question = :question')
                    ->setParameter('question', $question)
                    ->getQuery()
                    ->getSingleScalarResult();

                if ($submissionCount == 0) {
                    $template->removeQuestion($question);
                    $entityManager->remove($question);
                }
            }
        }

        // Добавляем или обновляем вопросы
        $position = 0;
        foreach ($questionsData as $data) {
            if (!empty($data['id'])) {
                // Обновляем существующий вопрос
                $question = $entityManager->getRepository(Question::class)->find($data['id']);
                if ($question && $question->getTemplate() === $template) {
                    $question->setTitle($data['title']);
                    $question->setDescription($data['description'] ?? null);
                    $question->setType($data['type']);
                    $question->setShowInTable(isset($data['showInTable']) && $data['showInTable'] === 'on');
                    $question->setPosition($position++);
                    continue;
                }
            }

            // Создаём новый вопрос
            $question = new Question();
            $question->setTemplate($template);
            $question->setTitle($data['title']);
            $question->setDescription($data['description'] ?? null);
            $question->setType($data['type']);
            $question->setShowInTable(isset($data['showInTable']) && $data['showInTable'] === 'on');
            $question->setPosition($position++);

            $template->addQuestion($question);
        }

        try {
            $entityManager->flush();
            $this->addFlash('success', 'Вопросы успешно обновлены.');
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->addFlash('error', 'Невозможно удалить некоторые вопросы, так как существуют связанные с ними ответы.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Произошла ошибка при сохранении вопросов: ' . $e->getMessage());
        }

        return $this->redirectToRoute('survey_template_show', ['id' => $template->getId()]);
    }
    #[Route('/survey/{id}/submit', name: 'survey_template_submit', methods: ['POST'])]
    public function submit(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $template = $entityManager->getRepository(Template::class)->find($id);

        if (!$template) {
            $this->addFlash('error', 'Шаблон не найден.');
            return $this->redirectToRoute('app_home');
        }

        if (!$this->getUser()) {
            $this->addFlash('error', 'Войдите, чтобы пройти тест.');
            return $this->redirectToRoute('app_login');
        }

        $answersData = $request->request->all()['answers'] ?? [];

        foreach ($answersData as $questionId => $answer) {
            $question = $entityManager->getRepository(Question::class)->find($questionId);
            if ($question && $question->getTemplate() === $template) {
                $submission = new FormSubmission();
                $submission->setTemplate($template);
                $submission->setQuestion($question);
                $submission->setUser($this->getUser());
                $submission->setSubmittedAt(new \DateTime());

                if ($question->getType() === 'checkbox' && is_array($answer)) {
                    $submission->setAnswer(implode(', ', $answer));
                } else {
                    $submission->setAnswer($answer);
                }

                $entityManager->persist($submission);
            }
        }

        $entityManager->flush();

        $this->addFlash('success', 'Ваши ответы успешно отправлены.');
        return $this->redirectToRoute('survey_template_show', ['id' => $template->getId()]);
    }
    #[Route('/survey/{id}/start', name: 'survey_template_start', methods: ['GET'])]
    public function start(int $id, EntityManagerInterface $entityManager): Response
    {
        $template = $entityManager->getRepository(Template::class)->find($id);

        if (!$template) {
            $this->addFlash('error', 'Шаблон не найден.');
            return $this->redirectToRoute('app_home');
        }

        $questions = $template->getQuestions();

        return $this->render('home/start.html.twig', [
            'template' => $template,
            'questions' => $questions,
        ]);
    }
    #[Route('/template/{id}/delete1', name: 'template_delete1', methods: ['DELETE'])]
    public function delete1(int $id, EntityManagerInterface $entityManager, Request $request): JsonResponse
    {
        $template = $entityManager->getRepository(Template::class)->find($id);

        if (!$template) {
            return new JsonResponse(['success' => false, 'message' => 'Шаблон не найден'], 404);
        }

        // Проверяем, является ли текущий пользователь автором шаблона
        if ($this->getUser() !== $template->getAuthor()) {
            return new JsonResponse(['success' => false, 'message' => 'Вы можете удалять только свои шаблоны'], 403);
        }

        try {
            // 1. Удаляем FormSubmissions (зависит от Question)
            foreach ($template->getFormSubmissions() as $submission) {
                $entityManager->remove($submission);
            }

            // 2. Удаляем Questions (зависит от Template)
            foreach ($template->getQuestions() as $question) {
                $entityManager->remove($question);
            }

            // 3. Удаляем Comments (зависит от Template)
            foreach ($template->getComments() as $comment) {
                $entityManager->remove($comment);
            }

            // 4. Удаляем сам Template
            $entityManager->remove($template);

            // Применяем изменения в базе данных
            $entityManager->flush();

            $this->logger->info("Template ID $id deleted by user {$this->getUser()->getId()}");
            return new JsonResponse(['success' => true]);
        } catch (ForeignKeyConstraintViolationException $e) {
            $this->logger->error("Foreign key constraint violation while deleting template ID $id: {$e->getMessage()}");
            return new JsonResponse(['success' => false, 'message' => 'Невозможно удалить шаблон из-за связанных данных'], 500);
        } catch (\Exception $e) {
            $this->logger->error("Failed to delete template ID $id: {$e->getMessage()}");
            return new JsonResponse(['success' => false, 'message' => 'Ошибка при удалении шаблона: ' . $e->getMessage()], 500);
        }}
}