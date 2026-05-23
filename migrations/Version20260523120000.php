<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260523120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add accommodation qualities as PropertyValue children.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE property_value (name VARCHAR(120) NOT NULL, value INT NOT NULL, position INT NOT NULL, id UUID NOT NULL, accommodation_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_DB6499398F3692CD ON property_value (accommodation_id)');
        $this->addSql('CREATE INDEX idx_property_value_accommodation_position ON property_value (accommodation_id, position)');
        $this->addSql('ALTER TABLE property_value ADD CONSTRAINT FK_79725E258F3692CD FOREIGN KEY (accommodation_id) REFERENCES accommodation (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql("INSERT INTO property_value (id, accommodation_id, name, value, position) SELECT gen_random_uuid(), id, 'Confort', 0, 1 FROM accommodation");
        $this->addSql("INSERT INTO property_value (id, accommodation_id, name, value, position) SELECT gen_random_uuid(), id, 'Architecture', 0, 2 FROM accommodation");
        $this->addSql("INSERT INTO property_value (id, accommodation_id, name, value, position) SELECT gen_random_uuid(), id, 'Intérieur', 0, 3 FROM accommodation");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE property_value DROP CONSTRAINT FK_79725E258F3692CD');
        $this->addSql('DROP TABLE property_value');
    }
}
