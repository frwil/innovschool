<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002123006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_permission (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, label VARCHAR(255) NOT NULL, category VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_CA770A2B5E237E06 (name), INDEX idx_access_permission_category (category), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE access_role (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, label VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, is_system TINYINT(1) NOT NULL, locked TINYINT(1) NOT NULL, data_scope VARCHAR(20) NOT NULL, UNIQUE INDEX UNIQ_A1B0EC6C5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE access_role_permission (id INT AUTO_INCREMENT NOT NULL, role_id INT NOT NULL, permission_id INT NOT NULL, is_default TINYINT(1) NOT NULL, is_mandatory TINYINT(1) NOT NULL, INDEX IDX_DA91AC5DD60322AC (role_id), INDEX IDX_DA91AC5DFED90CCA (permission_id), UNIQUE INDEX uniq_role_permission (role_id, permission_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE access_role_permission ADD CONSTRAINT FK_DA91AC5DD60322AC FOREIGN KEY (role_id) REFERENCES access_role (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE access_role_permission ADD CONSTRAINT FK_DA91AC5DFED90CCA FOREIGN KEY (permission_id) REFERENCES access_permission (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_role_permission DROP FOREIGN KEY FK_DA91AC5DD60322AC');
        $this->addSql('ALTER TABLE access_role_permission DROP FOREIGN KEY FK_DA91AC5DFED90CCA');
        $this->addSql('DROP TABLE access_permission');
        $this->addSql('DROP TABLE access_role');
        $this->addSql('DROP TABLE access_role_permission');
    }
}
