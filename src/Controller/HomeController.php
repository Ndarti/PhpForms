<?php
// src/Controller/HomeController.php
namespace App\Controller;

use App\Entity\Template;
use App\Repository\LikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home', methods: ['GET', 'POST'])]
    public function index(EntityManagerInterface $entityManager, LikeRepository $likeRepository): Response
    {
        $user = $this->getUser();

        // 1. Последние шаблоны
        $qb = $entityManager->createQueryBuilder()
            ->select('t')
            ->from(Template::class, 't')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(5);

        if ($user) {
            $qb->where('t.author = :author')
                ->setParameter('author', $user);
        } else {
            $qb->where('t.status = :public')
                ->setParameter('public', 'public');
        }

        $latestTemplates = $qb->getQuery()->getResult() ?? [];

        $latestTemplatesData = array_map(function ($template) use ($likeRepository, $user) {
            return [
                'template' => $template,
                'isLiked' => $user ? $likeRepository->findOneBy(['user' => $user, 'template' => $template]) !== null : false,
                'likeCount' => $likeRepository->count(['template' => $template]),
            ];
        }, $latestTemplates);

        // 2. Популярные шаблоны (по лайкам)
        $popularTemplates = $entityManager->createQueryBuilder()
            ->select('t, COUNT(l.id) as like_count')
            ->from(Template::class, 't')
            ->leftJoin('t.likes', 'l') // Предполагается, что у Template есть отношение "likes"
            ->where('t.status = :status')
            ->setParameter('status', 'public')
            ->groupBy('t.id')
            ->orderBy('like_count', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult() ?? [];

        $popularTemplatesData = array_map(function ($row) use ($likeRepository, $user) {
            $template = $row[0];
            return [
                'template' => $template,
                'likeCount' => $row['like_count'], // Количество лайков
                'submission_count' => $template->getFormSubmissions()->count(), // Оставляем для отображения, если нужно
                'isLiked' => $user ? $likeRepository->findOneBy(['user' => $user, 'template' => $template]) !== null : false,
            ];
        }, $popularTemplates);

        // 3. Теги
        $tagsResult = $entityManager->createQueryBuilder()
            ->select('t.tags')
            ->from(Template::class, 't')
            ->where('t.status = :status')
            ->setParameter('status', 'public')
            ->getQuery()
            ->getResult() ?? [];

        $tags = [];
        foreach ($tagsResult as $row) {
            if (is_array($row['tags'])) {
                foreach ($row['tags'] as $tag) {
                    $tags[$tag] = ($tags[$tag] ?? 0) + 1;
                }
            }
        }

        return $this->render('home/home.html.twig', [
            'latest_templates' => $latestTemplatesData,
            'popular_templates' => $popularTemplatesData,
            'tags' => $tags,
        ]);
    }
}