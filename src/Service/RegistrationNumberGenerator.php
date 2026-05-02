<?php

namespace App\Service;

class RegistrationNumberGenerator
{
    public function generate(string $prefix, int $lastId): string
    {
        // Format the lastId to be 5 digits long, padded with zeros if necessary
        $formattedId = str_pad((string)$lastId, 5, '0', STR_PAD_LEFT);

        // Concatenate the prefix and the formatted ID
        return $prefix . $formattedId;
    }
}