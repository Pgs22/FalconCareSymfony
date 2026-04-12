<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260412120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Documents: original_name, wider type for MIME, patient_id NOT NULL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM document WHERE patient_id IS NULL');
        $this->addSql('ALTER TABLE document ADD original_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE document ALTER COLUMN type TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE document ALTER COLUMN patient_id SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP original_name');
        $this->addSql('ALTER TABLE document ALTER COLUMN type TYPE VARCHAR(50)');
        $this->addSql('ALTER TABLE document ALTER COLUMN patient_id DROP NOT NULL');
    }
}
