<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426165000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create radiograph_annotation table linked to document, patient and appointment';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE radiograph_annotation (id SERIAL NOT NULL, document_id INT NOT NULL, patient_id INT NOT NULL, appointment_id INT NOT NULL, tool VARCHAR(64) NOT NULL, label VARCHAR(255) DEFAULT NULL, color VARCHAR(32) DEFAULT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_radiograph_annotation_document ON radiograph_annotation (document_id)');
        $this->addSql('CREATE INDEX idx_radiograph_annotation_patient ON radiograph_annotation (patient_id)');
        $this->addSql('CREATE INDEX idx_radiograph_annotation_appointment ON radiograph_annotation (appointment_id)');
        $this->addSql('ALTER TABLE radiograph_annotation ADD CONSTRAINT FK_BF614BDD33F7837 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE radiograph_annotation ADD CONSTRAINT FK_BF614BDD6B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE radiograph_annotation ADD CONSTRAINT FK_BF614BDD6B3CA4B FOREIGN KEY (appointment_id) REFERENCES appointment (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE radiograph_annotation');
    }
}
