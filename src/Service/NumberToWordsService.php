<?php
// src/Service/NumberToWordsService.php
namespace App\Service;

class NumberToWordsService
{
    public function convert($number): string
    {
        return $this->convertNumberToWords($number);
    }

    private function convertNumberToWords($number): string
    {
        $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
        $teens = ['dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante', 'quatre-vingt', 'quatre-vingt'];
        $thousands = ['', 'mille', 'million', 'milliard'];

        if (!is_numeric($number)) {
            return 'Non valide';
        }

        if ($number == 0) {
            return 'zéro';
        }

        $number = (int) $number;
        $words = '';

        if ($number < 0) {
            $words = 'moins ';
            $number = abs($number);
        }

        $groupIndex = 0;

        while ($number > 0) {
            $group = $number % 1000;
            $number = (int) ($number / 1000);

            if ($group > 0) {
                $groupWords = '';

                // Centaines
                if ($group >= 100) {
                    $hundreds = (int) ($group / 100);
                    $group %= 100;
                    $groupWords .= $hundreds == 1 ? 'cent' : $units[$hundreds] . ' cent';
                    if ($group > 0) {
                        $groupWords .= ' ';
                    }
                }

                // Dizaines et unités
                if ($group >= 10 && $group < 20) {
                    $groupWords .= $teens[$group - 10];
                } elseif ($group >= 20) {
                    $tensIndex = (int) ($group / 10);
                    $unitIndex = $group % 10;
                    $groupWords .= $tens[$tensIndex];
                    if ($tensIndex == 7 || $tensIndex == 9) {
                        $groupWords .= '-' . $teens[$unitIndex];
                    } elseif ($unitIndex > 0) {
                        $groupWords .= '-' . $units[$unitIndex];
                    }
                } elseif ($group > 0) {
                    $groupWords .= $units[$group];
                }

                // Gestion des milliers, millions, milliards
                if ($groupIndex > 0) {
                    if ($groupIndex == 1 && $group == 1) {
                        // Si le groupe est "mille" et vaut 1, on ne dit pas "un mille", mais juste "mille"
                        $groupWords = 'mille';
                    } else {
                        $groupWords .= ' ' . $thousands[$groupIndex];
                    }
                }

                $words = $groupWords . ' ' . $words;
            }

            $groupIndex++;
        }

        return trim($words);
    }
}
