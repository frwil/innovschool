<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;

class ProgressQueryDTO
{
    public function __construct(
        public readonly ?int $classId = null,
        public readonly ?int $periodicityId = null,
        public readonly ?string $bulletinType = null,
        public readonly ?int $studentId = null
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            $request->query->get('classId') ? (int)$request->query->get('classId') : null,
            $request->query->get('periodicityId') ? (int)$request->query->get('periodicityId') : null,
            $request->query->get('bulletinType'),
            $request->query->get('studentId') ? (int)$request->query->get('studentId') : null
        );
    }

    public function validateForProgress(): void
    {
        if (!$this->classId || !$this->periodicityId || !$this->bulletinType) {
            throw new \InvalidArgumentException('Paramètres manquants pour la progression');
        }
    }
}