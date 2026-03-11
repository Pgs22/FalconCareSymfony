<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227123500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make document.patient_id nullable';
    }

    public function up(Schema $schema): void
    {
        // adapt statement to your platform if needed (MySQL assumed)
        $this->addSql('ALTER TABLE document ALTER COLUMN patient_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // revert to not null
        $this->addSql('ALTER TABLE document ALTER COLUMN patient_id SET NOT NULL');
    }
}
