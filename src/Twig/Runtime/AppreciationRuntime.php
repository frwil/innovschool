<?php

namespace App\Twig\Runtime;

use App\Service\NoteAppreciation;
use Twig\Extension\RuntimeExtensionInterface;

class AppreciationRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private NoteAppreciation $noteAppreciation
    ) {}

    public function doAppreciate(float $note, float $total): string
    {
        return $this->noteAppreciation->doAppreciate($note, $total);
    }
}
