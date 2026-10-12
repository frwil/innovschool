<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Scopage de la désactivation par (année scolaire, école) : l'événement
 * d'historique porte désormais l'école et la période de l'action. Les
 * événements legacy (modèle antérieur, sans année ni école) sont rattachés à
 * la dernière année scolaire connue et à l'école de la dernière inscription
 * de l'élève.
 */
final class Version20261010100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'École et période des changements de statut de compte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_status_history ADD school_period_id INT DEFAULT NULL, ADD school_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user_status_history ADD CONSTRAINT FK_USH_SCHOOL_PERIOD FOREIGN KEY (school_period_id) REFERENCES school_period (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_status_history ADD CONSTRAINT FK_USH_SCHOOL FOREIGN KEY (school_id) REFERENCES school (id) ON DELETE SET NULL');

        // Événements legacy sans période : l'action datait de la dernière
        // année scolaire connue (modèle antérieur sans année ni école).
        $this->addSql(
            'UPDATE user_status_history ush
             SET ush.school_period_id = (SELECT sp.id FROM school_period sp ORDER BY sp.name DESC LIMIT 1)
             WHERE ush.school_period_id IS NULL'
        );

        // École des événements legacy : l'école de la dernière inscription de
        // l'élève (dernière période scolaire, puis dernière inscription).
        $this->addSql(
            'UPDATE user_status_history ush
             JOIN (
                 SELECT sc.student_id, scp.school_id
                 FROM student_class sc
                 JOIN school_class_period scp ON scp.id = sc.school_class_period_id
                 WHERE sc.id = (
                     SELECT sc3.id
                     FROM student_class sc3
                     JOIN school_class_period scp3 ON scp3.id = sc3.school_class_period_id
                     JOIN school_period sp3 ON sp3.id = scp3.period_id
                     WHERE sc3.student_id = sc.student_id
                     ORDER BY sp3.name DESC, sc3.id DESC
                     LIMIT 1
                 )
             ) ls ON ls.student_id = ush.user_id
             SET ush.school_id = ls.school_id
             WHERE ush.school_id IS NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_status_history DROP FOREIGN KEY FK_USH_SCHOOL_PERIOD');
        $this->addSql('ALTER TABLE user_status_history DROP FOREIGN KEY FK_USH_SCHOOL');
        $this->addSql('ALTER TABLE user_status_history DROP school_period_id, DROP school_id');
    }
}
