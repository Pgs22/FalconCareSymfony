<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260518170512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add fields consentiment y first_visit a patient';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE patient ADD consentiment BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE patient ADD first_visit BOOLEAN NOT NULL');

    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE patient DROP consentiment');
        $this->addSql('ALTER TABLE patient DROP first_visit');

    }
}
