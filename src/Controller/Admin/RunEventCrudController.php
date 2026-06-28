<?php

namespace App\Controller\Admin;

use App\Entity\RunEvent;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class RunEventCrudController extends ReadOnlyCrudController
{
    #[\Override]
    public static function getEntityFqcn(): string
    {
        return RunEvent::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Run event')
            ->setEntityLabelInPlural('Run events')
            ->setDefaultSort(['createdAt' => 'DESC'])
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield AssociationField::new('run')->setCrudController(AgentRunCrudController::class);
        yield TextField::new('type');
        yield DateTimeField::new('createdAt', 'Created');
        yield $this->redactedTextField('message');
        yield $this->redactedJsonField('metadata')->hideOnIndex();
    }
}
