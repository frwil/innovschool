<?php

namespace App\Service;

class GradeCSVGenerator
{
    public function generateCSV(int $ref, array $students): string
    {
        $csvContent = "\xEF\xBB\xBF"; // Ajoute le BOM UTF-8
        $csvContent .= "ref;uid;name;note\r\n"; // En-tête du CSV

        // Données des utilisateurs
        foreach ($students as $student) {
            /* @var $student User */
            $csvContent .= $ref . ';' . $student->getId() . ';' . $student->getFullName() . ";\r\n";
        }

        return $csvContent;
    }
}