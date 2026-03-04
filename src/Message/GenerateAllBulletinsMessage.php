<?php
namespace App\Message;

class GenerateAllBulletinsMessage
{
    public function __construct(
        public readonly int $classId,
        public readonly int $periodicityId,
        public readonly string $bulletinType,
        public readonly string $taskId
    ) {}
}