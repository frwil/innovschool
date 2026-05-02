<?php

namespace App\Service;
;

use App\Contract\GenderEnum;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassGrade;
use App\Entity\SchoolEvaluation;
use App\Entity\SchoolEvaluationFrame;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;

class SecondaryFrameReportCard
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
        private PvSequenceService $pvSequenceService,
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

    public function headInfos(): array
    {
        $infos = [];

        $infos['name'] = $this->studient->getFullName();
        $infos['birth'] = $this->studient->getDateOfBirth();
        $infos['tutor'] = $this->studient->getTutor()->getFullName();
        $infos['tutorPhone'] = $this->studient->getTutor()->getPhone();
        $infos['schoolClassPeriod'] = $this->schoolClassPeriod->getName();
        $infos['repeated'] = $this->studient->isRepeated() ? 'Oui' : 'Non';
        $infos['gender'] = $this->studient->getGender() === GenderEnum::MALE ? 'Masculin' : 'Féminin';
        $infos['registerNumber'] = $this->studient->getRegistrationNumber();


        return $infos;
    }

    public function getCardItems(): array
    {
        $items = [
            "groups" => [],
            'evals' => [],
            'averageFactor' => 0,
            'averageFactorAverage' => 0,
        ];

        $factors = 0;
        $evaluations = [];
        foreach($this->frame->getSchoolEvaluations() as $evaluation) {
            $items['evals'][] = [
                'name' => $evaluation->getEvaluationName()
            ];
            $evaluations[] = $evaluation;
        }

        foreach( $this->schoolClassPeriod->getSchoolClassSubjectGroups() as $subjectGroup) {
            $group = [
                'name' => $subjectGroup->getName(),
                'subjects' => [],
                
            ];
            foreach ($subjectGroup->getSubjects() as $subject) {
                $subjectItem = [
                    'name' => $subject->getName(),
                    'skills' => $subject->getTargetSkills(),
                    'evals' => [],
                    'average' => 0,
                    'averageFactor' => 0,
                    'studentRank' => 0,
                    'cotation' => '',
                    'grade' => '',
                    'middle' => 0,
                    'min' => 0,
                    'max' => 0,
                    'successRate' => 0,
                ];
                $grades = [];
                /**
                 * @var array <studentId:int => notes:array>
                 */
                $studentsNotes = [];
                
                foreach($evaluations as $evaluation) {
                    
                    $subjectGrades = $this->entityManager->getRepository(SchoolClassGrade::class)->findOneBy([
                        'schoolClassPeriod' => $this->schoolClassPeriod,
                        'evaluation' => $evaluation,
                        'subject' => $subject
                    ]);
                    $grade = $subjectGrades->getNotesJson()[$this->studient->getId()];
                    
                    $subjectItem['evals'][$evaluation->getEvaluationName()] = [
                        'grade' => $grade,
                    ];
                    $grades[] = $grade;

                    $this->pvSequenceService->compute(
                        $this->schoolClassPeriod,
                        $evaluation,
                    );                    
                    
                    foreach ($subjectGrades->getNotesJson() as $studentId => $note) {
                        if (!isset($studentsNotes[$studentId])) {
                            $studentsNotes[$studentId] = [];
                        }
                        $studentsNotes[$studentId][] = $note;
                    }

                }
                $subjectItem['average'] = $average = Utils::computeAverage($grades);
                $subjectItem['averageFactor'] = $averageFactor = $subject->getCoefficient() * $average;
                $subjectItem['cotation'] = Utils::appreciationSecondaryRemark($average, 20);
                $subjectItem['grade'] = Utils::appreciationSecondary($average, 20);

                $subjectItem['studentRank'] = Utils::getStudentRank(
                    $this->studient->getId(),
                    $studentsNotes,
                );
                $studentsAverage = Utils::computeStudentsAverage($studentsNotes);
                $subjectItem['middle'] = Utils::computeAverage($studentsAverage);

                $subjectItem['min'] = Utils::getMinAverage($studentsAverage);
                $subjectItem['max'] = Utils::getMaxAverage($studentsAverage);

                $subjectItem['successRate'] = Utils::successRate($studentsAverage);
                $items['averageFactor'] += $averageFactor;

                $group['subjects'][] = $subjectItem;

                $factors += $subject->getCoefficient();
            }
            
            
            $items['groups'][] = $group;
        }

        $items['averageFactorAverage'] = $items['averageFactor'] / $factors;
        return $items;
    }
}