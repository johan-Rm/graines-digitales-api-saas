<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove schema_data from web page translations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE web_page_translation DROP schema_data');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE web_page_translation ADD schema_data JSON DEFAULT NULL');
    }
}
