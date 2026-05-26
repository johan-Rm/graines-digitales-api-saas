<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stores accommodation area values as strings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accommodation ALTER area_size TYPE VARCHAR(80) USING area_size::VARCHAR(80)');
        $this->addSql('ALTER TABLE accommodation ALTER area_terrace TYPE VARCHAR(80) USING area_terrace::VARCHAR(80)');
        $this->addSql('ALTER TABLE accommodation ALTER floor_size TYPE VARCHAR(80) USING floor_size::VARCHAR(80)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE accommodation ALTER area_size TYPE INT USING CASE WHEN area_size ~ '^-?[0-9]+$' THEN area_size::INT ELSE NULL END");
        $this->addSql("ALTER TABLE accommodation ALTER area_terrace TYPE INT USING CASE WHEN area_terrace ~ '^-?[0-9]+$' THEN area_terrace::INT ELSE NULL END");
        $this->addSql("ALTER TABLE accommodation ALTER floor_size TYPE INT USING CASE WHEN floor_size ~ '^-?[0-9]+$' THEN floor_size::INT ELSE NULL END");
    }
}
