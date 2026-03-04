<?php

namespace App\Message;

class GenerateAllBulletinsPdfMessage
{
    public function __construct(
        private string $taskId,
        private int $classId,
        private int $periodicityId,
        private string $bulletinType,
        private int $templateId,
        private int $userId,
        private int $schoolId,
        private int $periodId,
        private string $bulLang,
        private int $passNote,
    ) {}

    public function getTaskId(): string { return $this->taskId; }
    public function getClassId(): int { return $this->classId; }
    public function getPeriodicityId(): int { return $this->periodicityId; }
    public function getBulletinType(): string { return $this->bulletinType; }
    public function getTemplateId(): int { return $this->templateId; }
    public function getUserId(): int { return $this->userId; }
    public function getSchoolId(): int { return $this->schoolId; }
    public function getPeriodId(): int { return $this->periodId; }
    public function getBulLang(): string { return $this->bulLang; }
    public function passNote(): int { return $this->passNote; }
}