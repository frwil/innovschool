<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Raison obligatoire des désactivations / réactivations de comptes
 * (user_status_history.reason). Défaut '' pour que l'ALTER passe sur les
 * bases ayant déjà des lignes ; le contrôleur, lui, exige une raison non vide.
 */
final class Version20261009100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Raison des changements de statut de compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user_status_history ADD reason VARCHAR(255) NOT NULL DEFAULT ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_status_history DROP reason');
    }
}
