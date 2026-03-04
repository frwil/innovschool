<?php
// filepath: src/Controller/HomeController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use App\Repository\SchoolRepository;
use App\Repository\SchoolPeriodRepository;
use App\Entity\School;
use App\Entity\SchoolPeriod;
use App\Entity\StudyLevel;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassSubject;
use App\Entity\StudentClass;
use App\Entity\User;

class HomeController extends AbstractController
{
    private $schoolRepository;
    private $schoolPeriodRepository;
    private $requestStack;
    private EntityManagerInterface $entityManager;
    private School $currentSchool;
    private SchoolPeriod $currentPeriod;
    private SessionInterface $session;
    /**
     * @Route("/dashboard/stats/{sectionId}", name="dashboard_stats", methods={"GET"})
     */
    #[Route('/dashboard/stats/{sectionId}', name: 'dashboard_stats', methods: ['GET'])]
    public function dashboardStats($sectionId, EntityManagerInterface $em, SessionInterface $session): JsonResponse
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $schoolClassPeriod = $this->entityManager->getRepository(SchoolClassPeriod::class)->findBy(['school' => $this->currentSchool, 'period' => $this->currentPeriod]);
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $eleves = array_filter($users, fn($u) => in_array('ROLE_STUDENT', $u->getRoles()));
        $studentClasses = $this->entityManager->getRepository(StudentClass::class)->findBy(['schoolClassPeriod' => $schoolClassPeriod]);
        $schoolClassPeriodMap = array_map(fn($scp) => $scp->getId(), $schoolClassPeriod);
        $enseignants = array_filter($users, fn($u) => in_array('ROLE_TEACHER', $u->getRoles()));
        $subjects = $this->entityManager->getRepository(SchoolClassSubject::class)->findBy(['schoolClassPeriod' => $schoolClassPeriod]);
        if ($sectionId !== 'all') {
            $elevesInscrits = array_filter($eleves, function ($e) use ($schoolClassPeriodMap, $sectionId) {
                return in_array($e->getId(), array_map(function ($sc) {
                    return $sc->getStudent()->getId();
                }, array_filter($e->getStudentClasses()->toArray(), function ($scp) use ($schoolClassPeriodMap, $sectionId) {
                    return in_array($scp->getSchoolClassPeriod()->getId(), $schoolClassPeriodMap) && $scp->getSchoolClassPeriod()->getClassOccurence()->getClasse()->getStudyLevel()->getId() === (int)$sectionId;
                })));
            });
            $enseignantsDansClasses = array_filter($enseignants, function ($e) use ($schoolClassPeriodMap, $sectionId) {
                return in_array($e->getId(), array_map(function ($sc) {
                    return $sc->getTeacher()->getId();
                }, array_filter($e->getTeacherSchoolClassSubjects()->toArray(), function ($scp) use ($schoolClassPeriodMap, $sectionId) {
                    return in_array($scp->getSchoolClassPeriod()->getId(), $schoolClassPeriodMap) && $scp->getSchoolClassPeriod()->getClassOccurence()->getClasse()->getStudyLevel()->getId() === (int)$sectionId;
                })));
            });
            $subjects = array_filter($subjects, function ($s) use ($sectionId) {
                return $s->getSchoolClassPeriod()->getClassOccurence()->getClasse()->getStudyLevel()->getId() === (int)$sectionId;
            });
        } else {
            $elevesInscrits = array_filter($eleves, function ($e) use ($studentClasses) {
                return in_array($e->getId(), array_map(function ($sc) {
                    return $sc->getStudent()->getId();
                }, $studentClasses));
            });
            $enseignantsDansClasses = array_filter($enseignants, function ($e) use ($schoolClassPeriodMap, $sectionId) {
                return in_array($e->getId(), array_map(function ($sc) {
                    return $sc->getTeacher()->getId();
                }, array_filter($e->getTeacherSchoolClassSubjects()->toArray(), function ($scp) use ($schoolClassPeriodMap, $sectionId) {
                    return in_array($scp->getSchoolClassPeriod()->getId(), $schoolClassPeriodMap);
                })));
            });
        }
        $classesMap = array_unique(array_map(fn($scp) => $scp->getSchoolClassPeriod()->getClassOccurence()->getName(), $studentClasses));

        return $this->json([
            'eleves' => count($eleves),
            'elevesInscrits' => count($elevesInscrits),
            'classes' => count($classesMap),
            'enseignants' => count($enseignants),
            'enseignantsDansClasses' => count($enseignantsDansClasses),
            'matieres' => count($subjects),
        ]);
    }

    public function __construct(EntityManagerInterface $entityManager, SchoolRepository $schoolRepository, SchoolPeriodRepository $schoolPeriodRepository, RequestStack $requestStack)
    {
        $this->schoolRepository = $schoolRepository;
        $this->schoolPeriodRepository = $schoolPeriodRepository;
        $this->requestStack = $requestStack;
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'app_home')]
    public function index(SessionInterface $session): Response
    {
        $this->session = $session;
        $this->currentSchool = $this->entityManager->getRepository(School::class)->find($this->session->get('school_id'));
        $this->currentPeriod = $this->entityManager->getRepository(SchoolPeriod::class)->find($this->session->get('period_id'));
        $request = $this->requestStack->getCurrentRequest();
        $schoolId = $request->getSession()->get('school_id');
        $periodId = $request->getSession()->get('period_id');

        $school = $schoolId ? $this->schoolRepository->find($schoolId) : null;
        $period = $periodId ? $this->schoolPeriodRepository->find($periodId) : null;
        $schools = $this->schoolRepository->findAll();
        $periods = $this->schoolPeriodRepository->findAll();
        $studyLevels = $this->entityManager->getRepository(StudyLevel::class)->findAll();
        $user = $this->getConnectedUser();
        $config = $user->getBaseConfigurations()->toArray();
        if (count($config) > 0) {
            $studyLevels = (count($config[0]->getSectionList()) > 0) ? $this->entityManager->getRepository(StudyLevel::class)->findBy(['id' => $config[0]->getSectionList()]) : $studyLevels;
        } else {
            $config = [];
        }


        return $this->render('/home/index.html.twig', [
            'currentSchool' => $school,
            'currentPeriod' => $period,
            'schools' => $schools,
            'periods' => $periods,
            'sections' => $studyLevels,
        ]);
    }

    public function getConnectedUser(): User
    {
        return $this->getUser();
    }
}
