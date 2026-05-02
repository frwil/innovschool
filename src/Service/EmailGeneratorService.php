<?php

namespace App\Service;

use Transliterator;

class EmailGeneratorService
{
    public function generateEmail(string $fullName): string
    {
        // Normaliser le nom complet (supprimer les espaces inutiles, convertir en minuscules)
        $normalizedFullName = trim(strtolower($fullName));
        $normalizedFullName = $this->removeAccents($normalizedFullName);
        // Remplacer les espaces par des points
        $emailUsername = str_replace(' ', '.', $normalizedFullName);

        // Générer l'email final
        return $emailUsername . '@emailboxy.cm';
    }

    public function removeAccents(string $text): string
    {
        return Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC')->transliterate($text);
    }
}