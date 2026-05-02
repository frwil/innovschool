<?php

namespace App\Controller;

use App\Entity\ModalitiesSubscriptions;
use App\Entity\SchoolClassAdmissionPayment;
use App\Repository\SchoolClassAdmissionPaymentRepository;
use App\Repository\ModalitiesSubscriptionsRepository;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\SchoolPeriodRepository;
use App\Repository\SchoolClassPaymentModalRepository; // Ensure this class exists in the specified namespace
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\School;
use App\Entity\SchoolPeriod;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;

use App\Repository\UserRepository;
use App\Service\NumberToWordsService as NumberToWords;

class ReceiptController extends AbstractController
{
    private UserRepository $userRepository;
    private $paymentRepository;
    private $schoolClassRepository;
    private $schoolPeriodRepository;
    private $schoolClassPaymentModalRepository;
    private $modalitiesSubscriptionsRepository;
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;

    public function __construct(UserRepository $userRepository, SchoolClassAdmissionPaymentRepository $paymentRepository, SchoolClassPeriodRepository $schoolClassRepository, SchoolPeriodRepository $schoolPeriodRepository, SchoolClassPaymentModalRepository $schoolClassPaymentModalRepository, ModalitiesSubscriptionsRepository $modalitiesSubscriptionsRepository)
    {
        $this->modalitiesSubscriptionsRepository = $modalitiesSubscriptionsRepository;
        $this->schoolClassPaymentModalRepository = $schoolClassPaymentModalRepository;
        $this->userRepository = $userRepository;
        $this->paymentRepository = $paymentRepository;
        $this->schoolClassRepository = $schoolClassRepository;
        $this->schoolPeriodRepository = $schoolPeriodRepository;
    }

