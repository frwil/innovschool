<?php

namespace App\Controller;

use App\Entity\SchoolPeriod;
use App\Form\SchoolPeriodType;
use App\Repository\SchoolPeriodRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\School;
use App\Service\OperationLogger;
use Symfony\Component\Form\FormError;

#[Route('/school-period')]
final class SchoolPeriodController extends AbstractController
{
    private ?SchoolPeriod $currentPeriod = null;
    private EntityManagerInterface $entityManager;
    private ?School $currentSchool = null;
    private SessionInterface $session;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    private function initContext(Request $request): void
    {
        $this->session = $request->getSession();
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
    }

    #[Route(name: 'app_school_period_index', methods: ['GET', 'POST'])]
    public function index(SchoolPeriodRepository $schoolPeriodRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        $schoolPeriod = new SchoolPeriod();
        $schoolPeriod->setName($schoolPeriodRepository->suggestNextName());
        $form = $this->createForm(SchoolPeriodType::class, $schoolPeriod, [
            'action' => $this->generateUrl('app_school_period_new'),
        ]);


        return $this->render('school_period/index.html.twig', [
            'school_periods' => $schoolPeriodRepository->findBy([], ['name' => 'ASC']),
            'form' => $form,
        ]);
    }

    #[Route('/new', name: 'app_school_period_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, OperationLogger $operationLogger, SchoolPeriodRepository $schoolPeriodRepository): Response
    {
        $this->initContext($request);
        $schoolPeriod = new SchoolPeriod();
        if (!$request->isMethod('POST')) {
            $schoolPeriod->setName($schoolPeriodRepository->suggestNextName());
        }
        $form = $this->createForm(SchoolPeriodType::class, $schoolPeriod);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $expected = $schoolPeriodRepository->getExpectedNextName();
            if ($expected !== null && $schoolPeriod->getName() !== $expected) {
                $form->get('name')->addError(new FormError(sprintf(
                    'La nouvelle période doit être « %s », successeure directe de la dernière période existante.',
                    $expected
                )));
            } else {
                $schoolPeriod->setPreviousPeriod($schoolPeriodRepository->findLatest());
                $entityManager->persist($schoolPeriod);
                try {
                    $entityManager->flush();
                    $this->addFlash('success', 'Période scolaire créée avec succès.');
                    // Log the operation
                    $operationLogger->log(
                        'CRÉATION DE PÉRIODE SCOLAIRE ' . $schoolPeriod->getName(),
                        'SUCCESS',
                        'SchoolPeriod',
                        $schoolPeriod->getId(),
                        null,
                        ['name' => $schoolPeriod->getName(), 'previous' => $schoolPeriod->getPreviousPeriod()?->getName(), 'school' => $this->currentSchool?->getName(), 'period' => $this->currentPeriod?->getName()]
                    );
                    return $this->redirectToRoute('app_school_period_index', [], Response::HTTP_SEE_OTHER);
                } catch (\Exception $e) {
                    // Gérer l'erreur (journaliser, afficher un message, etc.)
                    $this->addFlash('error', 'Une erreur est survenue lors de la création de la période scolaire : ' . $e->getMessage());
                    // log l'erreur
                    $operationLogger->log(
                        'ÉCHEC DE CRÉATION DE PÉRIODE SCOLAIRE',
                        'ERROR',
                        'SchoolPeriod',
                        null,
                        $e->getMessage(),
                        ['name' => $schoolPeriod->getName(), 'school' => $this->currentSchool?->getName(), 'period' => $this->currentPeriod?->getName()]
                    );
                }
            }
        }

