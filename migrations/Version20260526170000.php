<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove CreativeWork and MenuItem resources.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE creative_work_translation');
        $this->addSql('DROP TABLE menu_item_translation');
        $this->addSql('DROP TABLE creative_work');
        $this->addSql('DROP TABLE menu_item');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE creative_work (identifier VARCHAR(180) NOT NULL, status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_16B9077AB03A8386 ON creative_work (created_by_id)');
        $this->addSql('CREATE INDEX IDX_16B9077A896DBBDE ON creative_work (updated_by_id)');
        $this->addSql('CREATE INDEX idx_creative_work_project ON creative_work (project_id)');
        $this->addSql('CREATE TABLE menu_item (url VARCHAR(220) NOT NULL, position INT NOT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D754D550B03A8386 ON menu_item (created_by_id)');
        $this->addSql('CREATE INDEX IDX_D754D550896DBBDE ON menu_item (updated_by_id)');
        $this->addSql('CREATE INDEX idx_menu_item_project ON menu_item (project_id)');
        $this->addSql('CREATE TABLE creative_work_translation (name VARCHAR(220) DEFAULT NULL, text TEXT DEFAULT NULL, schema_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, locale VARCHAR(8) NOT NULL, id UUID NOT NULL, creative_work_id UUID NOT NULL, project_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_A0AB108FC2DBB54F ON creative_work_translation (creative_work_id)');
        $this->addSql('CREATE INDEX IDX_A0AB108F166D1F9C ON creative_work_translation (project_id)');
        $this->addSql('CREATE INDEX IDX_A0AB108FB03A8386 ON creative_work_translation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_A0AB108F896DBBDE ON creative_work_translation (updated_by_id)');
        $this->addSql('CREATE INDEX idx_creative_work_translation_project_locale ON creative_work_translation (project_id, locale)');
        $this->addSql('CREATE UNIQUE INDEX uniq_creative_work_translation_locale ON creative_work_translation (creative_work_id, locale)');
        $this->addSql('CREATE TABLE menu_item_translation (label VARCHAR(160) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, locale VARCHAR(8) NOT NULL, id UUID NOT NULL, menu_item_id UUID NOT NULL, project_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_683EE3A69AB44FE0 ON menu_item_translation (menu_item_id)');
        $this->addSql('CREATE INDEX IDX_683EE3A6166D1F9C ON menu_item_translation (project_id)');
        $this->addSql('CREATE INDEX IDX_683EE3A6B03A8386 ON menu_item_translation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_683EE3A6896DBBDE ON menu_item_translation (updated_by_id)');
        $this->addSql('CREATE INDEX idx_menu_item_translation_project_locale ON menu_item_translation (project_id, locale)');
        $this->addSql('CREATE UNIQUE INDEX uniq_menu_item_translation_locale ON menu_item_translation (menu_item_id, locale)');
        $this->addSql('ALTER TABLE creative_work ADD CONSTRAINT FK_16B9077AB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work ADD CONSTRAINT FK_16B9077A896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work ADD CONSTRAINT FK_16B9077A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work_translation ADD CONSTRAINT FK_A0AB108FC2DBB54F FOREIGN KEY (creative_work_id) REFERENCES creative_work (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work_translation ADD CONSTRAINT FK_A0AB108F166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work_translation ADD CONSTRAINT FK_A0AB108FB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work_translation ADD CONSTRAINT FK_A0AB108F896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item_translation ADD CONSTRAINT FK_683EE3A69AB44FE0 FOREIGN KEY (menu_item_id) REFERENCES menu_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item_translation ADD CONSTRAINT FK_683EE3A6166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item_translation ADD CONSTRAINT FK_683EE3A6B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item_translation ADD CONSTRAINT FK_683EE3A6896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }
}
