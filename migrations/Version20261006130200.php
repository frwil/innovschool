<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Historique des désactivations / réactivations de comptes (user_status_history).
 */
final class Version20261006130200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Historique des désactivations / réactivations de comptes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_status_history (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, performed_by_id INT DEFAULT NULL, enabled TINYINT(1) NOT NULL, changed_at DATETIME NOT NULL, INDEX IDX_44EFD897A76ED395 (user_id), INDEX IDX_44EFD8972E65C292 (performed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user_status_history ADD CONSTRAINT FK_44EFD897A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_status_history ADD CONSTRAINT FK_44EFD8972E65C292 FOREIGN KEY (performed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_status_history DROP FOREIGN KEY FK_44EFD897A76ED395');
        $this->addSql('ALTER TABLE user_status_history DROP FOREIGN KEY FK_44EFD8972E65C292');
        $this->addSql('DROP TABLE user_status_history');
    }
}
