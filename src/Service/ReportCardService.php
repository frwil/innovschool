<?php

namespace App\Service;

use App\Contract\GenderEnum;
use App\Entity\SchoolClassPeriod;
use App\Entity\SchoolClassGrade;
use App\Entity\SchoolClassSubject;
use App\Entity\SchoolClassSubjectGroup;
use App\Entity\SchoolEvaluation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node\Stmt\Switch_;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\SluggerInterface;

class ReportCardService
{
    private User $studient;
    private SchoolEvaluation $evaluatioin;
    private SchoolClassPeriod $schoolClassPeriod;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private NoteAppreciation $noteAppreciation,
        private SluggerInterface $slugger,
    ) {}

    public function init(
        User $student,
        SchoolEvaluation $evaluation,
        SchoolClassPeriod $schoolClassPeriod
    ) {
        $this->studient = $student;
        $this->evaluatioin = $evaluation;
        $this->schoolClassPeriod = $schoolClassPeriod;
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
        $items = [];
        /** @var SchoolClassSubjectGroup[] */
        $groups = $this->schoolClassPeriod->getSchoolClassSubjectGroups()->toArray();
        $ttotalPoints = 0;
        $ttotalNotes = 0;
        foreach ($groups as $group) {
            if (count($group->getSubjects()) > 0) {
                $element = [
                    'group' => $group->getName(),
                    'subjects' => [],
                ];
                $totalNotes = 0;
                $totalPoints = 0;
                /** @var SchoolClassSubject[] */
                $subjects = $group->getSubjects()->toArray();
                foreach ($subjects as $subject) {
                    $rows = 1;
                    $item = [];
                    $item['name'] = $subject->getName();
                    $totalPoint = 0;
                    /** @var SchoolClassGrade[] */
                    $grades = $this->entityManager->getRepository(SchoolClassGrade::class)
                        ->findBy([
                            'subject' => $subject,
                            'evaluation' => $this->evaluatioin,
                            'schoolClassPeriod' => $this->schoolClassPeriod,
                        ]);

                    // Oral
                    if (null !== $subject->getPointOral()) {
                        $item['oral'] = $subject->getPointOral();
                        foreach ($grades as $key => $grande) {
                            $item['oral_grade'] = $grande->getNotesJson()[$this->studient->getId()]['pointOral'] ?? 0;
                        }
                        $rows++;
                        $totalPoint += $subject->getPointOral();
                    }

                    // Written
                    if (null !== $subject->getPointWritten()) {
                        $item['written'] = $subject->getPointWritten();
                        foreach ($grades as $key => $grande) {
                            $item['written_grade'] = $grande->getNotesJson()[$this->studient->getId()]['pointWritten'] ?? 0;
                        }
                        $rows++;
                        $totalPoint += $subject->getPointWritten();
                    }


                    // Know how
                    if (null !== $subject->getPointKnowHomw()) {
                        $item['know_how'] = $subject->getPointKnowHomw();
                        foreach ($grades as $key => $grande) {
                            $item['know_how_grade'] = $grande->getNotesJson()[$this->studient->getId()]['pointKnowHomw'] ?? 0;
                        }
                        $rows++;
                        $totalPoint += $subject->getPointKnowHomw();
                    }


                    // practical
                    if (null !== $subject->getPointPractical()) {
                        $item['practical'] = $subject->getPointPractical();
                        foreach ($grades as $key => $grande) {
                            $item['practical_grade'] = $grande->getNotesJson()[$this->studient->getId()]['pointPractical'] ?? 0;
                        }
                        $rows++;
                        $totalPoint += $subject->getPointPractical();
                    }
                    // Note
                    $note = 0;
                    foreach ($grades as $key => $grande) {
                        $note = $grande->getNotesJson()[$this->studient->getId()]['note'] ?? 0;
                    }


                    $item['note'] = $note;
                    $item['rows'] = $rows;
                    $item['totalPoint'] = $totalPoint;
                    $totalNotes += $note;
                    $totalPoints += $totalPoint;
                    $item['appreciation'] = $this->noteAppreciation->doAppreciate($note, $totalPoint);
                    $element['subjects'][] = $item;
                }

                $element['totalPoints'] = $totalPoints;
                $element['totalNotes'] = $totalNotes;
                $element['appreciation'] = $this->noteAppreciation->doAppreciate(
                    $totalNotes,
                    $totalPoints
                );
                $ttotalNotes += $totalNotes;
                $ttotalPoints += $totalPoints;

                $items[] = $element;
            }
        }
        $middle = ($ttotalNotes / $ttotalPoints) * 20;

        $evaluationName = '';
        $uaName = $this->getEvalName($this->slugger->slug($this->evaluatioin->getTime()->getSlug()));
        return [
            'items' => $items,
            'totalPoints' => $ttotalPoints,
            'totalNotes' => $ttotalNotes,
            'middle' => $middle,
            'uaName' => $uaName,
        ];
    }

    private function getEvalName(string $name): string
    {
        switch ($name) {
            case $this->slugger->slug('Premiere-sequence'):
                return 'UA1';
            case $this->slugger->slug('Deuxieme-sequence'):
                return 'UA2';
            case $this->slugger->slug('Troisieme-sequence'):
                return 'UA3';
            case $this->slugger->slug('Quatrieme-sequence'):
                return 'UA4';
            case $this->slugger->slug('Cinquieme-sequence'):
                return 'UA5';
            case $this->slugger->slug('Sixieme-sequence'):
                return 'UA6';
            case $this->slugger->slug('Septieme-sequence'):
                return 'UA7';
            case $this->slugger->slug('Huitieme-sequence'):
                return 'UA8';
            case $this->slugger->slug('Neuvieme-sequence'):
                return 'UA9';

            default:
                return '';
        }
    }
}
