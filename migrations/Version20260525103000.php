<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add organization memberships';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE organization_member (role VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, id UUID NOT NULL, organization_id UUID NOT NULL, user_id UUID NOT NULL, created_by_id UUID DEFAULT NULL, updated_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_756A2A8DB03A8386 ON organization_member (created_by_id)');
        $this->addSql('CREATE INDEX IDX_756A2A8D896DBBDE ON organization_member (updated_by_id)');
        $this->addSql('CREATE INDEX idx_organization_member_organization ON organization_member (organization_id)');
        $this->addSql('CREATE INDEX idx_organization_member_user ON organization_member (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_organization_member_user ON organization_member (organization_id, user_id)');
        $this->addSql('ALTER TABLE organization_member ADD CONSTRAINT FK_ORG_MEMBER_ORGANIZATION FOREIGN KEY (organization_id) REFERENCES organization (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE organization_member ADD CONSTRAINT FK_ORG_MEMBER_USER FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE organization_member ADD CONSTRAINT FK_ORG_MEMBER_CREATED_BY FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE organization_member ADD CONSTRAINT FK_ORG_MEMBER_UPDATED_BY FOREIGN KEY (updated_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE organization_member DROP CONSTRAINT FK_ORG_MEMBER_ORGANIZATION');
        $this->addSql('ALTER TABLE organization_member DROP CONSTRAINT FK_ORG_MEMBER_USER');
        $this->addSql('ALTER TABLE organization_member DROP CONSTRAINT FK_ORG_MEMBER_CREATED_BY');
        $this->addSql('ALTER TABLE organization_member DROP CONSTRAINT FK_ORG_MEMBER_UPDATED_BY');
        $this->addSql('DROP TABLE organization_member');
    }
}
