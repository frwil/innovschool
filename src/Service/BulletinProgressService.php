<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

class BulletinProgressService
{
    public function __construct(
        private string $projectDir,
        private Filesystem $filesystem
    ) {}

    public function initializePdfTask(string $taskId, string $filename): void
    {
        $progressFile = $this->getProgressFilePath($taskId);

        $this->filesystem->dumpFile($progressFile, json_encode([
            'status' => 'pending',
            'progress' => 0,
            'message' => 'Initialisation de la génération PDF...',
            'fileUrl' => null, // Initialiser à null
            'filename' => $filename, // Garder le nom de fichier
            'updatedAt' => (new \DateTime())->format('Y-m-d H:i:s')
        ]));
    }

    public function getPdfTaskStatus(string $taskId): array
    {
        $progressFile = $this->getProgressFilePath($taskId);

        if (!$this->filesystem->exists($progressFile)) {
            throw new \RuntimeException('Tâche de génération non trouvée');
        }

        return json_decode(file_get_contents($progressFile), true);
    }

    private function getProgressFilePath(string $taskId): string
    {
        $varDir = $this->projectDir . '/var';
        if (!$this->filesystem->exists($varDir)) {
            $this->filesystem->mkdir($varDir, 0777);
        }

        return $varDir . "/pdf_generation_$taskId.json";
    }

    // Méthodes existantes pour la compatibilité
    public function updateProgress(string $taskId, int $current, int $total, string $message, ?string $fileUrl = null): void
    {
        $progressFile = $this->projectDir . "/var/bulletin_progress_$taskId.json";

        $progress = [
            'status' => $current >= $total ? 'completed' : 'processing',
            'current' => $current,
            'total' => $total,
            'message' => $message,
            'percent' => $total > 0 ? round(($current / $total) * 100) : 0,
            'updatedAt' => (new \DateTime())->format('Y-m-d H:i:s')
        ];

        // Ajouter l'URL du fichier si fournie
        if ($fileUrl) {
            $progress['fileUrl'] = $fileUrl;
        }

        $this->filesystem->dumpFile($progressFile, json_encode($progress));
    }

    public function getProgress(string $taskId): ?array
    {
        $progressFile = $this->projectDir . "/var/bulletin_progress_$taskId.json";

        if (!$this->filesystem->exists($progressFile)) {
            return null;
        }

        return json_decode(file_get_contents($progressFile), true);
    }
}
