<?php

namespace App\Controller\Admin;

use App\Entity\ApprovalRequest;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class ApprovalRequestCrudController extends ReadOnlyCrudController
{
    #[\Override]
    public static function getEntityFqcn(): string
    {
        return ApprovalRequest::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Approval')
            ->setEntityLabelInPlural('Approvals')
            ->setDefaultSort(['createdAt' => 'DESC'])
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield AssociationField::new('run')->setCrudController(AgentRunCrudController::class);
        yield TextField::new('action');
        yield TextField::new('targetSystem', 'Target');
        yield TextField::new('workflowName', 'Workflow');
        yield TextField::new('requesterId', 'Requester')->hideOnIndex();
        yield TextField::new('sensitivity');
        yield TextField::new('status');
        yield DateTimeField::new('createdAt', 'Created');
        yield TextField::new('approverId', 'Approver')->hideOnIndex();
        yield DateTimeField::new('decidedAt', 'Decided')->hideOnIndex();
        yield $this->redactedTextField('expectedEffect', 'Expected effect')->hideOnIndex();
    }
}
