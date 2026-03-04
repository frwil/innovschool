<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\BulletinGenerator;
use App\Repository\SchoolPeriodRepository;
use App\Repository\StudentClassRepository;
use Symfony\Component\Console\Command\Command;
use App\Repository\SchoolClassPeriodRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:generate-all-bulletins')]
class GenerateAllBulletinsCommand extends Command
{
    
    private BulletinGenerator $bulletinGenerator;
    private SchoolClassPeriodRepository $classRepo;
    private SchoolPeriodRepository $periodRepo;
    private StudentClassRepository $studentRepo;
    private UserRepository $userRepo;
    private $currentPeriod;

    public function __construct(
        BulletinGenerator $bulletinGenerator,
        SchoolClassPeriodRepository $classRepo,
        SchoolPeriodRepository $periodRepo,
        StudentClassRepository $studentRepo,
        UserRepository $userRepo
    ) {
        parent::__construct();
        $this->bulletinGenerator = $bulletinGenerator;
        $this->classRepo = $classRepo;
        $this->periodRepo = $periodRepo;
        $this->studentRepo = $studentRepo;
        $this->userRepo = $userRepo;
    }

    protected function configure()
    {
        $this
            ->setDescription('Génère tous les bulletins pour une classe')
            ->addArgument('classId', InputArgument::REQUIRED, 'ID de la classe')
            ->addArgument('periodicityId', InputArgument::REQUIRED, 'ID de la périodicité')
            ->addArgument('bulletinType', InputArgument::REQUIRED, 'Type de bulletin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $classId = $input->getArgument('classId');
        $periodicityId = $input->getArgument('periodicityId');
        $bulletinType = $input->getArgument('bulletinType');

        $class = $this->classRepo->find($classId);
        $period = $this->currentPeriod;
        $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class, 'period' => $period]);
        // Par exemple, récupère le premier admin
        $user = $this->userRepo->findOneBy(['email' => 'fohom.william.francis@emailboxy.cm']);
        // Ou par son email
        // $user = $this->userRepo->findOneBy(['email' => 'admin@domaine.com']);

        foreach ($students as $student) {
            $studentId = $student->getStudent()->getId();
            /* $this->bulletinGenerator->generateBulletin(
                $studentId,
                $periodicityId,
                $bulletinType,
                $classId,
                $user // utilisateur passé ici
            ); */
            $output->writeln("Bulletin généré pour l'élève ID $studentId");
        }


        $output->writeln('Tous les bulletins ont été générés.');
        return Command::SUCCESS;
    }
}
