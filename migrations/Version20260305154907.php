<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260305154907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tooth_face ADD odontograma_detail_id INT NOT NULL');
        $this->addSql('ALTER TABLE tooth_face ADD CONSTRAINT FK_F4AA349829093CE6 FOREIGN KEY (odontograma_detail_id) REFERENCES odontograma_detail (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_F4AA349829093CE6 ON tooth_face (odontograma_detail_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tooth_face DROP CONSTRAINT FK_F4AA349829093CE6');
        $this->addSql('DROP INDEX IDX_F4AA349829093CE6');
        $this->addSql('ALTER TABLE tooth_face DROP odontograma_detail_id');
    }
}
