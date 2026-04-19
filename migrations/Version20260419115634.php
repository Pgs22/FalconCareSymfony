<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260419115634 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Añadir columna allergies_bitmask a la tabla patient';
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
