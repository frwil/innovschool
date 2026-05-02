<?php

namespace App\Service;

class NoteAppreciation
{

    public function doAppreciate(float $note, float $total): string
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
}