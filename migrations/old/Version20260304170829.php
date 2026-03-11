<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260304170829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE treatment_pathology (treatment_id INT NOT NULL, pathology_id INT NOT NULL, PRIMARY KEY (treatment_id, pathology_id))');
        $this->addSql('CREATE INDEX IDX_CE325D8B471C0366 ON treatment_pathology (treatment_id)');
        $this->addSql('CREATE INDEX IDX_CE325D8BCE86795D ON treatment_pathology (pathology_id)');
        $this->addSql('ALTER TABLE treatment_pathology ADD CONSTRAINT FK_CE325D8B471C0366 FOREIGN KEY (treatment_id) REFERENCES treatment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE treatment_pathology ADD CONSTRAINT FK_CE325D8BCE86795D FOREIGN KEY (pathology_id) REFERENCES pathology (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE pathology ADD visual_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE treatment ADD last_odontogram_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE treatment ADD status VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE treatment_pathology DROP CONSTRAINT FK_CE325D8B471C0366');
        $this->addSql('ALTER TABLE treatment_pathology DROP CONSTRAINT FK_CE325D8BCE86795D');
        $this->addSql('DROP TABLE treatment_pathology');
        $this->addSql('ALTER TABLE pathology DROP visual_type');
        $this->addSql('ALTER TABLE treatment DROP last_odontogram_id');
        $this->addSql('ALTER TABLE treatment DROP status');
    }
}
