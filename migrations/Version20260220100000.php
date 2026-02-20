<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename patients.dni to national_id for full English naming.
 */
final class Version20260220100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename patients.dni to national_id (English naming)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_patient_dni');
        $this->addSql('DROP INDEX IF EXISTS UNIQ_2CCC2E2C7F8F253B');
        $this->addSql('ALTER TABLE patients RENAME COLUMN dni TO national_id');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2CCC2E2C7F8F253B ON patients (national_id)');
        $this->addSql('CREATE INDEX idx_patient_national_id ON patients (national_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_patient_national_id');
        $this->addSql('DROP INDEX IF EXISTS UNIQ_2CCC2E2C7F8F253B');
        $this->addSql('ALTER TABLE patients RENAME COLUMN national_id TO dni');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2CCC2E2C7F8F253B ON patients (dni)');
        $this->addSql('CREATE INDEX idx_patient_dni ON patients (dni)');
    }
}
