<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260530095829 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename category_code.code to code_value to align with schema.org CategoryCode';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_category_code_project_set_code');
        $this->addSql('ALTER TABLE category_code RENAME COLUMN code TO code_value');
        $this->addSql('CREATE UNIQUE INDEX uniq_category_code_project_set_code_value ON category_code (project_id, in_code_set, code_value)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_category_code_project_set_code_value');
        $this->addSql('ALTER TABLE category_code RENAME COLUMN code_value TO code');
        $this->addSql('CREATE UNIQUE INDEX uniq_category_code_project_set_code ON category_code (project_id, in_code_set, code)');
    }
}
