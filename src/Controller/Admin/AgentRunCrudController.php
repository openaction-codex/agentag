<?php

namespace App\Controller\Admin;

use App\Entity\AgentRun;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class AgentRunCrudController extends ReadOnlyCrudController
{
    #[\Override]
    public static function getEntityFqcn(): string
    {
        return AgentRun::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Run')
            ->setEntityLabelInPlural('Runs')
            ->setDefaultSort(['createdAt' => 'DESC'])
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield AssociationField::new('session')->setCrudController(ChatSessionCrudController::class);
        yield TextField::new('status');
        yield DateTimeField::new('createdAt', 'Created');
        yield TextField::new('workflowName', 'Agent')->hideOnDetail();
        yield TextField::new('workflowVersion', 'Agent version')->onlyOnDetail();
        yield TextField::new('workflowRevision', 'Workspace revision')->onlyOnDetail();
        yield TextField::new('requesterId', 'Requester')->hideOnIndex();
        yield TextField::new('sourceEventId', 'Source event')->hideOnIndex();
        yield TextField::new('workspaceCleanupState', 'Workspace cleanup');
        yield IntegerField::new('exitCode', 'Exit');
        yield IntegerField::new('inputTokens', 'Input tokens');
        yield IntegerField::new('outputTokens', 'Output tokens');
        yield IntegerField::new('totalTokens', 'Total tokens');
        yield $this->redactedTextField('inputSummary', 'Input')->hideOnIndex();
        yield $this->redactedTextField('outputSummary', 'Output')->hideOnIndex();
        yield $this->redactedTextField('contextSnapshot', 'Context snapshot')->onlyOnDetail();
        yield $this->redactedTextField('workspacePath', 'Workspace path')->onlyOnDetail();
        yield $this->redactedJsonField('artifacts')->onlyOnDetail();
        yield $this->redactedTextField('logSummary', 'Log summary')->onlyOnDetail();
    }
}
