<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260602123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add localized text to category code translations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category_code_translation ADD text TEXT DEFAULT NULL');
        $this->addSql(
            "UPDATE category_code_translation translation
            SET text = jsonb_extract_path_text(category_code.metadata::jsonb, 'text')
            FROM category_code
            INNER JOIN project ON project.id = category_code.project_id
            WHERE translation.category_code_id = category_code.id
            AND translation.locale = project.source_locale
            AND jsonb_extract_path_text(category_code.metadata::jsonb, 'text') IS NOT NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category_code_translation DROP text');
    }
}
