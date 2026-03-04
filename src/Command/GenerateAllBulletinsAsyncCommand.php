<?php
namespace App\Command;

use App\Repository\EvaluationRepository;
use App\Repository\UserRepository;
use App\Service\BulletinGenerator;
use App\Repository\SchoolRepository;
use App\Repository\SchoolPeriodRepository;
use App\Repository\StudentClassRepository;
use Symfony\Component\Console\Command\Command;
use App\Repository\SchoolClassPeriodRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[AsCommand(name: 'app:generate-all-bulletins-async')]
class GenerateAllBulletinsAsyncCommand extends Command
{

    private $classRepo;
    private $periodRepo;
    private $studentRepo;
    private $userRepo;
    private $bulletinGenerator;
    private $projectDir;
    private $currentPeriod;
    private $currentSchool;
    private $evalRepo;

    public function __construct(
        SchoolClassPeriodRepository $classRepo,
        SchoolPeriodRepository $periodRepo,
        SchoolRepository $schoolRepo,
        StudentClassRepository $studentRepo,
        UserRepository $userRepo,
        EvaluationRepository $evalRepo,
        BulletinGenerator $bulletinGenerator,
        string $projectDir
    ) {
        parent::__construct();
        $this->classRepo = $classRepo;
        $this->periodRepo = $periodRepo;
        $this->studentRepo = $studentRepo;
        $this->userRepo = $userRepo;
        $this->bulletinGenerator = $bulletinGenerator;
        $this->projectDir = $projectDir;
        $this->evalRepo=$evalRepo;
    }

    protected function configure()
    {
        $this
            ->addArgument('classId', InputArgument::REQUIRED)
            ->addArgument('periodicityId', InputArgument::REQUIRED)
            ->addArgument('bulletinType', InputArgument::REQUIRED)
            ->addArgument('taskId', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        set_time_limit(6000);
        $classId = $input->getArgument('classId');
        $periodicityId = $input->getArgument('periodicityId');
        $bulletinType = $input->getArgument('bulletinType');
        $taskId = $input->getArgument('taskId');

        $progressFile = $this->projectDir . '/var/bulletin_progress_' . $taskId . '.json';
        $class = $this->classRepo->find($classId);
        $period = $class->getSchoolPeriod();
        $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class, 'period' => $period]);
        $currentSchool=$class->getSchool();
        
        $user = $this->userRepo->findOneBy(['email' => 'fohom.william.francis@emailboxy.cm']);
        
        $total = count($students);
        $done = 0;
        $messages = [];
        foreach ($students as $student) {
            $studentId = $student->getStudent()->getId();
            $this->bulletinGenerator->generateBulletinA(
                $studentId,
                $periodicityId,
                $bulletinType,
                $classId,
                $user,
                $currentSchool->getId(),
                $period->getId(),
                $this->evalRepo,
                $class->getReportCardTemplate()->getName(),
                null,
                0,
                0,
                $bulLang ?? 'fr',
                10,
                null
            );
            $done++;
            $messages[] = "Bulletin généré pour l'élève ID $studentId";
            file_put_contents($progressFile, json_encode([
                'status' => 'running',
                'messages' => $messages,
                'percent' => intval($done / $total * 100),
            ]));
        }

        file_put_contents($progressFile, json_encode([
            'status' => 'done',
            'messages' => $messages,
            'percent' => 100,
        ]));

        return Command::SUCCESS;
    }
}