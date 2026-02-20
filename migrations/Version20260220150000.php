<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Dentists table: rename telephone to phone (spec: Phone, Email).
 */
final class Version20260220150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dentists: rename telephone to phone';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'dentists' AND column_name = 'telephone') THEN ALTER TABLE dentists RENAME COLUMN telephone TO phone; END IF; END \$\$;");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'dentists' AND column_name = 'phone') THEN ALTER TABLE dentists RENAME COLUMN phone TO telephone; END IF; END \$\$;");
    }
}
