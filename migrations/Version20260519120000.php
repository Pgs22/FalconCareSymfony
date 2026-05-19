<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Persiste el binario del fichero en BD (Neon) para que cualquier cliente de la API pueda descargarlo.
 */
final class Version20260519120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add document.file_content (BYTEA) for cross-host file access';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document ADD file_content BYTEA DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document DROP file_content');
    }
}
