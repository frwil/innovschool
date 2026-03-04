<?php

namespace App\DTO;
use Symfony\Component\HttpFoundation\Request;

class BulletinRequestDTO
{
    public function __construct(
        public readonly ?int $classId = null,
        public readonly ?int $periodicityId = null,
        public readonly ?string $bulletinType = null,
        public readonly ?int $templateId = null,
        public readonly ?int $studentId = null,
        public ?string $bulLang = null,
        public ?int $passNote = null,
        public readonly ?string $printType = null,
        public readonly ?Array $studentIds=null
    ) {}

    public static function fromRequest(Request $request): self
    {
        $studentIds = $request->get('studentIds');
        return new self(
            $request->get('classId') ? (int)$request->get('classId') : null,
            $request->get('periodicityId') ? (int)$request->get('periodicityId') : null,
            $request->get('bulletinType'),
            $request->get('templateId') ? (int)$request->get('templateId') : null,
            $request->get('studentId') ? (int)$request->get('studentId') : null,
            $request->get('bulLang') ? (string)$request->get('bulLang') : 'fr',
            $request->get('passNote') ? (int)$request->get('passNote') : 10,
            $request->get('printType') ? $request->get('printType') : null,
            is_array($studentIds) ? $studentIds : null
        );
    }

    public function validateForMassGeneration(): void
    {
        if (!$this->classId || !$this->periodicityId || !$this->bulletinType || !$this->templateId) {
            throw new \InvalidArgumentException('Paramètres manquants pour la génération en masse');
        }
    }

    public function validateForIndividualGeneration(): void
    {
        // Si printType=full, studentId n'est pas requis
        if ($this->printType !== 'full') {
            if (!$this->studentId) {
                throw new \InvalidArgumentException('ID étudiant manquant pour la génération individuelle');
            }
        }
        
        if (!$this->classId || !$this->periodicityId || !$this->bulletinType || !$this->templateId) {
            throw new \InvalidArgumentException('Paramètres manquants pour la génération individuelle');
        }
    }
}