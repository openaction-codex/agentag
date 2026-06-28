<?php

namespace App\Controller\Admin;

use App\Entity\GlobalMemory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class GlobalMemoryCrudController extends ReadOnlyCrudController
{
    #[\Override]
    public static function getEntityFqcn(): string
    {
        return GlobalMemory::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Global memory')
            ->setEntityLabelInPlural('Global memories')
            ->setDefaultSort(['createdAt' => 'DESC'])
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield $this->redactedTextField('content');
        yield DateTimeField::new('createdAt', 'Created');
        yield TextField::new('createdBy', 'Created by');
        yield TextField::new('sourcePlatform', 'Platform');
        yield TextField::new('sourceThreadId', 'Thread')->hideOnIndex();
        yield TextField::new('sourceMessageId', 'Message')->hideOnIndex();
    }
}
