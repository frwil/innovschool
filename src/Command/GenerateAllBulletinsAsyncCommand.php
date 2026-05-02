<?php
namespace App\Command;

use App\Service\BulletinGenerator;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\SchoolPeriodRepository;
use App\Repository\StudentClassRepository;
use App\Repository\UserRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateAllBulletinsAsyncCommand extends Command
{
    protected static $defaultName = 'app:generate-all-bulletins-async';

    private $classRepo;
    private $periodRepo;
    private $studentRepo;
    private $userRepo;
    private $bulletinGenerator;
    private $projectDir;

    public function __construct(
        SchoolClassPeriodRepository $classRepo,
        SchoolPeriodRepository $periodRepo,
        StudentClassRepository $studentRepo,
        UserRepository $userRepo,
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
        $period = $this->currentPeriod;
        $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class, 'period' => $period]);
        
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
                $period->getId(),
                $bulLang ?? 'fr',
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