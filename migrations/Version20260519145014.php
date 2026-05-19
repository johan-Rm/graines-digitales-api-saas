<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260519145014 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article (slug VARCHAR(180) NOT NULL, headline VARCHAR(220) NOT NULL, body TEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, schema_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_23A0E66B03A8386 ON article (created_by_id)');
        $this->addSql('CREATE INDEX IDX_23A0E66896DBBDE ON article (updated_by_id)');
        $this->addSql('CREATE INDEX idx_article_project ON article (project_id)');
        $this->addSql('CREATE INDEX idx_article_slug ON article (slug)');
        $this->addSql('CREATE TABLE category_code (code VARCHAR(120) NOT NULL, label VARCHAR(180) NOT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FC8E4ADFB03A8386 ON category_code (created_by_id)');
        $this->addSql('CREATE INDEX IDX_FC8E4ADF896DBBDE ON category_code (updated_by_id)');
        $this->addSql('CREATE INDEX idx_category_code_project ON category_code (project_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_category_code_project_code ON category_code (project_id, code)');
        $this->addSql('CREATE TABLE creative_work (identifier VARCHAR(180) NOT NULL, name VARCHAR(220) DEFAULT NULL, text TEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, schema_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_16B9077AB03A8386 ON creative_work (created_by_id)');
        $this->addSql('CREATE INDEX IDX_16B9077A896DBBDE ON creative_work (updated_by_id)');
        $this->addSql('CREATE INDEX idx_creative_work_project ON creative_work (project_id)');
        $this->addSql('CREATE TABLE media_object (file_path VARCHAR(255) DEFAULT NULL, mime_type VARCHAR(120) DEFAULT NULL, size INT DEFAULT NULL, original_filename VARCHAR(255) DEFAULT NULL, checksum_sha256 VARCHAR(64) DEFAULT NULL, status VARCHAR(255) NOT NULL, ai_suggested_filename VARCHAR(255) DEFAULT NULL, ai_suggested_alt VARCHAR(255) DEFAULT NULL, ai_suggested_description TEXT DEFAULT NULL, final_filename VARCHAR(255) DEFAULT NULL, final_alt VARCHAR(255) DEFAULT NULL, final_description TEXT DEFAULT NULL, ai_review_status VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_14D43132B03A8386 ON media_object (created_by_id)');
        $this->addSql('CREATE INDEX IDX_14D43132896DBBDE ON media_object (updated_by_id)');
        $this->addSql('CREATE INDEX idx_media_object_project ON media_object (project_id)');
        $this->addSql('CREATE INDEX idx_media_object_checksum ON media_object (checksum_sha256)');
        $this->addSql('CREATE TABLE menu_item (label VARCHAR(160) NOT NULL, url VARCHAR(220) NOT NULL, position INT NOT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D754D550B03A8386 ON menu_item (created_by_id)');
        $this->addSql('CREATE INDEX IDX_D754D550896DBBDE ON menu_item (updated_by_id)');
        $this->addSql('CREATE INDEX idx_menu_item_project ON menu_item (project_id)');
        $this->addSql('CREATE TABLE message (first_name VARCHAR(120) NOT NULL, last_name VARCHAR(120) DEFAULT NULL, email VARCHAR(254) NOT NULL, phone VARCHAR(40) DEFAULT NULL, message TEXT NOT NULL, property_value JSON DEFAULT NULL, website VARCHAR(200) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B6BD307FB03A8386 ON message (created_by_id)');
        $this->addSql('CREATE INDEX IDX_B6BD307F896DBBDE ON message (updated_by_id)');
        $this->addSql('CREATE INDEX idx_message_project ON message (project_id)');
        $this->addSql('CREATE TABLE organization (name VARCHAR(160) NOT NULL, slug VARCHAR(180) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C1EE637C989D9B62 ON organization (slug)');
        $this->addSql('CREATE INDEX IDX_C1EE637CB03A8386 ON organization (created_by_id)');
        $this->addSql('CREATE INDEX IDX_C1EE637C896DBBDE ON organization (updated_by_id)');
        $this->addSql('CREATE INDEX idx_organization_slug ON organization (slug)');
        $this->addSql('CREATE TABLE project (name VARCHAR(160) NOT NULL, slug VARCHAR(180) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, organization_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2FB3D0EEB03A8386 ON project (created_by_id)');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE896DBBDE ON project (updated_by_id)');
        $this->addSql('CREATE INDEX idx_project_organization ON project (organization_id)');
        $this->addSql('CREATE INDEX idx_project_slug ON project (slug)');
        $this->addSql('CREATE TABLE project_member (role VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, project_id UUID NOT NULL, user_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_67401132B03A8386 ON project_member (created_by_id)');
        $this->addSql('CREATE INDEX IDX_67401132896DBBDE ON project_member (updated_by_id)');
        $this->addSql('CREATE INDEX idx_project_member_project ON project_member (project_id)');
        $this->addSql('CREATE INDEX idx_project_member_user ON project_member (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_project_member_user ON project_member (project_id, user_id)');
        $this->addSql('CREATE TABLE refresh_token (refresh_token VARCHAR(128) NOT NULL, username VARCHAR(255) NOT NULL, valid TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, id INT GENERATED BY DEFAULT AS IDENTITY NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C74F2195C74F2195 ON refresh_token (refresh_token)');
        $this->addSql('CREATE TABLE "user" (email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('CREATE INDEX IDX_8D93D649B03A8386 ON "user" (created_by_id)');
        $this->addSql('CREATE INDEX IDX_8D93D649896DBBDE ON "user" (updated_by_id)');
        $this->addSql('CREATE INDEX idx_user_email ON "user" (email)');
        $this->addSql('CREATE TABLE web_page (slug VARCHAR(180) NOT NULL, in_language VARCHAR(8) NOT NULL, meta_title VARCHAR(220) DEFAULT NULL, meta_description VARCHAR(320) DEFAULT NULL, headline VARCHAR(220) DEFAULT NULL, body TEXT DEFAULT NULL, status VARCHAR(255) NOT NULL, schema_data JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_D008BBD5B03A8386 ON web_page (created_by_id)');
        $this->addSql('CREATE INDEX IDX_D008BBD5896DBBDE ON web_page (updated_by_id)');
        $this->addSql('CREATE INDEX idx_web_page_project ON web_page (project_id)');
        $this->addSql('CREATE INDEX idx_web_page_slug ON web_page (slug)');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE category_code ADD CONSTRAINT FK_FC8E4ADFB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE category_code ADD CONSTRAINT FK_FC8E4ADF896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE category_code ADD CONSTRAINT FK_FC8E4ADF166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work ADD CONSTRAINT FK_16B9077AB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work ADD CONSTRAINT FK_16B9077A896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE creative_work ADD CONSTRAINT FK_16B9077A166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE media_object ADD CONSTRAINT FK_14D43132B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE media_object ADD CONSTRAINT FK_14D43132896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE media_object ADD CONSTRAINT FK_14D43132166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE menu_item ADD CONSTRAINT FK_D754D550166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307F166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637CB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE organization ADD CONSTRAINT FK_C1EE637C896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EEB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_member ADD CONSTRAINT FK_67401132166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_member ADD CONSTRAINT FK_67401132A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_member ADD CONSTRAINT FK_67401132B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE project_member ADD CONSTRAINT FK_67401132896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT FK_8D93D649896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE web_page ADD CONSTRAINT FK_D008BBD5B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE web_page ADD CONSTRAINT FK_D008BBD5896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE web_page ADD CONSTRAINT FK_D008BBD5166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP CONSTRAINT FK_23A0E66B03A8386');
        $this->addSql('ALTER TABLE article DROP CONSTRAINT FK_23A0E66896DBBDE');
        $this->addSql('ALTER TABLE article DROP CONSTRAINT FK_23A0E66166D1F9C');
        $this->addSql('ALTER TABLE category_code DROP CONSTRAINT FK_FC8E4ADFB03A8386');
        $this->addSql('ALTER TABLE category_code DROP CONSTRAINT FK_FC8E4ADF896DBBDE');
        $this->addSql('ALTER TABLE category_code DROP CONSTRAINT FK_FC8E4ADF166D1F9C');
        $this->addSql('ALTER TABLE creative_work DROP CONSTRAINT FK_16B9077AB03A8386');
        $this->addSql('ALTER TABLE creative_work DROP CONSTRAINT FK_16B9077A896DBBDE');
        $this->addSql('ALTER TABLE creative_work DROP CONSTRAINT FK_16B9077A166D1F9C');
        $this->addSql('ALTER TABLE media_object DROP CONSTRAINT FK_14D43132B03A8386');
        $this->addSql('ALTER TABLE media_object DROP CONSTRAINT FK_14D43132896DBBDE');
        $this->addSql('ALTER TABLE media_object DROP CONSTRAINT FK_14D43132166D1F9C');
        $this->addSql('ALTER TABLE menu_item DROP CONSTRAINT FK_D754D550B03A8386');
        $this->addSql('ALTER TABLE menu_item DROP CONSTRAINT FK_D754D550896DBBDE');
        $this->addSql('ALTER TABLE menu_item DROP CONSTRAINT FK_D754D550166D1F9C');
        $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307FB03A8386');
        $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307F896DBBDE');
        $this->addSql('ALTER TABLE message DROP CONSTRAINT FK_B6BD307F166D1F9C');
        $this->addSql('ALTER TABLE organization DROP CONSTRAINT FK_C1EE637CB03A8386');
        $this->addSql('ALTER TABLE organization DROP CONSTRAINT FK_C1EE637C896DBBDE');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EE32C8A3DE');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EEB03A8386');
        $this->addSql('ALTER TABLE project DROP CONSTRAINT FK_2FB3D0EE896DBBDE');
        $this->addSql('ALTER TABLE project_member DROP CONSTRAINT FK_67401132166D1F9C');
        $this->addSql('ALTER TABLE project_member DROP CONSTRAINT FK_67401132A76ED395');
        $this->addSql('ALTER TABLE project_member DROP CONSTRAINT FK_67401132B03A8386');
        $this->addSql('ALTER TABLE project_member DROP CONSTRAINT FK_67401132896DBBDE');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649B03A8386');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT FK_8D93D649896DBBDE');
        $this->addSql('ALTER TABLE web_page DROP CONSTRAINT FK_D008BBD5B03A8386');
        $this->addSql('ALTER TABLE web_page DROP CONSTRAINT FK_D008BBD5896DBBDE');
        $this->addSql('ALTER TABLE web_page DROP CONSTRAINT FK_D008BBD5166D1F9C');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE category_code');
        $this->addSql('DROP TABLE creative_work');
        $this->addSql('DROP TABLE media_object');
        $this->addSql('DROP TABLE menu_item');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE organization');
        $this->addSql('DROP TABLE project');
        $this->addSql('DROP TABLE project_member');
        $this->addSql('DROP TABLE refresh_token');
        $this->addSql('DROP TABLE "user"');
        $this->addSql('DROP TABLE web_page');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
