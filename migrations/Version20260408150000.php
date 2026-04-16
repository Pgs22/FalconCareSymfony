<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable profile_image column to patient table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient ADD profile_image TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patient DROP profile_image');
    }
}
