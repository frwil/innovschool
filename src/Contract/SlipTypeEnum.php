<?php

namespace App\Contract;

enum SlipTypeEnum: string
{
    case SINGLE = 'single';
    case GLOBAL = 'global';

    public static function getType(string $type): SlipTypeEnum
    {
        return match ($type) {
            'single' => SlipTypeEnum::SINGLE,
            'global' => SlipTypeEnum::GLOBAL,
            default => SlipTypeEnum::SINGLE,
        };
    }
}