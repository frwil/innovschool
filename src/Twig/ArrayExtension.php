<?php 
// src/Twig/ArrayExtension.php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ArrayExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('array_sum', [$this, 'arraySum']),
            new TwigFunction('array_values', [$this, 'arrayValues']),
        ];
    }

    public function arraySum(array $array): float
    {
        return array_sum($array);
    }

    public function arrayValues(array $array): array
    {
        return array_values($array);
    }
}