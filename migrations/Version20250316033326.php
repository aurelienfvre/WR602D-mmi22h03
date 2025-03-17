<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250316033326 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pdf_generation_queue ADD user_id INT DEFAULT NULL, CHANGE status status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE pdf_generation_queue ADD CONSTRAINT FK_64622E1A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_64622E1A76ED395 ON pdf_generation_queue (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE pdf_generation_queue DROP FOREIGN KEY FK_64622E1A76ED395');
        $this->addSql('DROP INDEX IDX_64622E1A76ED395 ON pdf_generation_queue');
        $this->addSql('ALTER TABLE pdf_generation_queue DROP user_id, CHANGE status status VARCHAR(255) NOT NULL');
    }
}
