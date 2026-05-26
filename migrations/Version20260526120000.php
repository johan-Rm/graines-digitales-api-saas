<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Changes accommodation floor size from string to integer.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accommodation ALTER floor_size DROP DEFAULT');
        $this->addSql("ALTER TABLE accommodation ALTER floor_size TYPE INT USING CASE WHEN floor_size ~ '^-?[0-9]+$' THEN floor_size::INT ELSE NULL END");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accommodation ALTER floor_size TYPE VARCHAR(80) USING floor_size::VARCHAR(80)');
    }
}
