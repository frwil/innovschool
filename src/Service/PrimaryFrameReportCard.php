<?php

namespace App\Service;

use App\Contract\GenderEnum;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassGrade;
use App\Entity\SchoolClassSubject;
use App\Entity\SchoolClassSubjectGroup;
use App\Entity\SchoolEvaluation;
use App\Entity\SchoolEvaluationFrame;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;

class PrimaryFrameReportCard
{
    private User $studient;
    private SchoolEvaluation $evaluatioin;
    private SchoolClassPeriod $schoolClassPeriod;
    private SchoolEvaluationFrame $frame;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private NoteAppreciation $noteAppreciation,
        private ReportCardService $reportCardService,
        private SluggerInterface $slugger,
    ) {}

    public function init(
        User $student,
        SchoolEvaluationFrame $frame,
        SchoolClassPeriod $schoolClassPeriod
    ) {
        $this->studient = $student;
        $this->frame = $frame;
        $this->schoolClassPeriod = $schoolClassPeriod;
    }

    public function getCardItems(): array
    {
        $items = [];
        $ttotalPoints = 0;
        $ttotalNotes = 0;
        $middle = 0;
        $middleTimes = 0;
        
        foreach($this->frame->getSchoolEvaluations() as $evaluation){
            $this->reportCardService->init(
                $this->studient,
                $evaluation,
                $this->schoolClassPeriod
            );
            $items[] = $this->reportCardService->getCardItems();
            $ttotalPoints += $this->reportCardService->getCardItems()['totalPoints'];
            $ttotalNotes += $this->reportCardService->getCardItems()['totalNotes'];
            $middle += $this->reportCardService->getCardItems()['middle'];
            $middleTimes++;
        }
        
        $uaCount = count($items);
        $colspan = $uaCount > 0 ? intdiv(12, $uaCount) : 1;

        $tmiddle = ($middle / $middleTimes);
        $datas = [
            'items' => $items,
            'totalPoints' => $ttotalPoints,
            'totalNotes' => $ttotalNotes,
            'middle' => $tmiddle,
            'colspan' => $colspan,
            "name" => $this->getTrimName(
                $this->slugger->slug($this->frame->getName())
            )
        ];
        
        usort($datas['items'], function ($a, $b) {
            $uaA = intval(str_replace('UA', '', $a['uaName']));
            $uaB = intval(str_replace('UA', '', $b['uaName']));
            return $uaA <=> $uaB;
        });
        
        return $datas;
    }

    public function getTrimName(string $name): string
    {
       switch ($name) {
            case $this->slugger->slug('Premier trimestre'):
                return 'TRIM1';
            case $this->slugger->slug('Deuxième trimestre'):
                return 'TRIM2';
            case $this->slugger->slug('Troisième trimestre'):
                return 'TRIM3';
            default:
                return $name;
        }
    }
}