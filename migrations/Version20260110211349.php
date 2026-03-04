<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260110211349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D649208F64F1');
        $this->addSql('ALTER TABLE user_base_configuration DROP FOREIGN KEY FK_811D7695A76ED395');
        //$this->addSql('ALTER TABLE timetable_slot DROP FOREIGN KEY FK_3932CA6919F13D4D');
        //$this->addSql('ALTER TABLE timetable_slot DROP FOREIGN KEY FK_3932CA6923EDC87');
        $this->addSql('ALTER TABLE timetable_day DROP FOREIGN KEY timetable_day_ibfk_1');
        //$this->addSql('ALTER TABLE timetable_day DROP FOREIGN KEY FK_6600A558CC306847');
        $this->addSql('ALTER TABLE admission_reductions DROP FOREIGN KEY FK_BA9FE4EBCB944F1A');
        $this->addSql('ALTER TABLE admission_reductions DROP FOREIGN KEY FK_BA9FE4EBC32A47EE');
        $this->addSql('ALTER TABLE admission_reductions DROP FOREIGN KEY FK_BA9FE4EB9DC4B963');
        $this->addSql('ALTER TABLE admission_reductions DROP FOREIGN KEY FK_BA9FE4EB8373D28E');
        $this->addSql('ALTER TABLE admission_reductions DROP FOREIGN KEY FK_BA9FE4EB14463F54');
        $this->addSql('ALTER TABLE admission_reductions DROP FOREIGN KEY admission_reductions_ibfk_3');
        $this->addSql('ALTER TABLE admission_reductions DROP FOREIGN KEY admission_reductions_ibfk_2');
        $this->addSql('ALTER TABLE app_license DROP FOREIGN KEY app_license_ibfk_1');
        $this->addSql('ALTER TABLE class_occurence DROP FOREIGN KEY class_occurence_ibfk_1');
        $this->addSql('ALTER TABLE class_subject_module DROP FOREIGN KEY class_subject_module_ibfk_6');
        $this->addSql('ALTER TABLE class_subject_module DROP FOREIGN KEY class_subject_module_ibfk_5');
        $this->addSql('ALTER TABLE class_subject_module DROP FOREIGN KEY class_subject_module_ibfk_3');
        $this->addSql('ALTER TABLE class_subject_module DROP FOREIGN KEY class_subject_module_ibfk_2');
        $this->addSql('ALTER TABLE class_subject_module DROP FOREIGN KEY class_subject_module_ibfk_1');
        $this->addSql('ALTER TABLE classe DROP FOREIGN KEY FK_9621439DD823E37A');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY evaluation_ibfk_3');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY evaluation_ibfk_2');
        $this->addSql('ALTER TABLE evaluation DROP FOREIGN KEY evaluation_ibfk_1');
        $this->addSql('ALTER TABLE modalities_subscriptions DROP FOREIGN KEY FK_6582079CCC33534F');
        $this->addSql('ALTER TABLE modalities_subscriptions DROP FOREIGN KEY FK_6582079CCB944F1A');
        $this->addSql('ALTER TABLE modalities_subscriptions DROP FOREIGN KEY FK_6582079C9DC4B963');
        $this->addSql('ALTER TABLE modalities_subscriptions DROP FOREIGN KEY FK_6582079C14463F54');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840DF675F31B');
        $this->addSql('ALTER TABLE payment DROP FOREIGN KEY FK_6D28840D9C6BD325');
        $this->addSql('ALTER TABLE report_card_template DROP FOREIGN KEY report_card_template_ibfk_1');
        $this->addSql('ALTER TABLE school DROP FOREIGN KEY FK_F99EDABBB069795C');
        $this->addSql('ALTER TABLE school DROP FOREIGN KEY school_ibfk_2');
        $this->addSql('ALTER TABLE school DROP FOREIGN KEY school_ibfk_1');
        $this->addSql('ALTER TABLE school_class_admission_payments DROP FOREIGN KEY FK_33FDA223CC33534F');
        $this->addSql('ALTER TABLE school_class_admission_payments DROP FOREIGN KEY FK_33FDA223CB944F1A');
        $this->addSql('ALTER TABLE school_class_admission_payments DROP FOREIGN KEY FK_33FDA223C32A47EE');
        $this->addSql('ALTER TABLE school_class_admission_payments DROP FOREIGN KEY FK_33FDA2239DC4B963');
        $this->addSql('ALTER TABLE school_class_admission_payments DROP FOREIGN KEY FK_33FDA22314463F54');
        $this->addSql('ALTER TABLE school_class_attendance DROP FOREIGN KEY FK_410B528456C5646');
        $this->addSql('ALTER TABLE school_class_attendance DROP FOREIGN KEY FK_410B52814463F54');
        $this->addSql('ALTER TABLE school_class_grade DROP FOREIGN KEY FK_EC8BBDB2456C5646');
        $this->addSql('ALTER TABLE school_class_grade DROP FOREIGN KEY FK_EC8BBDB223EDC87');
        $this->addSql('ALTER TABLE school_class_grade DROP FOREIGN KEY FK_EC8BBDB214463F54');
        $this->addSql('ALTER TABLE school_class_numbering_type DROP FOREIGN KEY school_class_numbering_type_ibfk_2');
        $this->addSql('ALTER TABLE school_class_numbering_type DROP FOREIGN KEY school_class_numbering_type_ibfk_1');
        $this->addSql('ALTER TABLE school_class_payment_modals DROP FOREIGN KEY FK_662FC6799DC4B963');
        $this->addSql('ALTER TABLE school_class_payment_modals DROP FOREIGN KEY FK_662FC679C32A47EE');
        $this->addSql('ALTER TABLE school_class_payment_modals DROP FOREIGN KEY FK_662FC67914463F54');
        $this->addSql('ALTER TABLE school_class_period DROP FOREIGN KEY school_class_period_ibfk_1');
        $this->addSql('ALTER TABLE school_class_period DROP FOREIGN KEY school_class_period_ibfk_3');
        $this->addSql('ALTER TABLE school_class_period DROP FOREIGN KEY school_class_period_ibfk_2');
        $this->addSql('ALTER TABLE school_class_period DROP FOREIGN KEY FK_33B1AF85EC8B7ADE');
        $this->addSql('ALTER TABLE school_class_period DROP FOREIGN KEY FK_33B1AF85C32A47EE');
        $this->addSql('ALTER TABLE school_class_subject DROP FOREIGN KEY school_class_subject_ibfk_5');
        $this->addSql('ALTER TABLE school_class_subject DROP FOREIGN KEY school_class_subject_ibfk_3');
        $this->addSql('ALTER TABLE school_class_subject DROP FOREIGN KEY school_class_subject_ibfk_2');
        $this->addSql('ALTER TABLE school_class_subject DROP FOREIGN KEY school_class_subject_ibfk_1');
        $this->addSql('ALTER TABLE school_class_subject_group DROP FOREIGN KEY FK_BE74C7FDFB00225A');
        $this->addSql('ALTER TABLE school_class_subject_group DROP FOREIGN KEY FK_BE74C7FDC32A47EE');
        $this->addSql('ALTER TABLE school_class_subject_group DROP FOREIGN KEY FK_BE74C7FD14463F54');
        $this->addSql('ALTER TABLE school_evaluation DROP FOREIGN KEY FK_A6822587EC8B7ADE');
        $this->addSql('ALTER TABLE school_evaluation DROP FOREIGN KEY FK_A68225875EEADD3B');
        $this->addSql('ALTER TABLE school_evaluation DROP FOREIGN KEY FK_A68225873FA3C347');
        $this->addSql('ALTER TABLE school_evaluation_time DROP FOREIGN KEY school_evaluation_time_ibfk_2');
        $this->addSql('ALTER TABLE school_evaluation_time DROP FOREIGN KEY school_evaluation_time_ibfk_1');
        $this->addSql('ALTER TABLE school_license_payment DROP FOREIGN KEY school_license_payment_ibfk_1');
        $this->addSql('ALTER TABLE school_section DROP FOREIGN KEY FK_BF5C4877C32A47EE');
        $this->addSql('ALTER TABLE school_section DROP FOREIGN KEY FK_BF5C4877D823E37A');
        $this->addSql('ALTER TABLE school_study_type DROP FOREIGN KEY school_study_type_ibfk_1');
        $this->addSql('ALTER TABLE school_study_type DROP FOREIGN KEY school_study_type_ibfk_2');
        $this->addSql('ALTER TABLE section_category_subject DROP FOREIGN KEY FK_DC5615E0FB00225A');
        $this->addSql('ALTER TABLE section_category_subject DROP FOREIGN KEY FK_DC5615E04B7E29D');
        $this->addSql('ALTER TABLE section_category_subject_group DROP FOREIGN KEY FK_334937034B7E29D');
        $this->addSql('ALTER TABLE student_attendance DROP FOREIGN KEY FK_803CE070CB944F1A');
        $this->addSql('ALTER TABLE student_attendance DROP FOREIGN KEY FK_803CE07014463F54');
        $this->addSql('ALTER TABLE student_class DROP FOREIGN KEY FK_657C6002CB944F1A');
        $this->addSql('ALTER TABLE student_class DROP FOREIGN KEY FK_657C600214463F54');
        $this->addSql('ALTER TABLE student_class_attendance DROP FOREIGN KEY student_class_attendance_ibfk_2');
        $this->addSql('ALTER TABLE student_class_attendance DROP FOREIGN KEY student_class_attendance_ibfk_1');
        $this->addSql('ALTER TABLE student_class_timetable_presence DROP FOREIGN KEY student_class_timetable_presence_ibfk_3');
        $this->addSql('ALTER TABLE student_class_timetable_presence DROP FOREIGN KEY student_class_timetable_presence_ibfk_2');
        $this->addSql('ALTER TABLE student_class_timetable_presence DROP FOREIGN KEY student_class_timetable_presence_ibfk_1');
        $this->addSql('ALTER TABLE student_note DROP FOREIGN KEY FK_F09E81CCCB944F1A');
        $this->addSql('ALTER TABLE student_note DROP FOREIGN KEY FK_F09E81CC14463F54');
        $this->addSql('ALTER TABLE subject_grade DROP FOREIGN KEY FK_48C96FEC23EDC87');
        $this->addSql('ALTER TABLE subject_grade DROP FOREIGN KEY FK_48C96FECEC8B7ADE');
        $this->addSql('ALTER TABLE subject_grade DROP FOREIGN KEY FK_48C96FECDC3D0C33');
        $this->addSql('ALTER TABLE subject_grade DROP FOREIGN KEY FK_48C96FEC456C5646');
        $this->addSql('ALTER TABLE subject_group DROP FOREIGN KEY subject_group_ibfk_3');
        $this->addSql('ALTER TABLE subject_group DROP FOREIGN KEY subject_group_ibfk_2');
        $this->addSql('ALTER TABLE teacher_class DROP FOREIGN KEY FK_BCB4A5C2EC8B7ADE');
        $this->addSql('ALTER TABLE teacher_class DROP FOREIGN KEY FK_BCB4A5C241807E1D');
        $this->addSql('ALTER TABLE teacher_class DROP FOREIGN KEY FK_BCB4A5C214463F54');
        $this->addSql('ALTER TABLE timetable DROP FOREIGN KEY timetable_ibfk_3');
        $this->addSql('ALTER TABLE timetable DROP FOREIGN KEY timetable_ibfk_2');
        $this->addSql('ALTER TABLE timetable DROP FOREIGN KEY timetable_ibfk_1');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admission_reductions ADD CONSTRAINT FK_BA9FE4EBCB944F1A FOREIGN KEY (student_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE admission_reductions ADD CONSTRAINT FK_BA9FE4EBC32A47EE FOREIGN KEY (school_id) REFERENCES school (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE admission_reductions ADD CONSTRAINT FK_BA9FE4EB9DC4B963 FOREIGN KEY (school_period_id) REFERENCES school_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE admission_reductions ADD CONSTRAINT FK_BA9FE4EB8373D28E FOREIGN KEY (reduction_modal_id) REFERENCES school_class_payment_modals (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE admission_reductions ADD CONSTRAINT FK_BA9FE4EB14463F54 FOREIGN KEY (school_class_period_id) REFERENCES school_class_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE admission_reductions ADD CONSTRAINT admission_reductions_ibfk_3 FOREIGN KEY (requested_by_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE admission_reductions ADD CONSTRAINT admission_reductions_ibfk_2 FOREIGN KEY (approved_by_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_license ADD CONSTRAINT app_license_ibfk_1 FOREIGN KEY (school_id) REFERENCES school (id) ON UPDATE SET NULL ON DELETE CASCADE');
        $this->addSql('ALTER TABLE class_occurence ADD CONSTRAINT class_occurence_ibfk_1 FOREIGN KEY (classe_id) REFERENCES classe (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE class_subject_module ADD CONSTRAINT class_subject_module_ibfk_6 FOREIGN KEY (subject_id) REFERENCES study_subject (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE class_subject_module ADD CONSTRAINT class_subject_module_ibfk_5 FOREIGN KEY (module_id) REFERENCES subjects_modules (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE class_subject_module ADD CONSTRAINT class_subject_module_ibfk_3 FOREIGN KEY (school_id) REFERENCES school (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE class_subject_module ADD CONSTRAINT class_subject_module_ibfk_2 FOREIGN KEY (period_id) REFERENCES school_period (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE class_subject_module ADD CONSTRAINT class_subject_module_ibfk_1 FOREIGN KEY (class_id) REFERENCES school_class_period (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE classe ADD CONSTRAINT FK_9621439DD823E37A FOREIGN KEY (study_level_id) REFERENCES study_level (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT evaluation_ibfk_3 FOREIGN KEY (time_id) REFERENCES school_evaluation_time (id)');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT evaluation_ibfk_2 FOREIGN KEY (class_subject_module_id) REFERENCES class_subject_module (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE evaluation ADD CONSTRAINT evaluation_ibfk_1 FOREIGN KEY (student_id) REFERENCES student_class (id)');
        $this->addSql('ALTER TABLE modalities_subscriptions ADD CONSTRAINT FK_6582079CCC33534F FOREIGN KEY (payment_modal_id) REFERENCES school_class_payment_modals (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE modalities_subscriptions ADD CONSTRAINT FK_6582079CCB944F1A FOREIGN KEY (student_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE modalities_subscriptions ADD CONSTRAINT FK_6582079C9DC4B963 FOREIGN KEY (school_period_id) REFERENCES school_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE modalities_subscriptions ADD CONSTRAINT FK_6582079C14463F54 FOREIGN KEY (school_class_period_id) REFERENCES school_class_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840DF675F31B FOREIGN KEY (author_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE payment ADD CONSTRAINT FK_6D28840D9C6BD325 FOREIGN KEY (fees_id) REFERENCES fees (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE report_card_template ADD CONSTRAINT report_card_template_ibfk_1 FOREIGN KEY (evaluation_appreciation_template_id) REFERENCES evaluation_appreciation_template (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE school ADD CONSTRAINT FK_F99EDABBB069795C FOREIGN KEY (registration_base_config_id) REFERENCES registration_card_base_config (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school ADD CONSTRAINT school_ibfk_2 FOREIGN KEY (report_card_template_id) REFERENCES report_card_template (id)');
        $this->addSql('ALTER TABLE school ADD CONSTRAINT school_ibfk_1 FOREIGN KEY (evaluation_appreciation_template_id) REFERENCES evaluation_appreciation_template (id)');
        $this->addSql('ALTER TABLE school_class_admission_payments ADD CONSTRAINT FK_33FDA223CC33534F FOREIGN KEY (payment_modal_id) REFERENCES school_class_payment_modals (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_admission_payments ADD CONSTRAINT FK_33FDA223CB944F1A FOREIGN KEY (student_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_admission_payments ADD CONSTRAINT FK_33FDA223C32A47EE FOREIGN KEY (school_id) REFERENCES school (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_admission_payments ADD CONSTRAINT FK_33FDA2239DC4B963 FOREIGN KEY (school_period_id) REFERENCES school_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_admission_payments ADD CONSTRAINT FK_33FDA22314463F54 FOREIGN KEY (school_class_period_id) REFERENCES school_class_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_attendance ADD CONSTRAINT FK_410B528456C5646 FOREIGN KEY (evaluation_id) REFERENCES school_evaluation (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_attendance ADD CONSTRAINT FK_410B52814463F54 FOREIGN KEY (school_class_id) REFERENCES school_class_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_grade ADD CONSTRAINT FK_EC8BBDB2456C5646 FOREIGN KEY (evaluation_id) REFERENCES school_evaluation (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_grade ADD CONSTRAINT FK_EC8BBDB223EDC87 FOREIGN KEY (subject_id) REFERENCES study_subject (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_grade ADD CONSTRAINT FK_EC8BBDB214463F54 FOREIGN KEY (school_class_id) REFERENCES school_class_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_numbering_type ADD CONSTRAINT school_class_numbering_type_ibfk_2 FOREIGN KEY (classe_id) REFERENCES classe (id)');
        $this->addSql('ALTER TABLE school_class_numbering_type ADD CONSTRAINT school_class_numbering_type_ibfk_1 FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE school_class_payment_modals ADD CONSTRAINT FK_662FC6799DC4B963 FOREIGN KEY (school_period_id) REFERENCES school_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_payment_modals ADD CONSTRAINT FK_662FC679C32A47EE FOREIGN KEY (school_id) REFERENCES school (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_payment_modals ADD CONSTRAINT FK_662FC67914463F54 FOREIGN KEY (school_class_period_id) REFERENCES school_class_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_period ADD CONSTRAINT school_class_period_ibfk_1 FOREIGN KEY (evaluation_appreciation_template_id) REFERENCES evaluation_appreciation_template (id)');
        $this->addSql('ALTER TABLE school_class_period ADD CONSTRAINT school_class_period_ibfk_3 FOREIGN KEY (class_occurence_id) REFERENCES class_occurence (id)');
        $this->addSql('ALTER TABLE school_class_period ADD CONSTRAINT school_class_period_ibfk_2 FOREIGN KEY (class_master_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE school_class_period ADD CONSTRAINT FK_33B1AF85EC8B7ADE FOREIGN KEY (period_id) REFERENCES school_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_period ADD CONSTRAINT FK_33B1AF85C32A47EE FOREIGN KEY (school_id) REFERENCES school (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_subject ADD CONSTRAINT school_class_subject_ibfk_5 FOREIGN KEY (teacher_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE school_class_subject ADD CONSTRAINT school_class_subject_ibfk_3 FOREIGN KEY (group_id) REFERENCES subject_group (id)');
        $this->addSql('ALTER TABLE school_class_subject ADD CONSTRAINT school_class_subject_ibfk_2 FOREIGN KEY (study_subject_id) REFERENCES study_subject (id)');
        $this->addSql('ALTER TABLE school_class_subject ADD CONSTRAINT school_class_subject_ibfk_1 FOREIGN KEY (school_class_period_id) REFERENCES school_class_period (id)');
        $this->addSql('ALTER TABLE school_class_subject_group ADD CONSTRAINT FK_BE74C7FDFB00225A FOREIGN KEY (section_category_subject_group_id) REFERENCES section_category_subject_group (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_subject_group ADD CONSTRAINT FK_BE74C7FDC32A47EE FOREIGN KEY (school_id) REFERENCES school (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_class_subject_group ADD CONSTRAINT FK_BE74C7FD14463F54 FOREIGN KEY (school_class_id) REFERENCES school_class_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_evaluation ADD CONSTRAINT FK_A6822587EC8B7ADE FOREIGN KEY (period_id) REFERENCES school_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_evaluation ADD CONSTRAINT FK_A68225875EEADD3B FOREIGN KEY (time_id) REFERENCES school_evaluation_time (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_evaluation ADD CONSTRAINT FK_A68225873FA3C347 FOREIGN KEY (frame_id) REFERENCES school_evaluation_frame (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_evaluation_time ADD CONSTRAINT school_evaluation_time_ibfk_2 FOREIGN KEY (type_id) REFERENCES school_evaluation_time_type (id)');
        $this->addSql('ALTER TABLE school_evaluation_time ADD CONSTRAINT school_evaluation_time_ibfk_1 FOREIGN KEY (evaluation_frame_id) REFERENCES school_evaluation_frame (id)');
        $this->addSql('ALTER TABLE school_license_payment ADD CONSTRAINT school_license_payment_ibfk_1 FOREIGN KEY (license_id) REFERENCES app_license (id)');
        $this->addSql('ALTER TABLE school_section ADD CONSTRAINT FK_BF5C4877C32A47EE FOREIGN KEY (school_id) REFERENCES school (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_section ADD CONSTRAINT FK_BF5C4877D823E37A FOREIGN KEY (study_level_id) REFERENCES study_level (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE school_study_type ADD CONSTRAINT school_study_type_ibfk_1 FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE school_study_type ADD CONSTRAINT school_study_type_ibfk_2 FOREIGN KEY (studies_type_id) REFERENCES studies_type (id)');
        $this->addSql('ALTER TABLE section_category_subject ADD CONSTRAINT FK_DC5615E0FB00225A FOREIGN KEY (section_category_subject_group_id) REFERENCES section_category_subject_group (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE section_category_subject ADD CONSTRAINT FK_DC5615E04B7E29D FOREIGN KEY (section_category_id) REFERENCES classe (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE section_category_subject_group ADD CONSTRAINT FK_334937034B7E29D FOREIGN KEY (section_category_id) REFERENCES classe (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE student_attendance ADD CONSTRAINT FK_803CE070CB944F1A FOREIGN KEY (student_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE student_attendance ADD CONSTRAINT FK_803CE07014463F54 FOREIGN KEY (school_class_id) REFERENCES school_class_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE student_class ADD CONSTRAINT FK_657C6002CB944F1A FOREIGN KEY (student_id) REFERENCES user (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_class ADD CONSTRAINT FK_657C600214463F54 FOREIGN KEY (school_class_period_id) REFERENCES school_class_period (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_class_attendance ADD CONSTRAINT student_class_attendance_ibfk_2 FOREIGN KEY (student_class_id) REFERENCES student_class (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_class_attendance ADD CONSTRAINT student_class_attendance_ibfk_1 FOREIGN KEY (time_id) REFERENCES school_evaluation_time (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE student_class_timetable_presence ADD CONSTRAINT student_class_timetable_presence_ibfk_3 FOREIGN KEY (school_evaluation_time_id) REFERENCES school_evaluation_time (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE student_class_timetable_presence ADD CONSTRAINT student_class_timetable_presence_ibfk_2 FOREIGN KEY (time_table_slot_id) REFERENCES timetable_slot (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE student_class_timetable_presence ADD CONSTRAINT student_class_timetable_presence_ibfk_1 FOREIGN KEY (student_class_id) REFERENCES student_class (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE student_note ADD CONSTRAINT FK_F09E81CCCB944F1A FOREIGN KEY (student_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE student_note ADD CONSTRAINT FK_F09E81CC14463F54 FOREIGN KEY (school_class_id) REFERENCES school_class_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE subject_grade ADD CONSTRAINT FK_48C96FEC23EDC87 FOREIGN KEY (subject_id) REFERENCES section_category_subject (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE subject_grade ADD CONSTRAINT FK_48C96FECEC8B7ADE FOREIGN KEY (period_id) REFERENCES school_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE subject_grade ADD CONSTRAINT FK_48C96FECDC3D0C33 FOREIGN KEY (s_class_id) REFERENCES school_class_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE subject_grade ADD CONSTRAINT FK_48C96FEC456C5646 FOREIGN KEY (evaluation_id) REFERENCES school_evaluation (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE subject_group ADD CONSTRAINT subject_group_ibfk_3 FOREIGN KEY (school_id) REFERENCES school (id)');
        $this->addSql('ALTER TABLE subject_group ADD CONSTRAINT subject_group_ibfk_2 FOREIGN KEY (period_id) REFERENCES school_period (id)');
        $this->addSql('ALTER TABLE teacher_class ADD CONSTRAINT FK_BCB4A5C2EC8B7ADE FOREIGN KEY (period_id) REFERENCES school_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE teacher_class ADD CONSTRAINT FK_BCB4A5C241807E1D FOREIGN KEY (teacher_id) REFERENCES user (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE teacher_class ADD CONSTRAINT FK_BCB4A5C214463F54 FOREIGN KEY (school_class_id) REFERENCES school_class_period (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE timetable ADD CONSTRAINT timetable_ibfk_3 FOREIGN KEY (teacher_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timetable ADD CONSTRAINT timetable_ibfk_2 FOREIGN KEY (school_id) REFERENCES school (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timetable ADD CONSTRAINT timetable_ibfk_1 FOREIGN KEY (period_id) REFERENCES school_period (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE timetable_day ADD CONSTRAINT timetable_day_ibfk_1 FOREIGN KEY (timetable_id) REFERENCES timetable (id) ON DELETE CASCADE');
    }
}
