<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * No-op: index already correct from initial migration.
 */
final class Version20260219155043 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op';
    }

    public function up(Schema $schema): void
    {
    }

    public function down(Schema $schema): void
    {
    }
}
