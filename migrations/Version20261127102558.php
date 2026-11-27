<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261127102558 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fund_transfer ADD agency_id INT NOT NULL');
        $this->addSql('ALTER TABLE fund_transfer ALTER amount TYPE NUMERIC(15, 2)');
        $this->addSql('ALTER TABLE fund_transfer ALTER recieved_by_id DROP NOT NULL');
        $this->addSql('ALTER TABLE fund_transfer ADD CONSTRAINT FK_4FD85EDFCDEADB2A FOREIGN KEY (agency_id) REFERENCES agency (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_4FD85EDFCDEADB2A ON fund_transfer (agency_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fund_transfer DROP CONSTRAINT FK_4FD85EDFCDEADB2A');
        $this->addSql('DROP INDEX IDX_4FD85EDFCDEADB2A');
        $this->addSql('ALTER TABLE fund_transfer DROP agency_id');
        $this->addSql('ALTER TABLE fund_transfer ALTER amount TYPE DOUBLE PRECISION');
        $this->addSql('ALTER TABLE fund_transfer ALTER recieved_by_id SET NOT NULL');
    }
}
