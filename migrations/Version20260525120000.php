<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260525120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace flat accommodation offer and real estate agent fields with a JSON offer object.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE accommodation ADD offer JSON NOT NULL DEFAULT '{}'");
        $this->addSql("UPDATE accommodation SET offer = json_strip_nulls(json_build_object('price', offer_price, 'priceCurrency', offer_price_currency, 'priceSpecification', offer_price_specification, 'availability', offer_availability))");
        $this->addSql('ALTER TABLE accommodation ALTER offer DROP DEFAULT');
        $this->addSql('ALTER TABLE accommodation DROP offer_price');
        $this->addSql('ALTER TABLE accommodation DROP offer_price_currency');
        $this->addSql('ALTER TABLE accommodation DROP offer_price_specification');
        $this->addSql('ALTER TABLE accommodation DROP offer_availability');
        $this->addSql('ALTER TABLE accommodation DROP real_estate_agent_identifier');
        $this->addSql('ALTER TABLE accommodation DROP real_estate_agent_name');
        $this->addSql('ALTER TABLE accommodation DROP real_estate_agent_phone');
        $this->addSql('ALTER TABLE accommodation DROP real_estate_agent_email');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE accommodation ADD offer_price VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD offer_price_currency VARCHAR(8) DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD offer_price_specification VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD offer_availability VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD real_estate_agent_identifier VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD real_estate_agent_name VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD real_estate_agent_phone VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE accommodation ADD real_estate_agent_email VARCHAR(180) DEFAULT NULL');
        $this->addSql("UPDATE accommodation SET offer_price = offer->>'price', offer_price_currency = offer->>'priceCurrency', offer_price_specification = offer->>'priceSpecification', offer_availability = offer->>'availability'");
        $this->addSql('ALTER TABLE accommodation DROP offer');
    }
}
