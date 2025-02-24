<?php
namespace App\Controller;

use App\Repository\TemplateRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class SearchController extends AbstractController
{
    #[Route('/search', name: 'search_templates', methods: ['GET'])]
    public function search(Request $request, TemplateRepository $templateRepository): Response
    {
        $query = $request->query->get('q');
        $templates = $query ? $templateRepository->search($query) : [];

        return $this->render('search/results.html.twig', [
            'templates' => $templates,
            'query' => $query,
        ]);
    }
}