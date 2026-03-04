<?php

namespace App\Service;

use App\Entity\ModalitiesSubscriptions;
use App\Repository\ModalitiesSubscriptionsRepository;
use App\Repository\StudentClassRepository;
use App\Repository\SchoolPeriodRepository;


class AdmissionReportService
{
    private StudentClassRepository $studentRepository;
    private SchoolPeriodRepository $schoolPeriodRepository;
    private ModalitiesSubscriptionsRepository $subscriptionRepository;

    public function __construct(
        StudentClassRepository $studentRepository,
        SchoolPeriodRepository $schoolPeriodRepository,
        ModalitiesSubscriptionsRepository $subscriptionRepository
    ) {
        $this->studentRepository = $studentRepository;
        $this->schoolPeriodRepository = $schoolPeriodRepository;
        $this->subscriptionRepository = $subscriptionRepository;
    }

    public function getReportData($classId, $modalities)
    {
        // Récupérer les étudiants en fonction des critères
        $students = $this->studentRepository->findStudentsByCriteria($classId, $modalities);
        $schoolPeriod = $this->schoolPeriodRepository->findOneBy(['id' => $classId]);
        //$school=$this->currentSchool;

        // Préparer les données du rapport
        $reportData = [];
        foreach ($students as $student) {
            $modalitiesData = [];

            // Parcourir les modalités de paiement associées à la classe
            foreach ($student->getSchoolClassPeriod()->getPaymentModals() as $modal) {
                $subscription = $this->subscriptionRepository->findOneBy([
                    'student' => $student->getStudent(),
                    'paymentModal' => $modal,
                    'schoolClassPeriod' => $classId,
                    'schoolPeriod' => $schoolPeriod,
                ]);
                if (in_array($modal->getId(), $modalities)) {
                    $totalPaid = 0;
                    $isFull=$subscription ? $subscription->getIsFull() : true;
                    $remainingAmount = $modal->getAmount()/(!$isFull ? 2 : 1); // Montant total de la modalité
                    $payments = [];
                    // Parcourir les paiements d'admission de l'étudiant
                    foreach ($student->getStudent()->getAdmissionPayments() as $payment) {
                        if ($payment->getPaymentModal()->getId() === $modal->getId()) {
                            $totalPaid += $payment->getPaymentAmount(); // Ajouter le montant payé

                            $payments[$modal->getId()] = [
                                'isFull' => $subscription ? $subscription->getIsFull() : true,
                                'isFullPeriod' => $subscription ? $subscription->getIsFullPeriod() : false,
                                '',
                            ];
                        }
                    }

                    // Calculer le reste à payer
                    $remainingAmount -= $totalPaid;

                    // Ajouter les données de la modalité
                    $modalitiesData[] = [
                        'modalLabel' => $modal->getLabel(),
                        'totalPaid' => $totalPaid,
                        'remainingAmount' => $remainingAmount,
                        'id' => $modal->getId(),
                        'payments' => $payments,
                    ];
                }
            }

            // Ajouter les données de l'étudiant au rapport
            $reportData[] = [
                'name' => $student->getStudent()->getFullName(),
                'matricule' => $student->getStudent()->getRegistrationNumber(),
                'modalities' => $modalitiesData,
            ];
        }

        return $reportData;
    }

    public function generateExcel(array $reportData): string
    {
        // Logique pour générer un fichier Excel à partir des données
        // Retourner le chemin du fichier généré
    }
}
