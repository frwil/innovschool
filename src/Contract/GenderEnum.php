<?php

namespace App\Contract;

enum GenderEnum: string
{
    case MALE = 'male';
    case FEMALE = 'female';
    // Ajoutez une valeur par défaut si nécessaire
    case UNKNOWN = '';
}