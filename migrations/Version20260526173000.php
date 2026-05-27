<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add translatable caption and main entity metadata to media objects.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_object ADD main_entity VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE TABLE media_object_translation (caption VARCHAR(255) DEFAULT NULL, locale VARCHAR(8) NOT NULL, id UUID NOT NULL, media_object_id UUID NOT NULL, project_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_5F81631964DE5A5 ON media_object_translation (media_object_id)');
        $this->addSql('CREATE INDEX IDX_5F816319166D1F9C ON media_object_translation (project_id)');
        $this->addSql('CREATE INDEX IDX_5F816319B03A8386 ON media_object_translation (created_by_id)');
        $this->addSql('CREATE INDEX IDX_5F816319896DBBDE ON media_object_translation (updated_by_id)');
        $this->addSql('CREATE INDEX idx_media_object_translation_project_locale ON media_object_translation (project_id, locale)');
        $this->addSql('CREATE UNIQUE INDEX uniq_media_object_translation_locale ON media_object_translation (media_object_id, locale)');
        $this->addSql('ALTER TABLE media_object_translation ADD CONSTRAINT FK_5F81631964DE5A5 FOREIGN KEY (media_object_id) REFERENCES media_object (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE media_object_translation ADD CONSTRAINT FK_5F816319166D1F9C FOREIGN KEY (project_id) REFERENCES project (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE media_object_translation ADD CONSTRAINT FK_5F816319B03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE media_object_translation ADD CONSTRAINT FK_5F816319896DBBDE FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_object_translation DROP CONSTRAINT FK_5F81631964DE5A5');
        $this->addSql('ALTER TABLE media_object_translation DROP CONSTRAINT FK_5F816319166D1F9C');
        $this->addSql('ALTER TABLE media_object_translation DROP CONSTRAINT FK_5F816319B03A8386');
        $this->addSql('ALTER TABLE media_object_translation DROP CONSTRAINT FK_5F816319896DBBDE');
        $this->addSql('DROP TABLE media_object_translation');
        $this->addSql('ALTER TABLE media_object DROP main_entity');
    }
}
