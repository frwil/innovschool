<?php
namespace App\Service;

class StringHelper
{
    public function toUpperNoAccent(string $str): string
    {
        // Remplace les caractères accentués par leur équivalent non accentué
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        // Remplace tout ce qui n'est pas lettre ou chiffre par un espace
        $str = preg_replace('/[^A-Za-z0-9]/', ' ', $str);
        // Met en majuscule
        $str = strtoupper($str);
        // Supprime les espaces multiples
        $str = preg_replace('/\s+/', ' ', $str);
        // Trim
        return trim($str);
    }
}