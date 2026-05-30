<?php

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;

class ProgressQueryDTO
{
    public function __construct(
        public readonly ?int $classId = null,
        public readonly string|int|null $periodicityId = null,
        public readonly ?string $bulletinType = null,
        public readonly ?int $studentId = null
    ) {}

    public static function fromRequest(Request $request): self
    {
        $periodicityId = $request->query->get('periodicityId');
        if ($periodicityId !== null && $periodicityId !== 'all') {
            $periodicityId = (int)$periodicityId;
        }

        return new self(
            $request->query->get('classId') ? (int)$request->query->get('classId') : null,
            $periodicityId,
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