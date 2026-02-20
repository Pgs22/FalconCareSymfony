<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Patients table: align column names with spec (phone, medication_allergies).
 */
final class Version20260220140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Patients: rename telephone->phone, allergy_medications->medication_allergies';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'patients' AND column_name = 'telephone') THEN ALTER TABLE patients RENAME COLUMN telephone TO phone; END IF; END \$\$;");
        $this->addSql("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'patients' AND column_name = 'allergy_medications') THEN ALTER TABLE patients RENAME COLUMN allergy_medications TO medication_allergies; END IF; END \$\$;");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'patients' AND column_name = 'phone') THEN ALTER TABLE patients RENAME COLUMN phone TO telephone; END IF; END \$\$;");
        $this->addSql("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'patients' AND column_name = 'medication_allergies') THEN ALTER TABLE patients RENAME COLUMN medication_allergies TO allergy_medications; END IF; END \$\$;");
    }
}
