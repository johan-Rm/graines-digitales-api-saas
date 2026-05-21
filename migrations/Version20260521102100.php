<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260521102100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds accommodation domain resources and structured real estate fixture relations.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accommodation (identifier VARCHAR(180) NOT NULL, status VARCHAR(255) NOT NULL, year_built INT DEFAULT NULL, area_size INT DEFAULT NULL, area_terrace INT DEFAULT NULL, floor_size VARCHAR(80) DEFAULT NULL, land_area VARCHAR(80) DEFAULT NULL, level BOOLEAN DEFAULT NULL, number_of_rooms INT DEFAULT NULL, number_of_bedrooms INT DEFAULT NULL, number_of_bathrooms_total INT DEFAULT NULL, number_of_garages INT DEFAULT NULL, occupancy INT DEFAULT NULL, is_active BOOLEAN NOT NULL, offer_price VARCHAR(80) DEFAULT NULL, offer_price_currency VARCHAR(8) DEFAULT NULL, offer_price_specification VARCHAR(160) DEFAULT NULL, offer_availability VARCHAR(120) DEFAULT NULL, real_estate_agent_identifier VARCHAR(120) DEFAULT NULL, real_estate_agent_name VARCHAR(180) DEFAULT NULL, real_estate_agent_phone VARCHAR(80) DEFAULT NULL, real_estate_agent_email VARCHAR(180) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, category_id UUID NOT NULL, real_estate_listing_id UUID NOT NULL, place_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2D38541212469DE2 ON accommodation (category_id)');
        $this->addSql('CREATE INDEX IDX_2D385412DA241944 ON accommodation (real_estate_listing_id)');
        $this->addSql('CREATE INDEX IDX_2D385412DA6A219 ON accommodation (place_id)');
        $this->addSql('CREATE INDEX IDX_2D385412B03A8386 ON accommodation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_2D385412896DBBDE ON accommodation (updated_by_id)');
        $this->addSql('CREATE INDEX idx_accommodation_project ON accommodation (project_id)');
        $this->addSql('CREATE INDEX idx_accommodation_project_publication ON accommodation (project_id, status, is_active, deleted_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_accommodation_project_identifier ON accommodation (project_id, identifier)');
        $this->addSql('CREATE TABLE accommodation_amenity_feature (accommodation_id UUID NOT NULL, category_code_id UUID NOT NULL, PRIMARY KEY (accommodation_id, category_code_id))');
        $this->addSql('CREATE INDEX IDX_376C0B2F8F3692CD ON accommodation_amenity_feature (accommodation_id)');
        $this->addSql('CREATE INDEX IDX_376C0B2FE002BE69 ON accommodation_amenity_feature (category_code_id)');
        $this->addSql('CREATE TABLE accommodation_tag (accommodation_id UUID NOT NULL, category_code_id UUID NOT NULL, PRIMARY KEY (accommodation_id, category_code_id))');
        $this->addSql('CREATE INDEX IDX_8D07578F8F3692CD ON accommodation_tag (accommodation_id)');
        $this->addSql('CREATE INDEX IDX_8D07578FE002BE69 ON accommodation_tag (category_code_id)');
        $this->addSql('CREATE TABLE accommodation_media (position INT NOT NULL, caption VARCHAR(255) DEFAULT NULL, keywords JSON NOT NULL, id UUID NOT NULL, accommodation_id UUID NOT NULL, media_object_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_669382F68F3692CD ON accommodation_media (accommodation_id)');
        $this->addSql('CREATE INDEX IDX_669382F664DE5A5 ON accommodation_media (media_object_id)');
        $this->addSql('CREATE INDEX idx_accommodation_media_position ON accommodation_media (accommodation_id, position)');
        $this->addSql('CREATE TABLE accommodation_translation (slug VARCHAR(180) NOT NULL, name VARCHAR(220) DEFAULT NULL, label VARCHAR(160) DEFAULT NULL, highlight VARCHAR(260) DEFAULT NULL, body TEXT DEFAULT NULL, review TEXT DEFAULT NULL, meta_title VARCHAR(220) DEFAULT NULL, meta_description VARCHAR(320) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, locale VARCHAR(8) NOT NULL, id UUID NOT NULL, accommodation_id UUID NOT NULL, project_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_CA0A8D6C8F3692CD ON accommodation_translation (accommodation_id)');
        $this->addSql('CREATE INDEX IDX_CA0A8D6C166D1F9C ON accommodation_translation (project_id)');
        $this->addSql('CREATE INDEX IDX_CA0A8D6CB03A8386 ON accommodation_translation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_CA0A8D6C896DBBDE ON accommodation_translation (updated_by_id)');
        $this->addSql('CREATE INDEX idx_accommodation_translation_project_locale ON accommodation_translation (project_id, locale)');
        $this->addSql('CREATE INDEX idx_accommodation_translation_slug ON accommodation_translation (slug)');
        $this->addSql('CREATE UNIQUE INDEX uniq_accommodation_translation_locale ON accommodation_translation (accommodation_id, locale)');
        $this->addSql('CREATE UNIQUE INDEX uniq_accommodation_translation_project_slug_locale ON accommodation_translation (project_id, slug, locale)');
        $this->addSql('ALTER TABLE accommodation ADD CONSTRAINT FK_2D38541212469DE2 FOREIGN KEY (category_id) REFERENCES category_code (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation ADD CONSTRAINT FK_2D385412DA241944 FOREIGN KEY (real_estate_listing_id) REFERENCES category_code (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation ADD CONSTRAINT FK_2D385412DA6A219 FOREIGN KEY (place_id) REFERENCES category_code (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation ADD CONSTRAINT FK_2D385412B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation ADD CONSTRAINT FK_2D385412896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation ADD CONSTRAINT FK_2D385412166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation_amenity_feature ADD CONSTRAINT FK_376C0B2F8F3692CD FOREIGN KEY (accommodation_id) REFERENCES accommodation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE accommodation_amenity_feature ADD CONSTRAINT FK_376C0B2FE002BE69 FOREIGN KEY (category_code_id) REFERENCES category_code (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE accommodation_tag ADD CONSTRAINT FK_8D07578F8F3692CD FOREIGN KEY (accommodation_id) REFERENCES accommodation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE accommodation_tag ADD CONSTRAINT FK_8D07578FE002BE69 FOREIGN KEY (category_code_id) REFERENCES category_code (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE accommodation_media ADD CONSTRAINT FK_669382F68F3692CD FOREIGN KEY (accommodation_id) REFERENCES accommodation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation_media ADD CONSTRAINT FK_669382F664DE5A5 FOREIGN KEY (media_object_id) REFERENCES media_object (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation_translation ADD CONSTRAINT FK_CA0A8D6C8F3692CD FOREIGN KEY (accommodation_id) REFERENCES accommodation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation_translation ADD CONSTRAINT FK_CA0A8D6C166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation_translation ADD CONSTRAINT FK_CA0A8D6CB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation_translation ADD CONSTRAINT FK_CA0A8D6C896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('DROP INDEX uniq_category_code_project_code');
        $this->addSql("ALTER TABLE category_code ADD in_code_set VARCHAR(120) NOT NULL DEFAULT 'content-category'");
        $this->addSql('ALTER TABLE category_code ALTER in_code_set DROP DEFAULT');
        $this->addSql('CREATE UNIQUE INDEX uniq_category_code_project_set_code ON category_code (project_id, in_code_set, code)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accommodation DROP CONSTRAINT FK_2D38541212469DE2');
        $this->addSql('ALTER TABLE accommodation DROP CONSTRAINT FK_2D385412DA241944');
        $this->addSql('ALTER TABLE accommodation DROP CONSTRAINT FK_2D385412DA6A219');
        $this->addSql('ALTER TABLE accommodation DROP CONSTRAINT FK_2D385412B03A8386');
        $this->addSql('ALTER TABLE accommodation DROP CONSTRAINT FK_2D385412896DBBDE');
        $this->addSql('ALTER TABLE accommodation DROP CONSTRAINT FK_2D385412166D1F9C');
        $this->addSql('ALTER TABLE accommodation_amenity_feature DROP CONSTRAINT FK_376C0B2F8F3692CD');
        $this->addSql('ALTER TABLE accommodation_amenity_feature DROP CONSTRAINT FK_376C0B2FE002BE69');
        $this->addSql('ALTER TABLE accommodation_tag DROP CONSTRAINT FK_8D07578F8F3692CD');
        $this->addSql('ALTER TABLE accommodation_tag DROP CONSTRAINT FK_8D07578FE002BE69');
        $this->addSql('ALTER TABLE accommodation_media DROP CONSTRAINT FK_669382F68F3692CD');
        $this->addSql('ALTER TABLE accommodation_media DROP CONSTRAINT FK_669382F664DE5A5');
        $this->addSql('ALTER TABLE accommodation_translation DROP CONSTRAINT FK_CA0A8D6C8F3692CD');
        $this->addSql('ALTER TABLE accommodation_translation DROP CONSTRAINT FK_CA0A8D6C166D1F9C');
        $this->addSql('ALTER TABLE accommodation_translation DROP CONSTRAINT FK_CA0A8D6CB03A8386');
        $this->addSql('ALTER TABLE accommodation_translation DROP CONSTRAINT FK_CA0A8D6C896DBBDE');
        $this->addSql('DROP TABLE accommodation');
        $this->addSql('DROP TABLE accommodation_amenity_feature');
        $this->addSql('DROP TABLE accommodation_tag');
        $this->addSql('DROP TABLE accommodation_media');
        $this->addSql('DROP TABLE accommodation_translation');
        $this->addSql('DROP INDEX uniq_category_code_project_set_code');
        $this->addSql('ALTER TABLE category_code DROP in_code_set');
        $this->addSql('CREATE UNIQUE INDEX uniq_category_code_project_code ON category_code (project_id, code)');
    }
}
