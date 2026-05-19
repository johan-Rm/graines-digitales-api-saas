<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260519154137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds project locales and moves localized content fields to resource translation tables.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article_translation (slug VARCHAR(180) NOT NULL, headline VARCHAR(220) NOT NULL, body TEXT DEFAULT NULL, schema_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, locale VARCHAR(8) NOT NULL, id UUID NOT NULL, article_id UUID NOT NULL, project_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2EEA2F087294869C ON article_translation (article_id)');
        $this->addSql('CREATE INDEX IDX_2EEA2F08166D1F9C ON article_translation (project_id)');
        $this->addSql('CREATE INDEX IDX_2EEA2F08B03A8386 ON article_translation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_2EEA2F08896DBBDE ON article_translation (updated_by_id)');
        $this->addSql('CREATE INDEX idx_article_translation_project_locale ON article_translation (project_id, locale)');
        $this->addSql('CREATE INDEX idx_article_translation_slug ON article_translation (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_article_translation_locale ON article_translation (article_id, locale)');
        $this->addSql('CREATE UNIQUE INDEX uniq_article_translation_project_slug_locale ON article_translation (project_id, slug, locale)');
        $this->addSql('CREATE TABLE category_code_translation (label VARCHAR(180) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, locale VARCHAR(8) NOT NULL, id UUID NOT NULL, category_code_id UUID NOT NULL, project_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7815155BE002BE69 ON category_code_translation (category_code_id)');
        $this->addSql('CREATE INDEX IDX_7815155B166D1F9C ON category_code_translation (project_id)');
        $this->addSql('CREATE INDEX IDX_7815155BB03A8386 ON category_code_translation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_7815155B896DBBDE ON category_code_translation (updated_by_id)');
        $this->addSql('CREATE INDEX idx_category_code_translation_project_locale ON category_code_translation (project_id, locale)');
        $this->addSql('CREATE UNIQUE INDEX uniq_category_code_translation_locale ON category_code_translation (category_code_id, locale)');
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
        $this->addSql('CREATE TABLE web_page_translation (slug VARCHAR(180) NOT NULL, meta_title VARCHAR(220) DEFAULT NULL, meta_description VARCHAR(320) DEFAULT NULL, headline VARCHAR(220) DEFAULT NULL, body TEXT DEFAULT NULL, schema_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, locale VARCHAR(8) NOT NULL, id UUID NOT NULL, web_page_id UUID NOT NULL, project_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_55B14D67E4E9CD7A ON web_page_translation (web_page_id)');
        $this->addSql('CREATE INDEX IDX_55B14D67166D1F9C ON web_page_translation (project_id)');
        $this->addSql('CREATE INDEX IDX_55B14D67B03A8386 ON web_page_translation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_55B14D67896DBBDE ON web_page_translation (updated_by_id)');
        $this->addSql('CREATE INDEX idx_web_page_translation_project_locale ON web_page_translation (project_id, locale)');
        $this->addSql('CREATE INDEX idx_web_page_translation_slug ON web_page_translation (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_web_page_translation_locale ON web_page_translation (web_page_id, locale)');
        $this->addSql('CREATE UNIQUE INDEX uniq_web_page_translation_project_slug_locale ON web_page_translation (project_id, slug, locale)');
        $this->addSql('ALTER TABLE article_translation ADD CONSTRAINT FK_2EEA2F087294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE article_translation ADD CONSTRAINT FK_2EEA2F08166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE article_translation ADD CONSTRAINT FK_2EEA2F08B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE article_translation ADD CONSTRAINT FK_2EEA2F08896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE category_code_translation ADD CONSTRAINT FK_7815155BE002BE69 FOREIGN KEY (category_code_id) REFERENCES category_code (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE category_code_translation ADD CONSTRAINT FK_7815155B166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE category_code_translation ADD CONSTRAINT FK_7815155BB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE category_code_translation ADD CONSTRAINT FK_7815155B896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work_translation ADD CONSTRAINT FK_A0AB108FC2DBB54F FOREIGN KEY (creative_work_id) REFERENCES creative_work (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work_translation ADD CONSTRAINT FK_A0AB108F166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work_translation ADD CONSTRAINT FK_A0AB108FB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work_translation ADD CONSTRAINT FK_A0AB108F896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item_translation ADD CONSTRAINT FK_683EE3A69AB44FE0 FOREIGN KEY (menu_item_id) REFERENCES menu_item (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item_translation ADD CONSTRAINT FK_683EE3A6166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item_translation ADD CONSTRAINT FK_683EE3A6B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item_translation ADD CONSTRAINT FK_683EE3A6896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE web_page_translation ADD CONSTRAINT FK_55B14D67E4E9CD7A FOREIGN KEY (web_page_id) REFERENCES web_page (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE web_page_translation ADD CONSTRAINT FK_55B14D67166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE web_page_translation ADD CONSTRAINT FK_55B14D67B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE web_page_translation ADD CONSTRAINT FK_55B14D67896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql("ALTER TABLE project ADD source_locale VARCHAR(8) NOT NULL DEFAULT 'fr'");
        $this->addSql("ALTER TABLE project ADD enabled_locales JSON NOT NULL DEFAULT '[\"fr\"]'");

        $this->addSql('INSERT INTO article_translation (id, article_id, project_id, locale, slug, headline, body, schema_data, created_at, updated_at, published_at, deleted_at, created_by_id, updated_by_id) SELECT gen_random_uuid(), id, project_id, \'fr\', slug, headline, body, schema_data, created_at, updated_at, published_at, deleted_at, created_by_id, updated_by_id FROM article');
        $this->addSql('INSERT INTO category_code_translation (id, category_code_id, project_id, locale, label, created_at, updated_at, published_at, deleted_at, created_by_id, updated_by_id) SELECT gen_random_uuid(), id, project_id, \'fr\', label, created_at, updated_at, published_at, deleted_at, created_by_id, updated_by_id FROM category_code');
        $this->addSql('INSERT INTO creative_work_translation (id, creative_work_id, project_id, locale, name, text, schema_data, created_at, updated_at, published_at, deleted_at, created_by_id, updated_by_id) SELECT gen_random_uuid(), id, project_id, \'fr\', name, text, schema_data, created_at, updated_at, published_at, deleted_at, created_by_id, updated_by_id FROM creative_work');
        $this->addSql('INSERT INTO menu_item_translation (id, menu_item_id, project_id, locale, label, created_at, updated_at, published_at, deleted_at, created_by_id, updated_by_id) SELECT gen_random_uuid(), id, project_id, \'fr\', label, created_at, updated_at, published_at, deleted_at, created_by_id, updated_by_id FROM menu_item');
        $this->addSql('INSERT INTO web_page_translation (id, web_page_id, project_id, locale, slug, meta_title, meta_description, headline, body, schema_data, created_at, updated_at, published_at, deleted_at, created_by_id, updated_by_id) SELECT gen_random_uuid(), id, project_id, in_language, slug, meta_title, meta_description, headline, body, schema_data, created_at, updated_at, published_at, deleted_at, created_by_id, updated_by_id FROM web_page');

        $this->addSql('DROP INDEX idx_article_slug');
        $this->addSql('ALTER TABLE article DROP slug');
        $this->addSql('ALTER TABLE article DROP headline');
        $this->addSql('ALTER TABLE article DROP body');
        $this->addSql('ALTER TABLE article DROP schema_data');
        $this->addSql('ALTER TABLE category_code DROP label');
        $this->addSql('ALTER TABLE creative_work DROP name');
        $this->addSql('ALTER TABLE creative_work DROP text');
        $this->addSql('ALTER TABLE creative_work DROP schema_data');
        $this->addSql('ALTER TABLE menu_item DROP label');
        $this->addSql('DROP INDEX idx_web_page_slug');
        $this->addSql('ALTER TABLE web_page DROP slug');
        $this->addSql('ALTER TABLE web_page DROP in_language');
        $this->addSql('ALTER TABLE web_page DROP meta_title');
        $this->addSql('ALTER TABLE web_page DROP meta_description');
        $this->addSql('ALTER TABLE web_page DROP headline');
        $this->addSql('ALTER TABLE web_page DROP body');
        $this->addSql('ALTER TABLE web_page DROP schema_data');
        $this->addSql('ALTER TABLE project ALTER source_locale DROP DEFAULT');
        $this->addSql('ALTER TABLE project ALTER enabled_locales DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article_translation DROP CONSTRAINT FK_2EEA2F087294869C');
        $this->addSql('ALTER TABLE article_translation DROP CONSTRAINT FK_2EEA2F08166D1F9C');
        $this->addSql('ALTER TABLE article_translation DROP CONSTRAINT FK_2EEA2F08B03A8386');
        $this->addSql('ALTER TABLE article_translation DROP CONSTRAINT FK_2EEA2F08896DBBDE');
        $this->addSql('ALTER TABLE category_code_translation DROP CONSTRAINT FK_7815155BE002BE69');
        $this->addSql('ALTER TABLE category_code_translation DROP CONSTRAINT FK_7815155B166D1F9C');
        $this->addSql('ALTER TABLE category_code_translation DROP CONSTRAINT FK_7815155BB03A8386');
        $this->addSql('ALTER TABLE category_code_translation DROP CONSTRAINT FK_7815155B896DBBDE');
        $this->addSql('ALTER TABLE creative_work_translation DROP CONSTRAINT FK_A0AB108FC2DBB54F');
        $this->addSql('ALTER TABLE creative_work_translation DROP CONSTRAINT FK_A0AB108F166D1F9C');
        $this->addSql('ALTER TABLE creative_work_translation DROP CONSTRAINT FK_A0AB108FB03A8386');
        $this->addSql('ALTER TABLE creative_work_translation DROP CONSTRAINT FK_A0AB108F896DBBDE');
        $this->addSql('ALTER TABLE menu_item_translation DROP CONSTRAINT FK_683EE3A69AB44FE0');
        $this->addSql('ALTER TABLE menu_item_translation DROP CONSTRAINT FK_683EE3A6166D1F9C');
        $this->addSql('ALTER TABLE menu_item_translation DROP CONSTRAINT FK_683EE3A6B03A8386');
        $this->addSql('ALTER TABLE menu_item_translation DROP CONSTRAINT FK_683EE3A6896DBBDE');
        $this->addSql('ALTER TABLE web_page_translation DROP CONSTRAINT FK_55B14D67E4E9CD7A');
        $this->addSql('ALTER TABLE web_page_translation DROP CONSTRAINT FK_55B14D67166D1F9C');
        $this->addSql('ALTER TABLE web_page_translation DROP CONSTRAINT FK_55B14D67B03A8386');
        $this->addSql('ALTER TABLE web_page_translation DROP CONSTRAINT FK_55B14D67896DBBDE');
        $this->addSql('DROP TABLE article_translation');
        $this->addSql('DROP TABLE category_code_translation');
        $this->addSql('DROP TABLE creative_work_translation');
        $this->addSql('DROP TABLE menu_item_translation');
        $this->addSql('DROP TABLE web_page_translation');
        $this->addSql('ALTER TABLE article ADD slug VARCHAR(180) NOT NULL');
        $this->addSql('ALTER TABLE article ADD headline VARCHAR(220) NOT NULL');
        $this->addSql('ALTER TABLE article ADD body TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE article ADD schema_data JSON DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_article_slug ON article (slug)');
        $this->addSql('ALTER TABLE category_code ADD label VARCHAR(180) NOT NULL');
        $this->addSql('ALTER TABLE creative_work ADD name VARCHAR(220) DEFAULT NULL');
        $this->addSql('ALTER TABLE creative_work ADD text TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE creative_work ADD schema_data JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE menu_item ADD label VARCHAR(160) NOT NULL');
        $this->addSql('ALTER TABLE project DROP source_locale');
        $this->addSql('ALTER TABLE project DROP enabled_locales');
        $this->addSql('ALTER TABLE web_page ADD slug VARCHAR(180) NOT NULL');
        $this->addSql('ALTER TABLE web_page ADD in_language VARCHAR(8) NOT NULL');
        $this->addSql('ALTER TABLE web_page ADD meta_title VARCHAR(220) DEFAULT NULL');
        $this->addSql('ALTER TABLE web_page ADD meta_description VARCHAR(320) DEFAULT NULL');
        $this->addSql('ALTER TABLE web_page ADD headline VARCHAR(220) DEFAULT NULL');
        $this->addSql('ALTER TABLE web_page ADD body TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE web_page ADD schema_data JSON DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_web_page_slug ON web_page (slug)');
    }
}
