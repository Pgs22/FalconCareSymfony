<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Visits table: rename observations to notes (spec: Notes).
 */
final class Version20260220160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Visits: rename observations to notes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'visits' AND column_name = 'observations') THEN ALTER TABLE visits RENAME COLUMN observations TO notes; END IF; END \$\$;");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'visits' AND column_name = 'notes') THEN ALTER TABLE visits RENAME COLUMN notes TO observations; END IF; END \$\$;");
    }
}
