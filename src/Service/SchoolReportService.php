<?php

namespace App\Service;

use App\Entity\School;
use App\Filter\SchoolGlobalReportFilter;
use App\Repository\SchoolRepository;
use App\Repository\SchoolSectionRepository;

class SchoolReportService
{

    public function __construct(
        private SchoolSectionRepository $schoolSectionRepository,
    ) {}
    /**
     * Retourne les données du rapport filtrées selon les critères donnés.
     *
     * @param SchoolGlobalReportFilterDto $filter
     * @return array
     */
    public function getFilteredReport(SchoolGlobalReportFilterDto $filter): array
    {
        $formatedData = [];

        /** @var \App\Entity\SchoolSection[] $data */
        $data = $this->schoolSectionRepository->filterDTO($filter); // On récupère les sections filtrées

        foreach ($data as $schoolSection) {
            $section = $schoolSection->getSection();
            if (!$section) {
                continue;
            }

            $sectionName = $section->getName();
            if (!isset($formatedData[$sectionName])) {
                $formatedData[$sectionName] = [];
            }

            // On récupère toutes les classes de l'école liée à cette SchoolSection
            $school = $schoolSection->getSchool();
            $schoolClassPeriods = $school->getSchoolClasses();

            // Pour chaque "level" (Classe) de cette section...
            foreach ($section->getSectionCategories() as $level) {

                if ($filter->level && $filter->level !== $level) {
                    continue;
                }

                $levelName = $level->getName();
                if (!isset($formatedData[$sectionName][$levelName])) {
                    $formatedData[$sectionName][$levelName] = [];
                }

                // Logique d'appartenance : ici on suppose que tu peux filtrer les classes qui sont dans ce level
                // Exemple basique : par convention de nom ou une méthode personnalisée (à adapter si besoin)

                foreach ($schoolClassPeriods as $schoolClassPeriod) {
                    // 🎯 Si un filtre subClass est défini, on ignore toutes les autres classes
                    if ($filter->subClass && $schoolClassPeriod !== $filter->subClass) {
                        continue;
                    }

                    // 🎯 Filtrage par enseignant
                    if ($filter->teacher) {
                        $hasTeacher = false;
                        foreach ($schoolClassPeriod->getTeacherClasses() as $teacherClass) {
                            if ($teacherClass->getTeacher() === $filter->teacher) {
                                $hasTeacher = true;
                                break;
                            }
                        }

                        if (!$hasTeacher) {
                            continue;
                        }
                    }

                    // filtre enseignants
                    $teacherCount = $schoolClassPeriod->getTeacherClasses()->count();
                    if ($filter->minTeacher !== null && $teacherCount < $filter->minTeacher) {
                        continue;
                    }
                    if ($filter->maxTeacher !== null && $teacherCount > $filter->maxTeacher) {
                        continue;
                    }

                    // filtre effectifs de classe
                    $currentEnrollment = $schoolClassPeriod->getStudentClasses()->count();

                    if ($filter->minCurrentEnrollment !== null && $currentEnrollment < $filter->minCurrentEnrollment) {
                        continue;
                    }
                    if ($filter->maxCurrentEnrollment !== null && $currentEnrollment > $filter->maxCurrentEnrollment) {
                        continue;
                    }

                    // filtre etudiants redoublants
                    $repeaters = $schoolClassPeriod->getRepeatedStudentCount();

                    if ($filter->minRepeaters !== null && $repeaters < $filter->minRepeaters) {
                        continue;
                    }
                    if ($filter->maxRepeaters !== null && $repeaters > $filter->maxRepeaters) {
                        continue;
                    }

                    // filtre etudiants males
                    $boys = $schoolClassPeriod->getStudentsBoysCount();

                    if ($filter->minBoys !== null && $boys < $filter->minBoys) {
                        continue;
                    }
                    if ($filter->maxBoys !== null && $boys > $filter->maxBoys) {
                        continue;
                    }

                    // filtre etudiants femelles
                    $girls = $schoolClassPeriod->getStudentsGirlssCount(); // Corriger si faute

                    if ($filter->minGirls !== null && $girls < $filter->minGirls) {
                        continue;
                    }
                    if ($filter->maxGirls !== null && $girls > $filter->maxGirls) {
                        continue;
                    }

                    // filtre parents d'élèves
                    $parents = $schoolClassPeriod->getParentsCount();

                    if ($filter->minParents !== null && $parents < $filter->minParents) {
                        continue;
                    }
                    if ($filter->maxParents !== null && $parents > $filter->maxParents) {
                        continue;
                    }


                    // Hypothèse : filtrage par nom, ou bien utiliser une méthode comme matchLevel($level)
                    if (str_contains($schoolClassPeriod->getName(), $levelName)) {
                        $className = $schoolClassPeriod->getName();

                        $classDetails = [
                            'subClass' => $className,
                            'capacity' => 0,
                            'currentEnrollment' => $currentEnrollment,
                            'repeaters' => $repeaters,
                            'boys' => $boys,
                            'girls' => $girls,
                            'parents' => $parents,
                            'paid' => null,
                            'unpaid' => null,
                            'teachers' => $teacherCount,
                        ];

                        $formatedData[$sectionName][$levelName][] = $classDetails;
                    }
                }
            }
        }

        return $formatedData;
    }

    private function data(): array
    {
        return  [
            'Primaire' => [
                'CM2' => [
                    [
                        'subClass' => 'CM2 A',
                        'capacity' => 30,
                        'currentEnrollment' => 28,
                        'repeaters' => 2,
                        'boys' => 15,
                        'girls' => 13,
                        'parents' => 26,
                        'paid' => 20,
                        'unpaid' => 6,
                    ],
                    [
                        'subClass' => 'CM2 B',
                        'capacity' => 32,
                        'currentEnrollment' => 30,
                        'repeaters' => 1,
                        'boys' => 17,
                        'girls' => 13,
                        'parents' => 30,
                        'paid' => 25,
                        'unpaid' => 5,
                    ],
                ],
                'CE1' => [
                    [
                        'subClass' => 'CE1 A',
                        'capacity' => 28,
                        'currentEnrollment' => 27,
                        'repeaters' => 1,
                        'boys' => 14,
                        'girls' => 13,
                        'parents' => 26,
                        'paid' => 22,
                        'unpaid' => 4,
                    ],
                ]
            ],
            'Secondaire' => [
                '6ème' => [
                    [
                        'subClass' => '6ème A',
                        'capacity' => 25,
                        'currentEnrollment' => 24,
                        'repeaters' => 1,
                        'boys' => 12,
                        'girls' => 12,
                        'parents' => 24,
                        'paid' => 20,
                        'unpaid' => 4,
                    ],
                    [
                        'subClass' => '6ème B',
                        'capacity' => 26,
                        'currentEnrollment' => 25,
                        'repeaters' => 2,
                        'boys' => 13,
                        'girls' => 12,
                        'parents' => 25,
                        'paid' => 22,
                        'unpaid' => 3,
                    ],
                ],
            ],
        ];
    }
}
