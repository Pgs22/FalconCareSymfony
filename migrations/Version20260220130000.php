<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename table treatment_rooms to boxes.
 */
final class Version20260220130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename treatment_rooms to boxes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'treatment_rooms') THEN ALTER TABLE treatment_rooms RENAME TO boxes; END IF; END \$\$;");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE boxes RENAME TO treatment_rooms');
    }
}
