<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260530173831 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove deleted_at (soft delete) from all entities';
    }

    public function up(Schema $schema): void
    {
        $tables = [
            'accommodation',
            'accommodation_translation',
            'article',
            'article_translation',
            'category_code',
            'category_code_translation',
            'media_object',
            'media_object_translation',
            'message',
            'organization',
            'organization_member',
            'project',
            'project_member',
            '"user"',
            'web_page',
            'web_page_translation',
        ];

        foreach ($tables as $table) {
            $this->addSql("ALTER TABLE {$table} DROP COLUMN deleted_at");
        }
    }

    public function down(Schema $schema): void
    {
        $tables = [
            'accommodation',
            'accommodation_translation',
            'article',
            'article_translation',
            'category_code',
            'category_code_translation',
            'media_object',
            'media_object_translation',
            'message',
            'organization',
            'organization_member',
            'project',
            'project_member',
            '"user"',
            'web_page',
            'web_page_translation',
        ];

        foreach ($tables as $table) {
            $this->addSql("ALTER TABLE {$table} ADD deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
        }
    }
}
