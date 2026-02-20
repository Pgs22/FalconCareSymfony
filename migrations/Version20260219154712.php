<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * No-op: schema is already created in English by Version20260219152131.
 */
final class Version20260219154712 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'No-op (schema already in English)';
    }

    public function up(Schema $schema): void
    {
    }

    public function down(Schema $schema): void
    {
    }
}
