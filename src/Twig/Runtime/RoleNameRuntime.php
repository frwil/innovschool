<?php

namespace App\Twig\Runtime;

use App\Contract\UserRoleEnum;
use Twig\Extension\RuntimeExtensionInterface;

class RoleNameRuntime implements RuntimeExtensionInterface
{
    public function __construct()
    {
        // Inject dependencies if needed
    }

    public function doConvert($value)
    {
        
        $names =  array_map(function ($value) {
            return UserRoleEnum::getTitleFrom($value);
        }, $value);
        return implode(', ', $names);
    }
}
