<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard('/admin', 'app_admin')]
final class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->redirect($this->container->get(AdminUrlGenerator::class)->setController(AmstCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Below the Surface')->setTranslationDomain('amst');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute(new \Symfony\Component\Translation\TranslatableMessage('app_homepage', domain: 'routing'), 'fa fa-home', 'app_homepage');
        yield MenuItem::linkTo(AmstCrudController::class, 'vondstnummer.label', 'fa fa-list');
    }
}
