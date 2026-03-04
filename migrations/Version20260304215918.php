<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260304215918 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE odontogram ADD treatment_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE odontogram ADD CONSTRAINT FK_251BF940471C0366 FOREIGN KEY (treatment_id) REFERENCES treatment (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_251BF940471C0366 ON odontogram (treatment_id)');
        $this->addSql('ALTER TABLE tooth_face DROP CONSTRAINT fk_fa2992f9e8457812');
        $this->addSql('DROP INDEX idx_fa2992f9e8457812');
        $this->addSql('ALTER TABLE tooth_face DROP odontograma_detail_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE odontogram DROP CONSTRAINT FK_251BF940471C0366');
        $this->addSql('DROP INDEX IDX_251BF940471C0366');
        $this->addSql('ALTER TABLE odontogram DROP treatment_id');
        $this->addSql('ALTER TABLE tooth_face ADD odontograma_detail_id INT NOT NULL');
        $this->addSql('ALTER TABLE tooth_face ADD CONSTRAINT fk_fa2992f9e8457812 FOREIGN KEY (odontograma_detail_id) REFERENCES odontograma_detail (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_fa2992f9e8457812 ON tooth_face (odontograma_detail_id)');
    }
}
