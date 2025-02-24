<?php
namespace App\Controller;

use App\Entity\FormSubmission;
use App\Entity\Question;
use App\Entity\Registration;
use App\Entity\Template;
use App\Repository\TemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SurveyController extends AbstractController
{
    #[Route('/survey/{id}/submissions', name: 'survey_template_submissions', methods: ['GET'])]
    public function viewSubmissions(int $id): Response
    {
        $template = $this->entityManager->getRepository(Template::class)->find($id);

        if (!$template) {
            $this->addFlash('error', 'Шаблон не найден.');
            return $this->redirectToRoute('app_home');
        }

        // Проверяем, является ли текущий пользователь автором или администратором
        if ($this->getUser() !== $template->getAuthor() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Вы не имеете прав для просмотра заполненных форм этого шаблона.');
            return $this->redirectToRoute('app_home');
        }

        // Получаем все отправленные формы для этого шаблона
        $submissions = $this->entityManager->getRepository(FormSubmission::class)->findBy(['template' => $template]);

        // Группируем ответы по пользователям
        $groupedSubmissions = [];
        foreach ($submissions as $submission) {
            $user = $submission->getUser();
            if (!isset($groupedSubmissions[$user->getId()])) {
                $groupedSubmissions[$user->getId()] = [
                    'user' => $user,
                    'answers' => []
                ];
            }
            $groupedSubmissions[$user->getId()]['answers'][] = $submission;
        }

        return $this->render('/home/submits.html.twig', [
            'template' => $template,
            'groupedSubmissions' => $groupedSubmissions,
        ]);
    }
    private EntityManagerInterface $entityManager;
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    #[Route('/survey/new', name: 'survey_template_new')]
    public function new(): Response
    {
        return $this->render('/home/new.html.twig', []);
    }
    #[Route('/survey/template/oldTemplateTest/{id}', name: 'survey_template_Template', methods: ['GET'])]
    public function template(int $id): Response
    {
        $template = $this->entityManager->getRepository(Template::class)->find($id);

        if (!$template) {
            $this->addFlash('error', 'Шаблон не найден.');
            return $this->redirectToRoute('app_home');
        }

        if ($this->getUser() !== $template->getAuthor() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Вы не являетесь автором этого шаблона.');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('/home/newTemplateTest.html.twig', [
            'template' => $template,
        ]);
    }
    #[Route('/survey/template/{id}/add_questions', name: 'survey_template_add_questions', methods: ['POST'])]
    public function addQuestions(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $template = $entityManager->getRepository(Template::class)->find($id);

        if (!$template || $this->getUser() !== $template->getAuthor()) {
            $this->addFlash('error', 'Недостаточно прав для добавления вопросов.');
            return $this->redirectToRoute('app_home');
        }

        $postData = $request->request->all();
        $questionsData = $postData['questions'] ?? [];

        // Собираем ID вопросов из формы
        $submittedQuestionIds = array_filter(array_column($questionsData, 'id'));

        // Получаем существующие вопросы
        $existingQuestions = $template->getQuestions()->toArray();
        foreach ($existingQuestions as $question) {
            // Проверяем, есть ли связанные ответы
            $submissionCount = $entityManager->getRepository(FormSubmission::class)
                ->createQueryBuilder('fs')
                ->select('COUNT(fs.id)')
                ->where('fs.question = :question')
                ->setParameter('question', $question)
                ->getQuery()
                ->getSingleScalarResult();

            if ($submissionCount == 0 && !in_array($question->getId(), $submittedQuestionIds)) {
                $template->removeQuestion($question); // Удаляем только если нет ответов и вопроса нет в форме
            }
        }

        // Добавляем или обновляем вопросы
        $position = 0;
        foreach ($questionsData as $data) {
            if (!empty($data['id'])) {
                $question = $entityManager->getRepository(Question::class)->find($data['id']);
                if ($question && $question->getTemplate() === $template) {
                    // Обновляем существующий вопрос
                    $question->setTitle($data['title']);
                    $question->setDescription($data['description'] ?? null);
                    $question->setType($data['type']);
                    $question->setShowInTable(isset($data['showInTable']) && $data['showInTable'] === 'on');
                    $question->setPosition($position++);
                    continue;
                }
            }
            // Создаем новый вопрос
            $question = new Question();
            $question->setTemplate($template);
            $question->setTitle($data['title']);
            $question->setDescription($data['description'] ?? null);
            $question->setType($data['type']);
            $question->setShowInTable(isset($data['showInTable']) && $data['showInTable'] === 'on');
            $question->setPosition($position++);

            $template->addQuestion($question);
        }

        $entityManager->flush();
        $this->addFlash('success', 'Вопросы успешно обновлены.');
        return $this->redirectToRoute('survey_template_show', ['id' => $template->getId()]);
    }
    #[Route('/survey/{id}', name: 'survey_template_show', methods: ['GET'])]
    public function show(int $id): Response
    {
        $template = $this->entityManager->getRepository(Template::class)->find($id);

        if (!$template) {
            $this->addFlash('error', 'Шаблон не найден.');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('/home/show.html.twig', [
            'template' => $template,
        ]);
    }
    #[Route('/tem/{id}', name: 'survey_template_show', methods: ['GET'])]
    public function show1(int $id, EntityManagerInterface $entityManager): Response
    {
        $template = $this->entityManager->getRepository(Template::class)->find($id);
        if (!$template) {
            $this->addFlash('error', 'Шаблон не найден.');
            return $this->redirectToRoute('app_home');
        }
        return $this->render('/home/show.html.twig', [
            'template' => $template,
        ]);
    }
    #[Route('/survey/all', name: 'survey_all_templates', methods: ['GET'])]
    public function allTemplates(TemplateRepository $templateRepository): Response
    {
        $templates = $templateRepository->findAll();
        return $this->render('Survey/all_templates.html.twig', [
            'templates' => $templates,
        ]);
    }
}
