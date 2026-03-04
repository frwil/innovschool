<?php

namespace App\Service;

use App\Contract\GenderEnum;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassAttendance;
use App\Entity\SchoolClassGrade;
use App\Entity\SchoolClassSubject;
use App\Entity\SchoolEvaluation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class PvSequencePrimaryService
{
    private SchoolClassPeriod $schoolClassPeriod;
    private SchoolEvaluation $evaluation;
    private array $notes = [];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function compute(
        SchoolClassPeriod $schoolClassPeriod,
        SchoolEvaluation $evaluation,
    ): static {
        $this->schoolClassPeriod = $schoolClassPeriod;
        $this->evaluation = $evaluation;

        return $this;
    }

    public function getSequenceNotes(): array
    {
        $notes = [];

        // return notes as
        // $notes[studentId][schoolClassSubjectId] = note
        $schoolClassGrades = $this->entityManager->getRepository(SchoolClassGrade::class)
            ->findBy(['schoolClassPeriod' => $this->schoolClassPeriod, 'evaluation' => $this->evaluation]);
        /** @var \App\Entity\SchoolClassGrade */
        foreach ($schoolClassGrades as $schoolClassGrade) {
            foreach ($schoolClassGrade->getNotesJson() as $studenId => $note) {
                
                $notes[$studenId][$schoolClassGrade->getSubject()->getId()] = $note['note'];
            }
        }

        return $notes;
    }

    public function getPvHeadInfos(): array
    {
        $infos = [];

        $headcount = sizeof($this->schoolClassPeriod->getStudents());
        $infos['headcount'] = $headcount;

        $fermales = 0;
        $males = 0;
        $repeated = 0;

        /** @var \App\Entity\User */
        foreach ($this->schoolClassPeriod->getStudents() as $student) {
            if ($student->isRepeated()) {
                $repeated++;
            }
            if ($student->getGender() === GenderEnum::FEMALE) {
                $fermales++;
            } elseif ($student->getGender() === GenderEnum::MALE) {
                $males++;
            }
        }
        $infos['fermales'] = $fermales;
        $infos['males'] = $males;
        $infos['repeated'] = $repeated;

        return $infos;
    }

    public function getGroupsNote(): array
    {
        // $notes[studentId][groupeId] = note
        $groups = [];

        foreach ($this->schoolClassPeriod->getSchoolClassSubjectGroups() as $schoolClassSubjectGroup) {
            $subjectsIds = [];
            $notes = [];

            foreach ($schoolClassSubjectGroup->getSubjects() as $schoolClassSubject) {
                $subjectsIds[] = $schoolClassSubject->getId();
            }

            foreach ($this->getSequenceNotes() as $studentId => $subjects) {

                foreach ($subjects as $subjectId => $note) {
                    if (in_array($subjectId, $subjectsIds)) {
                        $notes[$studentId] = $note;
                    }
                }
                $average = $this->computeAverage($notes);
                $groups[$studentId][$schoolClassSubjectGroup->getId()] = $average;
            }
        }

        return $groups;
    }

    public function getGeneralAverage(): array
    {
        $generalAverage = [];

        foreach ($this->getGroupsNote() as $studentId => $groupes) {
            $average = $this->computeAverage($groupes);
            $generalAverage[$studentId] = $average;
        }

        return $generalAverage;
    }

    public function getRank(): array
    {
        $generalAverage = $this->getGeneralAverage();

        // sort by average
        arsort($generalAverage);

        // Étape 2 : calcul des rangs
        $studentRank = [];
        $rank = 1;
        $previousNote = null;
        $sameRankCount = 0;

        foreach ($generalAverage as $studentId => $note) {
            if ($note === $previousNote) {
                // Même note que le précédent => même rang
                $sameRankCount++;
            } else {
                // Nouvelle note => ajuster le rang
                $rank += $sameRankCount;
                $sameRankCount = 1;
            }

            $studentRank[$studentId] = $rank;
            $previousNote = $note;
        }

        return $studentRank;
    }

    private function computeAverage(array $notes): float
    {
        $total = 0;
        $count = 0;

        foreach ($notes as $note) {
            if (is_numeric($note)) {
                $total += $note;
                $count++;
            }
        }

        return $count > 0 ? round($total / $count, 2) : 0;
    }

    public function getSubjectAverageGrade(): array
    {
        $subjectAverage = [];
        $subjectNotes = $this->getSubjectNotes();

        foreach ($subjectNotes as $subjectId => $grades) {

            $average = $this->computeAverage($grades);
            $subjectAverage[$subjectId] = $average;
        }

        return $subjectAverage;
    }

    private function getSubjectNotes(): array
    {
        $subjectNotes = [];

        foreach ($this->getSequenceNotes() as $studentId => $subjects) {
            foreach ($subjects as $subjectId => $note) {
                $subjectNotes[$subjectId][] = $note;
            }
        }

        return $subjectNotes;
    }

    public function getGroupsAverageGrade(): array
    {
        $groupesAverage = [];
        $groupesNotes = $this->getGroupsNotes();

        foreach ($groupesNotes as $groupeId => $grades) {

            $average = $this->computeAverage($grades);
            $groupesAverage[$groupeId] = $average;
        }

        return $groupesAverage;
    }

    private function getGroupsNotes(): array
    {
        $groupesNotes = [];

        foreach ($this->getGroupsNote() as $studentId => $groupes) {
            foreach ($groupes as $groupeId => $note) {
                $groupesNotes[$groupeId][] = $note;
            }
        }

        return $groupesNotes;
    }

    public function getGeneralAverageAverage(): array
    {
        $generalAverage = [];
        $generalNotes = $this->getGeneralAverageGrades();

        $average = $this->computeAverage($generalNotes);
        $generalAverage['general'] = $average;

        return $generalAverage;
    }

    private function getGeneralAverageGrades(): array
    {
        $generalAverage = [];

        foreach ($this->getGeneralAverage() as $studentId => $note) {
            $generalAverage[] = $note;
        }

        return $generalAverage;
    }

    public function getGeneralLowestAverage(): array
    {
        $lowestAverage['lowest'] = min(count($this->getGeneralAverage())>0 ? $this->getGeneralAverage() : [0]);

        return $lowestAverage;
    }

    public function getGeneralHighestAverage(): array
    {
        $highestAverage['highest'] = max(count($this->getGeneralAverage())>0 ? $this->getGeneralAverage() : [0]);

        return $highestAverage;
    }

    public function getSubjectLowestAverage(): array
    {
        $subjectLowestAverage = [];

        $subjectNotess = $this->getSubjectNotes();
        foreach ($subjectNotess as $subjectId => $grades) {
            $subjectLowestAverage[$subjectId] = min($grades);
        }

        return $subjectLowestAverage;
    }

    public function getGroupLowestAverage(): array
    {
        $groupLowestAverage = [];

        $groupesNotess = $this->getGroupsNotes();
        foreach ($groupesNotess as $groupeId => $grades) {
            $groupLowestAverage[$groupeId] = min($grades);
        }

        return $groupLowestAverage;
    }

    public function getGroupHighestAverage(): array
    {
        $groupHighestAverage = [];

        $groupesNotess = $this->getGroupsNotes();
        foreach ($groupesNotess as $groupeId => $grades) {
            $groupHighestAverage[$groupeId] = max($grades);
        }

        return $groupHighestAverage;
    }

    public function getSubjectHighestAverage(): array
    {
        $subjectHighestAverage = [];

        $subjectNotess = $this->getSubjectNotes();
        foreach ($subjectNotess as $subjectId => $grades) {
            $subjectHighestAverage[$subjectId] = max($grades);
        }

        return $subjectHighestAverage;
    }

    public function getSubjectSuccessRate(): array
    {
        $subjectSuccessRate = [];

        $subjectNotess = $this->getSubjectNotes();
        foreach ($subjectNotess as $subjectId => $grades) {
            
            $successRate = $this->computeSuccessRate($grades);
            $subjectSuccessRate[$subjectId] = round($successRate, 2);
        }

        return $subjectSuccessRate;
    }

    public function getGroupSuccessRate(): array
    {
        $groupSuccessRate = [];

        $groupesNotess = $this->getGroupsNotes();
        foreach ($groupesNotess as $groupeId => $grades) {
            $successRate = $this->computeSuccessRate($grades);
            $groupSuccessRate[$groupeId] = round($successRate, 2);
        }

        return $groupSuccessRate;
    }

    public function getGeneralSuccessRate(): array
    {
        $generalSuccessRate = [];

        $generalNotes = $this->getGeneralAverageGrades();
        $successRate = $this->computeSuccessRate($generalNotes);
        $generalSuccessRate['general'] = round($successRate, 2);

        return $generalSuccessRate;
    }

    private function computeSuccessRate(array $grades): float
    {
        if (count($grades) === 0) {
            return 0;
        }
        // Count the number of successful grades (>= 10)
        $successCount = 0;
        foreach ($grades as $grade) {
            if ($grade >= 10) {
                $successCount++;
            }
        }

        return $successCount / count($grades) * 100;
    }

    public function getSubjectDeviation(): array
    {
        $subjectDeviation = [];

        $subjectNotess = $this->getSubjectNotes();
        foreach ($subjectNotess as $subjectId => $grades) {
            
            $subjectDeviation[$subjectId] = StandartDeviation::compute($grades);
        }

        return $subjectDeviation;
    }

    public function getGroupDeviation(): array
    {
        $groupDeviation = [];

        $groupesNotess = $this->getGroupsNotes();
        foreach ($groupesNotess as $groupeId => $grades) {
            $groupDeviation[$groupeId] = StandartDeviation::compute($grades);
        }

        return $groupDeviation;
    }

    public function getGeneralDeviation(): array
    {
        $generalDeviation = [];

        $generalNotes = $this->getGeneralAverageGrades();
        $generalDeviation['general'] = StandartDeviation::compute($generalNotes);

        return $generalDeviation;
    }

    public function getAverageOfTheFirst(): float
    {
        $avarages = $this->getGeneralAverage();
        if(empty($avarages)){
            return 0;
        }
        $rank = $this->getRank();
        $firstKey = array_search(1, $rank);
        $avarage = $avarages[$firstKey];
        if ($avarage === null) {
            return 0;
        }
        return $avarage;        
    }

    public function getAverageOfTheLast(): float
    {
        $avarages = $this->getGeneralAverage();
        $rank = $this->getRank();
        if(empty($rank)){
            return 0;
        }
        if(empty($avarages)){
            return 0;
        }
        // Trouver la clé correspondant au rang le plus élevé (dernier)
        $lastKey = array_search(max($rank), $rank);
        $avarage = $avarages[$lastKey];
        if ($avarage === null) {
            return 0;
        }
        return $avarage;
    }

    public function countAdmited(): array
    {
        $admitted = 0;
        $notAdmitted = 0;
        foreach ($this->getGeneralAverage() as $studentId => $note) {
            if ($note >= 10) {
                $admitted++;
            }
            if ($note < 10) {
                $notAdmitted++;
            }
        }

        return [
            'admitted' => $admitted,
            'notAdmitted' => $notAdmitted
        ];
    }

    public function countAdmitedByGender(): array
    {
        $admitted = [
            GenderEnum::MALE->value => 0,
            GenderEnum::FEMALE->value => 0,
        ];
        
        $repeatedAdmitted = 0;
        foreach ($this->getGeneralAverage() as $studentId => $note) {
            if ($note >= 10) {
                /** @var \App\Entity\User */
                $student = $this->entityManager->getRepository(User::class)->find($studentId);;
                
                if ($student->isRepeated()) {
                    $repeatedAdmitted++;
                }
                $admitted[$student->getGender()->value]++;
            } 
        }
        
        return [
            'admitted' => $admitted,
            'repeatedAdmitted' => $repeatedAdmitted
        ];
    }

    public function countAdmitedByGenderRate(): array
    {
        $total = sizeof($this->schoolClassPeriod->getStudents());
        $admitted = $this->countAdmitedByGender();
        $infos = $this->getPvHeadInfos();
        
        $totalFermales = $infos['fermales'];
        $totalMales = $infos['males'];
        
        $totalAdmitted = $admitted['admitted']['male'] + $admitted['admitted']['female'];
        
        $totalRate = $total > 0
            ? ($totalAdmitted / $total) * 100
            : 0;
        
        $malRate = $totalMales > 0
            ? ($admitted['admitted']['male'] / $totalMales) * 100
            : 0;
        
        $fermaleRate = $totalFermales > 0
            ? ($admitted['admitted']['female'] / $totalFermales) * 100
            : 0;
        
        $repeatedAdmitted = $total > 0
            ? ($admitted['repeatedAdmitted'] / $total) * 100
            : 0;
        
        return [
            'totalRate' => round($totalRate, 2),
            'malRate' => round($malRate, 2),
            'fermaleRate' => round($fermaleRate, 2),
            'repeatedAdmitted' => round($repeatedAdmitted, 2),
        ];
        
    }

    public function getAttendances(): array
    {
        $attendances = [];
        $schoolClasseAttence = $this->entityManager->getRepository(SchoolClassAttendance::class)
            ->findOneBy(['schoolClassPeriod' => $this->schoolClassPeriod, 'evaluation' => $this->evaluation]);

        if($schoolClasseAttence){
            $attendances = $schoolClasseAttence->getAttendancJson();
        }

        return $attendances;
    }

}