<?php

namespace App\Controller\Admin;

use App\Entity\ChatSession;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class ChatSessionCrudController extends ReadOnlyCrudController
{
    #[\Override]
    public static function getEntityFqcn(): string
    {
        return ChatSession::class;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Session')
            ->setEntityLabelInPlural('Sessions')
            ->setDefaultSort(['lastActivityAt' => 'DESC'])
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('platform');
        yield TextField::new('teamId', 'Team');
        yield TextField::new('channelId', 'Channel');
        yield TextField::new('threadId', 'Thread');
        yield DateTimeField::new('lastActivityAt', 'Last activity');
        yield IntegerField::new('inputTokens', 'Input tokens');
        yield IntegerField::new('outputTokens', 'Output tokens');
        yield IntegerField::new('totalTokens', 'Total tokens');
        yield $this->redactedTextField('summary')->hideOnIndex();
        yield $this->redactedTextField('sessionKey', 'Session key')->onlyOnDetail();
    }
}
