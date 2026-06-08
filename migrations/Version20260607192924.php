<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260607192924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add accommodation hasPart components (AccommodationComponent + translations).';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE accommodation_component (additional_type VARCHAR(255) NOT NULL, position INT NOT NULL, associated_media JSON NOT NULL, meta JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, accommodation_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, project_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_F0C551718F3692CD ON accommodation_component (accommodation_id)');
        $this->addSql('CREATE INDEX IDX_F0C55171B03A8386 ON accommodation_component (created_by_id)');
        $this->addSql('CREATE INDEX IDX_F0C55171896DBBDE ON accommodation_component (updated_by_id)');
        $this->addSql('CREATE INDEX idx_accommodation_component_position ON accommodation_component (accommodation_id, position)');
        $this->addSql('CREATE INDEX idx_accommodation_component_project ON accommodation_component (project_id)');
        $this->addSql('CREATE TABLE accommodation_component_translation (name VARCHAR(180) DEFAULT NULL, headline VARCHAR(260) DEFAULT NULL, text TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, locale VARCHAR(8) NOT NULL, id UUID NOT NULL, component_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_E46B3E91E2ABAFFF ON accommodation_component_translation (component_id)');
        $this->addSql('CREATE INDEX IDX_E46B3E91B03A8386 ON accommodation_component_translation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_E46B3E91896DBBDE ON accommodation_component_translation (updated_by_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_accommodation_component_translation_locale ON accommodation_component_translation (component_id, locale)');
        $this->addSql('ALTER TABLE accommodation_component ADD CONSTRAINT FK_F0C551718F3692CD FOREIGN KEY (accommodation_id) REFERENCES accommodation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation_component ADD CONSTRAINT FK_F0C55171B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation_component ADD CONSTRAINT FK_F0C55171896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation_component ADD CONSTRAINT FK_F0C55171166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation_component_translation ADD CONSTRAINT FK_E46B3E91E2ABAFFF FOREIGN KEY (component_id) REFERENCES accommodation_component (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation_component_translation ADD CONSTRAINT FK_E46B3E91B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE accommodation_component_translation ADD CONSTRAINT FK_E46B3E91896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accommodation_component DROP CONSTRAINT FK_F0C551718F3692CD');
        $this->addSql('ALTER TABLE accommodation_component DROP CONSTRAINT FK_F0C55171B03A8386');
        $this->addSql('ALTER TABLE accommodation_component DROP CONSTRAINT FK_F0C55171896DBBDE');
        $this->addSql('ALTER TABLE accommodation_component DROP CONSTRAINT FK_F0C55171166D1F9C');
        $this->addSql('ALTER TABLE accommodation_component_translation DROP CONSTRAINT FK_E46B3E91E2ABAFFF');
        $this->addSql('ALTER TABLE accommodation_component_translation DROP CONSTRAINT FK_E46B3E91B03A8386');
        $this->addSql('ALTER TABLE accommodation_component_translation DROP CONSTRAINT FK_E46B3E91896DBBDE');
        $this->addSql('DROP TABLE accommodation_component');
        $this->addSql('DROP TABLE accommodation_component_translation');
    }
}
