<?php
namespace App\MessageHandler;

use App\Message\GenerateAllBulletinsMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use App\Repository\SchoolClassPeriodRepository;
use App\Repository\SchoolPeriodRepository;
use App\Repository\StudentClassRepository;
use App\Repository\UserRepository;
use App\Service\BulletinGenerator;

#[AsMessageHandler]
class GenerateAllBulletinsMessageHandler
{
    public function __construct(
        private SchoolClassPeriodRepository $classRepo,
        private SchoolPeriodRepository $periodRepo,
        private StudentClassRepository $studentRepo,
        private UserRepository $userRepo,
        private BulletinGenerator $bulletinGenerator,
        private string $projectDir // injecte via services.yaml si besoin
    ) {}

    public function __invoke(GenerateAllBulletinsMessage $message)
    {
        $progressFile = $this->projectDir . "/var/bulletin_progress_{$message->taskId}.json";
        $class = $this->classRepo->find($message->classId);
        $period = $this->periodRepo->findOneBy(['enabled' => true]);
        $students = $this->studentRepo->findBy(['schoolClassPeriod' => $class]);
        $user = $this->userRepo->findOneBy(['email' => 'fohom.william.francis@emailboxy.cm']); // adapte selon ton besoin

        $total = count($students);
        $done = 0;
        $messages = [];

        foreach ($students as $student) {
            $studentId = $student->getStudent()->getId();
            $this->bulletinGenerator->generateBulletin(
                $studentId,
                $message->periodicityId,
                $message->bulletinType,
                $message->classId,
                $user
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
    }
}