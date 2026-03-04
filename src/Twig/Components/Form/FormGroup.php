<?php

namespace App\Twig\Components\Form;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\Component\Form\FormView;

#[AsTwigComponent]
final class FormGroup
{
    public FormView $formel;
}
