<?php

namespace App\Service;

class Utils
{
    public static function getDefaultPassword(): string
    {
        return "0000";
    }

    public static function computeAverage(array $notes): float
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

    /**
     * @param array $studentNotes <studentId:int => notes:array>
     * @return array <studentId:int => average:float>
     */
    public static function computeStudentsAverage(array $studentNotes): array
    {
        $studentsAverage = [];

        foreach ($studentNotes as $studentId => $notes) {
            $studentsAverage[$studentId] = self::computeAverage($notes);
        }

        return $studentsAverage;
    }

    public static function getMinAverage(array $notes): float
    {
        return min($notes);
    }

    public static function getMaxAverage(array $notes): float
    {
        return max($notes);
    }

    /**
     * @param array $studentNotes <studentId => note>
     * @return array
     */
    public static function getRank(array $studentNotes): array
    {
        $generalAverage = $studentNotes;

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

    public static function getStudentRank(int $studentId, array $students): int
    {
        $studentsAverage = self::computeStudentsAverage($students);
        $studentsRank = self::getRank($studentsAverage);

        return $studentsRank[$studentId] ?? 0;
    }

    public static function appreciationPrimary(float $note, float $total): string
    {
        if ($total == 0) {
            return 'NA'; // éviter division par zéro
        }

        $noteSur20 = ($note / $total) * 20;

        if ($noteSur20 >= 18) {
            return 'A+';
        } elseif ($noteSur20 >= 15) {
            return 'A';
        } elseif ($noteSur20 >= 11) {
            return 'ECA';
        } else {
            return 'NA';
        }
    }

    public static function appreciationSecondary(float $note, float $total): string
    {
        if ($total == 0) {
            return 'NA'; // éviter division par zéro
        }

        $noteSur20 = ($note / $total) * 20;

        if ($noteSur20 >= 18) {
            return 'A+';
        } elseif ($noteSur20 >= 16) {
            return 'A';
        } elseif ($noteSur20 >= 15) {
            return 'B+';
        } elseif ($noteSur20 >= 14) {
            return 'B';
        } elseif ($noteSur20 >= 12) {
            return 'C+';
        } elseif ($noteSur20 >= 10) {
            return 'C';
        } else {
            return 'D';
        }
    }

    public static function appreciationSecondaryRemark(float $note, float $total): string
    {
        if ($total == 0) {
            return 'NA'; // éviter division par zéro
        }

        $noteSur20 = ($note / $total) * 20;

        if ($noteSur20 >= 18) {
            return 'CTBA';
        } elseif ($noteSur20 >= 16) {
            return 'CTBA';
        } elseif ($noteSur20 >= 15) {
            return 'CBA';
        } elseif ($noteSur20 >= 14) {
            return 'CBA';
        } elseif ($noteSur20 >= 12) {
            return 'CA';
        } elseif ($noteSur20 >= 10) {
            return 'CMA';
        } else {
            return 'CNA';
        }
    }

    /**
     * @param array $studentNotes <studentId => note>
     * @return array
     */
    public static function successRate(array $grades): float
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
}
