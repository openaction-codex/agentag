<?php

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * @phpstan-require-extends \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase
 */
trait RefreshDatabaseTrait
{
    protected function refreshDatabase(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('Doctrine entity manager is not available in the test container.');
        }

        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        if ([] === $metadata) {
            return;
        }

        $schemaTool = new SchemaTool($entityManager);

        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Throwable) {
            // The first test run starts with an empty SQLite database.
        }

        $schemaTool->createSchema($metadata);
        $entityManager->clear();
    }
}