    #[Route('/receipt', name: 'generate_receipt', methods: ['GET'])]
    public function generateReceipt(SessionInterface $session,Request $request, NumberToWords $numberToWords,EntityManagerInterface $entityManager): Response
    {

        $this->session = $session;
        $this->entityManager = $entityManager;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $paymentId = $request->query->get('paymentId');
        $modalType = $request->query->get('modalType');
        // Vérifier si paymentId est un tableau JSON
        if (is_array(json_decode($paymentId))) $paymentId = json_decode($paymentId);

        // Récupérer les informations nécessaires
        $schoolPeriod = $this->currentPeriod;
        if ($paymentId != null) {
            $payments = $this->paymentRepository->findBy(['id' => $paymentId]);
            $studentId = $payments[0]->getStudent()->getId();
            $student = $this->userRepository->find($studentId);
            $classId = $payments[0]->getSchoolClass()->getId();
            $class = $this->schoolClassRepository->find($classId);
            $totalPayments = $this->paymentRepository->findBy(['student' => $student, 'schoolClassPeriod' => $class, 'schoolPeriod' => $schoolPeriod]);
        } else {

            $studentId = $request->query->get('studentId');
            $student = $this->userRepository->find($studentId);
            $classId = $request->query->get('classId');
            $class = $this->schoolClassRepository->find($classId);
            // Récupérer tous les paiements pour l'élève et la classe
            $payments = $this->paymentRepository->findBy(['student' => $student, 'schoolClassPeriod' => $class, 'schoolPeriod' => $schoolPeriod]);
            $totalPayments = $this->paymentRepository->findBy(['student' => $student, 'schoolClassPeriod' => $class, 'schoolPeriod' => $schoolPeriod]);
        }

        // Trier $payments par modalPriority
        usort($payments, function ($a, $b) {
            return $a->getPaymentModal()->getModalPriority() <=> $b->getPaymentModal()->getModalPriority();
        });
        //$payments = $this->paymentRepository->findBy(['student' => $student, 'schoolClassPeriod' => $class, 'schoolPeriod' => $schoolPeriod]);
        // Vérifier si les données existent

        if (!$student || !$class || !$payments) {
            throw $this->createNotFoundException('Données introuvables pour générer le reçu.');
        }

        // Recharge l'utilisateur courant depuis la base pour garantir l'accès à getFullName()
        $currentUser = $this->userRepository->findOneBy(['username' => $this->getUser()->getUserIdentifier()]);

        // Récupérer les informations de l'école
        $school = [
            'name' => $this->currentSchool->getName(),
            'address' => $this->currentSchool->getAddress(),
            'phone' => $this->currentSchool->getContactPhone(),
        ];

        // Générer le numéro de reçu
        $date = new \DateTime();
        $words = array_filter(explode(' ', $student->getFullName()), fn($w) => strlen($w) > 0);
        $initials = implode('', array_map(fn($word) => strtoupper($word[0]), $words));
        $receiptNumber = $date->format('ymdHis') . '-' . $initials;

        if (!is_array($paymentId)) {
            $modalities = [];
            $i = 0;
            foreach ($totalPayments as $payment) {
                $modaliteLabel = $payment->getPaymentModal()->getLabel();
                $modal = $payment->getPaymentModal();
                if (!isset($modalities[$modaliteLabel])) {
                    $modalities[$modaliteLabel] = [
                        'modalite' => $modaliteLabel,
                        'type' => $payment->getPaymentModal()->getModalType(),
                        'totalAmountToPay' => 0,
                        'totalPaymentAmount' => 0,
                        'totalRemainingAmount' => 0,
                        'totalReductionAmount' => array_sum(array_map(function($p){return $p->getReductionAmount();},array_filter($modal->getAdmissionReductions()->toArray(),function($p) use ($student,$class) {
                            return $p->getStudent() && $p->getStudent()->getId() === $student->getId() && $p->getSchoolClass() && $p->getSchoolClass()->getId() === $class->getId() && $p->isApproved();
                        }))),
                    ];
                }

                
                

                // Récupérer la souscription pour cette modalité
                $subscription = $this->modalitiesSubscriptionsRepository->findOneBy([
                    'student' => $student,
                    'schoolClassPeriod' => $class,
                    'schoolPeriod' => $schoolPeriod,
                    'paymentModal' => $modal,
                ]);
                // Calculer le montant à payer en tenant compte de la souscription
                $amountToPay = $modal->getAmount();
                if ($subscription && !$subscription->getIsFull()) {
                    $amountToPay /= 2; // Diviser le montant par 2 si la souscription n'est pas "full"
                }

                $modalities[$modaliteLabel]['totalAmountToPay'] = $amountToPay;
                $modalities[$modaliteLabel]['totalPaymentAmount'] += $payment->getPaymentAmount();
                $modalities[$modaliteLabel]['totalRemainingAmount'] += $amountToPay - $payment->getPaymentAmount();
                $modalities[$modaliteLabel]['totalReductionAmount'] = array_sum(array_map(function($p) use ($class,$student){return $p->getReductionAmount();},array_filter($modal->getAdmissionReductions()->toArray(),function($p) use ($student,$class) {
                            return $p->getStudent() && $p->getStudent()->getId() === $student->getId() && $p->getSchoolClass() && $p->getSchoolClass()->getId() === $class->getId() && $p->isApproved();
                        })));
                $i++;
            }
            $modals = $this->schoolClassPaymentModalRepository->findBy(['schoolClassPeriod' => $class, 'schoolPeriod' => $schoolPeriod, 'school' => $this->currentSchool]);
            foreach ($modals as $modal) {
                $modalLabel = $modal->getLabel();

                if (!isset($modalities[$modalLabel])) {
                    $modalities[$modalLabel] = [
                        'modalite' => $modalLabel,
                        'type' => $modal->getModalType(),
                        'totalAmountToPay' => 0,
                        'totalPaymentAmount' => 0,
                        'totalRemainingAmount' => 0,
                        'totalReductionAmount' =>  array_sum(array_map(function($p){return $p->getReductionAmount();},array_filter($modal->getAdmissionReductions()->toArray(),function($p) use ($student,$class) {
                            return $p->getStudent() && $p->getStudent()->getId() === $student->getId() && $p->getSchoolClass() && $p->getSchoolClass()->getId() === $class->getId() && $p->isApproved();
                        }))),
                    ];
                }

                // Récupérer la souscription pour cette modalité
                $subscription = $this->modalitiesSubscriptionsRepository->findOneBy([
                    'student' => $student,
                    'schoolClassPeriod' => $class,
                    'schoolPeriod' => $schoolPeriod,
                    'paymentModal' => $modal,
                ]);

                
                // Calculer le montant à payer en tenant compte de la souscription
                $amountToPay = $modal->getAmount();
                if ($subscription && !$subscription->getIsFull()) {
                    $amountToPay /= 2; // Diviser le montant par 2 si la souscription n'est pas "full"
                }

                $modalities[$modalLabel]['totalAmountToPay'] = $amountToPay;
                $modalities[$modalLabel]['totalPaymentAmount'] += 0;
                $modalities[$modalLabel]['totalRemainingAmount'] = $modalities[$modalLabel]['totalAmountToPay'] - $modalities[$modalLabel]['totalPaymentAmount'];
                $modalities[$modalLabel]['totalReductionAmount'] =  array_sum(array_map(function($p){return $p->getReductionAmount();},array_filter($modal->getAdmissionReductions()->toArray(),function($p) use ($student,$class) {
                    return $p->getStudent() && $p->getStudent()->getId() === $student->getId() && $p->getSchoolClass() && $p->getSchoolClass()->getId() === $class->getId() && $p->isApproved();
                })));
            }

            // Trier $modalities par type
            uasort($modalities, function ($a, $b) {
                return strcmp($a['type'], $b['type']);
            });
            //dd($modalities);

            // Calculer les totaux globaux
            $totalAmountToPay = 0;
            $totalPaymentAmount = 0;
            $totalRemainingAmount = 0;


            foreach ($modalities as $modalite) {
                $totalAmountToPay += $modalite['totalAmountToPay'];
                $totalPaymentAmount += $modalite['totalPaymentAmount'];
                $totalRemainingAmount += $modalite['totalRemainingAmount'];
            }

            if(count($payments)===1){
                $ptype=$payments[0]->getModalType();
                $pa=$this->entityManager->getRepository(SchoolClassAdmissionPayment::class)->findBy(['modalType'=>$ptype,'student'=>$payments[0]->getStudent(),'paymentModal'=>$payments[0]->getPaymentModal(),'schoolClassPeriod'=>$payments[0]->getSchoolClass()]);
                $pa=array_sum(array_map(function($p){ return $p->getPaymentAmount(); },$pa));
            }
            
            
            // Générer le contenu HTML du reçu
            return $this->render('receipt/receipt' . ($paymentId != null ? '_individual' : '') . '.html.twig', [
                'school' => $school,
                'student' => [
                    'name' => $student->getFullName(),
                    'matricule' => $student->getRegistrationNumber(), // Assurez-vous que cette méthode existe
                    'nationalRegistrationNumber' => $student->getNationalRegistrationNumber(), // Assurez-vous que cette méthode existe
                ],
                'class' => $class->getClassOccurence()->getName(),
                'payments' => is_array($payments) ? array_map(function ($payment) use ($modalities,$pa,$student) {
                    return [
                        'modalite' => $payment->getPaymentModal()->getLabel(), // Modalité
                        'dueDate' => $payment->getPaymentModal()->getDueDate(), // Date d'échéance
                        'amountToPay' => $modalities[$payment->getPaymentModal()->getLabel()]['totalAmountToPay']-($payment->getPaymentModal()->getModalType()=='base' ? array_sum(array_map(function($p){return $p->getReductionAmount();},array_filter($payment->getPaymentModal()->getAdmissionReductions()->toArray(),function($p) use($student){return $p->getStudent()->getId()===$student->getId() && $p->isApproved();}))) : 0), // Montant à payer
                        'paymentAmount' => $pa, // Montant payé
                        'remainingAmount' => $payment->getPaymentModal()->getAmount() - $payment->getPaymentAmount(), // Reste à payer
                    ];
                }, $payments) : [
                    [
                        'modalite' => $payments->getPaymentModal()->getLabel(), // Modalité
                        'dueDate' => $payments->getPaymentModal()->getDueDate(), // Date d'échéance
                        'amountToPay' => $modalities[$payments->getPaymentModal()->getLabel()]['totalAmountToPay']-($payments->getPaymentModal()->getModalType()=='base' ? array_sum(array_map(function($p){return $p->getReductionAmount();},array_filter($payments->getPaymentModal()->getAdmissionReductions()->toArray(),function($p) use($student){return $p->getStudent()->getId()===$student->getId() && $p->isApproved();}))) : 0), // Montant à payer
                        'paymentAmount' => $payments->getPaymentAmount(), // Montant payé
                        'remainingAmount' => $payments->getPaymentModal()->getAmount() - $payments->getPaymentAmount(), // Reste à payer
                    ]
                ],
                'receiptNumber' => $paymentId != null ? $payments[0]->getPaymentDate()->format('ymd') . $date->format('His') . '-' . $initials : $receiptNumber,
                'schoolYear' => $schoolPeriod->getName(),
                'docTitle' => $paymentId != null ? 'RECU DE PAIEMENT' : 'RECAPITULATIF DES PAIEMENTS',
                'paymentDate' => $paymentId != null ? $payments[0]->getPaymentDate()->format('d/m/Y') : null,
                'totalPayments' => $paymentId != null ? array_map(function ($payment) use ($class,$student) {
                    return [
                        'modalite' => $payment->getPaymentModal()->getLabel(), // Modalité
                        'dueDate' => $payment->getPaymentModal()->getDueDate(), // Date d'échéance
                        'amountToPay' => $payment->getPaymentModal()->getAmount(), // Montant à payer
                        'paymentAmount' => $payment->getPaymentAmount(), // Montant payé
                        'remainingAmount' => $payment->getPaymentModal()->getAmount() - $payment->getPaymentAmount(), // Reste à payer
                        'reductionAmount' => array_sum(array_map(function($p){return $p->getReductionAmount();},array_filter($payment->getPaymentModal()->getAdmissionReductions()->toArray(),function($p) use ($class,$student){
                return $p->getSchoolClass()->getId() === $class->getId() && $p->getStudent()->getId() === $student->getId() && $p->isApproved();
            }))), // Montant de la réduction
                    ];
                }, $totalPayments) : null,
                'amountInNumbers' => $paymentId != null ? strtoupper($numberToWords->convert($payments[0]->getPaymentAmount())).' - '.$payments[0]->getPaymentAmount() : null,
                'modalities' => $modalities,
                'totalAmountToPay' => $totalAmountToPay,
                'totalPaymentAmount' => $totalPaymentAmount,
                'totalRemainingAmount' => $totalRemainingAmount,
                'totalReductionAmount' => array_sum(array_map(function($p){return $p->getReductionAmount();},array_filter($payments[0]->getPaymentModal()->getAdmissionReductions()->toArray(),function($p) use ($class,$student){
                return $p->getSchoolClass()->getId() === $class->getId() && $p->getStudent()->getId() === $student->getId() && $p->isApproved();
            }))),
                'currentUser' => $currentUser ? $currentUser->getFullName() : '',

            ]);
        } else {
            // Regrouper les paiements par modalités
            $groupedPayments = [];
            foreach ($payments as $payment) {
                $modaliteLabel = $payment->getPaymentModal()->getLabel();
                if (!isset($groupedPayments[$modaliteLabel])) {
                    $groupedPayments[$modaliteLabel] = [
                        'modalite' => $modaliteLabel,
                        'type' => $payment->getPaymentModal()->getModalType(),
                        'totalAmountToPay' => 0,
                        'totalPaymentAmount' => 0,
                        'totalRemainingAmount' => 0,
                        'payments' => [],
                    ];
                }

                // Ajouter le paiement à la modalité correspondante
                $groupedPayments[$modaliteLabel]['payments'][] = [
                    'dueDate' => $payment->getPaymentModal()->getDueDate(),
                    'amountToPay' => $payment->getPaymentModal()->getAmount(),
                    'paymentAmount' => $payment->getPaymentAmount(),
                    'remainingAmount' => $payment->getPaymentModal()->getAmount() - $payment->getPaymentAmount(),
                    'paymentDate' => $payment->getPaymentDate(),
                ];

                // Mettre à jour les totaux pour cette modalité
                $groupedPayments[$modaliteLabel]['totalAmountToPay'] = $payment->getPaymentModal()->getAmount();
                $groupedPayments[$modaliteLabel]['totalPaymentAmount'] += $payment->getPaymentAmount();
                $groupedPayments[$modaliteLabel]['totalRemainingAmount'] = $payment->getPaymentModal()->getAmount() - $groupedPayments[$modaliteLabel]['totalPaymentAmount'];
                //dump($groupedPayments);
            }


            //dd($groupedPayments);


            // Calculer les totaux globaux
            $totalAmountToPay = 0;
            $totalPaymentAmount = 0;
            $totalRemainingAmount = 0;
            //dd($groupedPayments);

            foreach ($groupedPayments as $modalite) {
                $totalAmountToPay += $modalite['totalAmountToPay'];
                $totalPaymentAmount += $modalite['totalPaymentAmount'];
                $totalRemainingAmount += $modalite['totalRemainingAmount'];
            }
            $totalToConvert = $totalPaymentAmount;

            //dd($totalAmountToPay,$totalPaymentAmount,$totalRemainingAmount);
            $totalPaymentAmount = 0;

            $allPayments = $this->paymentRepository->findBy(
                ['student' => $student, 'schoolClassPeriod' => $class, 'schoolPeriod' => $schoolPeriod, 'modalType' => $modalType],
                ['paymentDate' => 'ASC'] // Trier par date de paiement croissante
            );
            $allReductions = [];
            foreach ($allPayments as $payment) {
                $modal = $payment->getPaymentModal();
                $amountToPay = $modal->getAmount();
                $allReductions[$modal->getLabel()]= array_sum(array_map(function($p){return $p->getReductionAmount();},array_filter($modal->getAdmissionReductions()->toArray(),function($p) use ($student) {
                    return $p->getStudent() && $p->getStudent()->getId() === $student->getId() && $p->isApproved();
                })));

                // Récupérer la souscription pour cette modalité
                $subscription = $this->modalitiesSubscriptionsRepository->findOneBy([
                    'student' => $student,
                    'schoolClassPeriod' => $class,
                    'schoolPeriod' => $schoolPeriod,
                    'paymentModal' => $modal,
                ]);

                // Ajuster le montant à payer si la souscription n'est pas "full"
                if ($subscription && !$subscription->getIsFull()) {
                    $amountToPay /= 2; // Diviser le montant par 2 si la souscription n'est pas "full"
                }

                //$totalAmountToPay += $amountToPay;
                $totalPaymentAmount += $payment->getPaymentAmount();
                //$totalRemainingAmount += $amountToPay - $payment->getPaymentAmount();
            }
            
            $totalRemainingAmount = $totalAmountToPay - $totalPaymentAmount;
            
            // Générer le contenu HTML du reçu pour les paiements groupés
            return $this->render('receipt/receipt_grouped.html.twig', [
                'school' => $school,
                'student' => [
                    'name' => $student->getFullName(),
                    'matricule' => $student->getRegistrationNumber(),
                    'nationalRegistrationNumber' => $student->getNationalRegistrationNumber(), // Assurez-vous que cette méthode existe
                ],
                'class' => $class->getClassOccurence()->getName(),
                'groupedPayments' => $groupedPayments,
                'totalAmountToPay' => $totalAmountToPay,
                'totalPaymentAmount' => $totalPaymentAmount,
                'totalRemainingAmount' => $totalRemainingAmount - array_sum($allReductions),
                'docTitle' => 'RECU DE PAIEMENT',
                'currentUser' => $currentUser ? $currentUser->getFullName() : '',
                'allPayments' => $allPayments,
                'receiptNumber' => $date->format('ymdHis') . '-' . $initials,
                'amountInNumbers' => $paymentId != null ? strtoupper($numberToWords->convert($totalToConvert)) : null,
                'period'=>$this->currentPeriod,
                'classe'=>$class->getClassOccurence(),
                'allReductions' => $allReductions,
                'totalReductionAmount'=>array_sum($allReductions)

            ]);
            
        }
    }
}
