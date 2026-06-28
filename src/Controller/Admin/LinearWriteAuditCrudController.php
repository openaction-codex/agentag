<?php

namespace App\Controller\Admin;

use App\Entity\LinearWriteAudit;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class LinearWriteAuditCrudController extends ReadOnlyCrudController
{
    #[\Override]
    public static function getEntityFqcn(): string
    {
        return LinearWriteAudit::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Linear write')
            ->setEntityLabelInPlural('Linear writes')
            ->setDefaultSort(['createdAt' => 'DESC'])
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('operation');
        yield TextField::new('status');
        yield TextField::new('targetIssueIdentifier', 'Target issue');
        yield TextField::new('workflowName', 'Workflow')->hideOnIndex();
        yield TextField::new('requesterId', 'Requester')->hideOnIndex();
        yield TextField::new('sourceMessageId', 'Source message')->hideOnIndex();
        yield $this->redactedJsonField('resultingIssueIdentifiers', 'Resulting issues');
        yield $this->redactedTextField('failureSummary', 'Failure summary')->hideOnIndex();
        yield DateTimeField::new('createdAt', 'Created');
    }
}
