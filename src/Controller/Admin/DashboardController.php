<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'agentag_admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(private readonly AdminUrlGeneratorInterface $adminUrlGenerator)
    {
    }

    #[\Override]
    public function index(): Response
    {
        $url = $this->adminUrlGenerator
            ->setController(ChatSessionCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl()
        ;

        return $this->redirect($url);
    }

    #[\Override]
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('AgentTag')
        ;
    }

    #[\Override]
    public function configureCrud(): Crud
    {
        return Crud::new()
            ->setPaginatorPageSize(50)
            ->setDateTimeFormat('yyyy-MM-dd HH:mm:ss')
        ;
    }

    #[\Override]
    public function configureActions(): Actions
    {
        return parent::configureActions()
            ->disable(
                Action::NEW,
                Action::EDIT,
                Action::DELETE,
                Action::BATCH_DELETE,
                Action::SAVE_AND_ADD_ANOTHER,
                Action::SAVE_AND_CONTINUE,
                Action::SAVE_AND_RETURN,
            )
        ;
    }

    #[\Override]
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return UserMenu::new()
            ->setName($user->getUserIdentifier())
            ->displayUserName()
            ->displayUserAvatar(false)
            ->disableLogoutLink()
        ;
    }

    #[\Override]
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Usage');
        yield MenuItem::linkTo(ChatSessionCrudController::class, 'Sessions', 'fa fa-comments');
        yield MenuItem::linkTo(AgentRunCrudController::class, 'Runs', 'fa fa-terminal');
        yield MenuItem::linkTo(RunEventCrudController::class, 'Run events', 'fa fa-list');
        yield MenuItem::section('Audit');
        yield MenuItem::linkTo(ApprovalRequestCrudController::class, 'Approvals', 'fa fa-check-circle');
        yield MenuItem::linkTo(GlobalMemoryCrudController::class, 'Global memories', 'fa fa-brain');
    }
}
