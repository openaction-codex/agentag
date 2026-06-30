<?php

namespace App\Controller\Admin;

use App\AgentTag\Security\SensitiveTextRedactor;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\HttpFoundation\Response;

/**
 * @extends AbstractCrudController<object>
 */
abstract class ReadOnlyCrudController extends AbstractCrudController
{
    public function __construct(private readonly SensitiveTextRedactor $redactor)
    {
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(
                Action::NEW,
                Action::EDIT,
                Action::DELETE,
                Action::BATCH_DELETE,
                Action::SAVE_AND_ADD_ANOTHER,
                Action::SAVE_AND_CONTINUE,
                Action::SAVE_AND_RETURN,
            )
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    #[\Override]
    public function new(AdminContext $context): KeyValueStore|Response
    {
        $this->denyWriteAction();
    }

    #[\Override]
    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $this->denyWriteAction();
    }

    #[\Override]
    public function delete(AdminContext $context): KeyValueStore|Response
    {
        $this->denyWriteAction();
    }

    #[\Override]
    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        $this->denyWriteAction();
    }

    #[\Override]
    public function persistEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->denyWriteAction();
    }

    #[\Override]
    public function updateEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->denyWriteAction();
    }

    #[\Override]
    public function deleteEntity(EntityManagerInterface $entityManager, object $entityInstance): void
    {
        $this->denyWriteAction();
    }

    protected function redactedTextField(string $property, ?string $label = null): TextareaField
    {
        return TextareaField::new($property, $label)
            ->setTemplatePath('admin/field/redacted_text.html.twig')
            ->formatValue(fn (mixed $value): AdminRedactedValue => $this->formatRedactedValue($value))
        ;
    }

    protected function redactedJsonField(string $property, ?string $label = null): Field
    {
        return Field::new($property, $label)
            ->setTemplatePath('admin/field/redacted_text.html.twig')
            ->formatValue(fn (mixed $value): AdminRedactedValue => $this->formatRedactedValue($value))
        ;
    }

    protected function formatRedactedValue(mixed $value): AdminRedactedValue
    {
        return new AdminRedactedValue($this->redactor->redact($this->stringify($value)));
    }

    private function stringify(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        try {
            return json_encode($value, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);
        } catch (\JsonException) {
            return '[unserializable value]';
        }
    }

    private function denyWriteAction(): never
    {
        throw $this->createAccessDeniedException('The AgentTag admin panel is read-only.');
    }
}
