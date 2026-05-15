<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260515170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist appointment cleaning_minutes for agenda totalBlockTime';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment ADD cleaning_minutes INT DEFAULT 5 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE appointment DROP cleaning_minutes');
    }
}