        return $this->render('school_period/new.html.twig', [
            'school_period' => $schoolPeriod,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_school_period_show', methods: ['GET'])]
    public function show(SchoolPeriod $schoolPeriod): Response
    {
        return $this->render('school_period/show.html.twig', [
            'school_period' => $schoolPeriod,
        ]);
    }

    #[Route('/{id}/as-default', name: 'app_school_period_default', methods: ['GET'])]
    public function asDefault(Request $request, SchoolPeriod $schoolPeriod, EntityManagerInterface $entityManager, OperationLogger $operationLogger): Response
    {
        $this->initContext($request);
        $schoolPeriods = $entityManager->getRepository(SchoolPeriod::class)->findAll();
        foreach ($schoolPeriods as $period) {
            $period->setEnabled(false);
        }
        $entityManager->flush();

        $schoolPeriod->setEnabled(true);
        try{
            $entityManager->flush();

        return $this->redirectToRoute('app_school_period_index', [], Response::HTTP_SEE_OTHER);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour de la période scolaire : ' . $e->getMessage());
            // log l'erreur
            $operationLogger->log(
                'ÉCHEC DE MISE À JOUR DE PÉRIODE SCOLAIRE',
                'ERROR',
                'SchoolPeriod',
                null,
                $e->getMessage(),
                ['name' => $schoolPeriod->getName(), 'school' => $this->currentSchool?->getName(), 'period' => $this->currentPeriod?->getName()]
            );
        }
        return $this->redirectToRoute('app_school_period_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_school_period_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SchoolPeriod $schoolPeriod, EntityManagerInterface $entityManager, OperationLogger $operationLogger): Response
    {
        $this->initContext($request);
        $form = $this->createForm(SchoolPeriodType::class, $schoolPeriod, ['lock_name' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try{
                $entityManager->flush();
                $this->addFlash('success', 'Période scolaire mise à jour avec succès.');
                // Log the operation
                $operationLogger->log(
                    'MISE À JOUR DE PÉRIODE SCOLAIRE ' . $schoolPeriod->getName(),
                    'SUCCESS',
                    'SchoolPeriod',
                    $schoolPeriod->getId(),
                    null,
                    ['name' => $schoolPeriod->getName(), 'school' => $this->currentSchool?->getName(), 'period' => $this->currentPeriod?->getName()]
                );
                return $this->redirectToRoute('app_school_period_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour de la période scolaire : ' . $e->getMessage());
                // log l'erreur
                $operationLogger->log(
                    'ÉCHEC DE MISE À JOUR DE PÉRIODE SCOLAIRE',
                    'ERROR',
                    'SchoolPeriod',
                    null,
                    $e->getMessage(),
                    ['name' => $schoolPeriod->getName(), 'school' => $this->currentSchool?->getName(), 'period' => $this->currentPeriod?->getName()]
                );
            }
        }

        return $this->render('school_period/edit.html.twig', [
            'school_period' => $schoolPeriod,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_school_period_delete', methods: ['POST'])]
    public function delete(Request $request, SchoolPeriod $schoolPeriod, EntityManagerInterface $entityManager, OperationLogger $operationLogger, SchoolPeriodRepository $schoolPeriodRepository): Response
    {
        $this->initContext($request);
        if ($this->isCsrfTokenValid('delete' . $schoolPeriod->getId(), $request->getPayload()->getString('_token'))) {
            if ($schoolPeriodRepository->hasLaterThan($schoolPeriod)) {
                $this->addFlash('error', sprintf(
                    'Impossible de supprimer « %s » : seule la dernière période peut être supprimée.',
                    $schoolPeriod->getName()
                ));
                $operationLogger->log(
                    'SUPPRESSION REFUSÉE DE PÉRIODE SCOLAIRE ' . $schoolPeriod->getName(),
                    'ERROR',
                    'SchoolPeriod',
                    $schoolPeriod->getId(),
                    'La période n\'est pas la dernière.',
                    ['name' => $schoolPeriod->getName(), 'school' => $this->currentSchool?->getName(), 'period' => $this->currentPeriod?->getName()]
                );
                return $this->redirectToRoute('app_school_period_index', [], Response::HTTP_SEE_OTHER);
            }
            $entityManager->remove($schoolPeriod);
            try{
                $entityManager->flush();
                $this->addFlash('success', 'Période scolaire supprimée avec succès.');
                // Log the operation
                $operationLogger->log(
                    'SUPPRESSION DE PÉRIODE SCOLAIRE ' . $schoolPeriod->getName(),
                    'SUCCESS',
                    'SchoolPeriod',
                    $schoolPeriod->getId(),
                    null,
                    ['name' => $schoolPeriod->getName(), 'school' => $this->currentSchool?->getName(), 'period' => $this->currentPeriod?->getName()]
                );
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de la suppression de la période scolaire : ' . $e->getMessage());
                // log l'erreur
                $operationLogger->log(
                    'ÉCHEC DE SUPPRESSION DE PÉRIODE SCOLAIRE',
                    'ERROR',
                    'SchoolPeriod',
                    null,
                    $e->getMessage(),
                    ['name' => $schoolPeriod->getName(), 'school' => $this->currentSchool?->getName(), 'period' => $this->currentPeriod?->getName()]
                );
            }
        }

        return $this->redirectToRoute('app_school_period_index', [], Response::HTTP_SEE_OTHER);
    }
}
