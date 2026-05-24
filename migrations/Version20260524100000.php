<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260524100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Convert accommodation.level from boolean to smallint.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accommodation ALTER level TYPE SMALLINT USING CASE WHEN level IS NULL THEN NULL WHEN level THEN 1 ELSE 0 END');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accommodation ALTER level TYPE BOOLEAN USING CASE WHEN level IS NULL THEN NULL WHEN level = 0 THEN FALSE ELSE TRUE END');
    }
}
