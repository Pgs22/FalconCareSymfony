<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add allergies_bitmask column to patient table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient ADD allergies_bitmask INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient DROP allergies_bitmask');
    }
}
