<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AmstRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppController extends AbstractController
{
    #[Route('/', name: 'app_redirect', methods: ['GET'])]
    public function redirectHome(Request $request): Response
    {
        return $this->redirectToRoute('app_homepage', ['_locale' => $request->getPreferredLanguage(['en', 'nl', 'fr'])]);
    }

    #[Route('/{_locale}/', name: 'app_homepage', requirements: ['_locale' => 'en|nl|fr|de|es|hu'], methods: ['GET'])]
    public function homepage(AmstRepository $finds): Response
    {
        return $this->render('app/app_homepage.html.twig', ['findCount' => $finds->count([])]);
    }

    #[Route('/{_locale}/search', name: 'app_catalog_search', requirements: ['_locale' => 'en|nl|fr|de|es|hu'], methods: ['GET'])]
    public function search(): Response
    {
        return $this->render('search/index.html.twig');
    }
}
