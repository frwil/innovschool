<?php

namespace App\Controller;

use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolSection;
use App\Form\SchoolClassReportFilterType;
use App\Form\SchoolGlobalReportFilterType;
use App\Service\ReportService;
use App\Service\SchoolClassReportService;
use App\Service\SchoolGlobalReportFilter;
use App\Service\SchoolGlobalReportFilterDto;
use App\Service\SchoolReportService;
use App\Service\SchoolSectionDTO;
use App\Service\SchoolSectionReportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use App\Entity\School;
use App\Entity\SchoolPeriod;
use Doctrine\ORM\EntityManagerInterface;


final class ReportController extends AbstractController
{
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->entityManager = $entityManager;
        }
    
    #[Route('/report', name: 'app_report')]
    public function adminIndex(): Response
    {
        return $this->render('report/index.html.twig', [
            'controller_name' => 'ReportController',
        ]);
    }

    #[Route('/report-school', name: 'app_report_school')]
    public function schoolIndex(ReportService $reportService): Response
    {
        return $this->render('report/index.html.twig', [
            'report' => $reportService,
        ]);
    }

    #[Route('/report-sections', name: 'app_report_school_sections')]
    public function schoolSections(SchoolSectionReportService $reportService): Response
    {
        /** @var \App\Entity\User */
        $user = $this->getUser();
        $reportService->build($this->currentSchool);

        return $this->render('report/school.sections.html.twig', [
            'report' => $reportService,
        ]);
    }

    #[Route('/school-global-report', name: 'app_report_school_global_report')]
    public function globalReport(Request $request, SchoolReportService $reportService): Response
    {
        /** @var \App\Entity\User */
        $user = $this->getUser();
        $filterDto = new SchoolGlobalReportFilterDto();
        $filterDto->school = $this->currentSchool;
        $form = $this->createForm(SchoolGlobalReportFilterType::class, $filterDto, [
            'action' => $this->generateUrl('app_report_school_global_report'),
            'method' => 'GET',
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $filterDto = $form->getData(); // récupère les données mises à jour

            /** @var SubmitButton $submitButton */
            $submitButton = $form->get('submit');
            if (!$submitButton->isClicked()) {
                $filter = new SchoolGlobalReportFilter($filterDto);
                $reportData = $reportService->getFilteredReport($filter->getFilter());

                return $this->render('report/school_global_report.html.twig', [
                    'form' => $form->createView(),
                    'reportData' => $reportData,
                ]);
            }
        }

        $filter = new SchoolGlobalReportFilter($filterDto);
        $reportData = $reportService->getFilteredReport($filter->getFilter());

        if ($filter->getFilter()->reportType === 'pdf') {
            $options = new Options();
            $options->set('defaultFont', 'Arial');
            $dompdf = new Dompdf($options);

            $html = $this->renderView('report/school_global_report_pdf.html.twig', [
                'reportData' => $reportData,
            ]);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            return new Response(
                $dompdf->output(),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="rapport-global.pdf"',
                ]
            );
        }


        return $this->render('report/school_global_report.html.twig', [
            'form' => $form->createView(),
            'reportData' => $reportData,
        ]);
    }

    #[Route('/report-school-classes', name: 'app_report_school_classes')]
    public function schoolClassPeriods(SchoolClassReportService $reportService, Request $request): Response
    {
        /** @var \App\Entity\User */
        $user = $this->getUser();
        $schoolClassPeriod = (new SchoolClassPeriod())
            ->setSchool($this->currentSchool);
        $form = $this->createForm(SchoolClassReportFilterType::class, $schoolClassPeriod, [
            'method' => 'GET',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $classes = $reportService->getClasses($schoolClassPeriod);
            return $this->render('report/school.classes.html.twig', [
                'classes' => $classes,
                'form' => $form->createView(),
            ]);
        }

        $classes = $reportService->getClasses($schoolClassPeriod);
        return $this->render('report/school.classes.html.twig', [
            'classes' => $classes,
            'form' => $form->createView(),
        ]);
    }
}
