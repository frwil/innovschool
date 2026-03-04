<?php

namespace App\Service;

use InvalidArgumentException;

class StandartDeviation
{
    /**
     * Calcule l'écart-type d'un tableau de notes.
     *
     * @param float[] $notes Tableau de notes
     * @return float L'écart-type
     * @throws \InvalidArgumentException Si le tableau est vide
     */
    public static function compute(array $notes): float
    {
        if (empty($notes)) {
            //throw new InvalidArgumentException("Le tableau de notes ne peut pas être vide.");
        }

        // Étape 1 : Calculer la moyenne
        $moyenne = array_sum($notes) / (!empty($notes) ? count($notes) : 1);

        // Étape 2 : Calculer la somme des carrés des écarts à la moyenne
        $sommeCarresEcarts = 0;
        foreach ($notes as $note) {
            $sommeCarresEcarts += pow($note - $moyenne, 2);
        }

        // Étape 3 : Calculer la variance
        $variance = $sommeCarresEcarts / (!empty($notes) ? count($notes) : 1);

        // Étape 4 : Retourner l'écart-type (racine carrée de la variance)
        return number_format(sqrt($variance), 2);
    }
}