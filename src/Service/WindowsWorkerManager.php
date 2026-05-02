<?php
// src/Service/WindowsWorkerManager.php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class WindowsWorkerManager
{
    private LoggerInterface $logger;
    private string $projectDir;

    public function __construct(string $projectDir, LoggerInterface $logger)
    {
        $this->projectDir = $projectDir;
        $this->logger = $logger;
    }

    public function startWorker(): array
    {
        try {
            // Utiliser PowerShell pour une meilleure gestion
            $command = [
                'powershell', '-Command',
                'docker', 'exec', '-d', 'symfony-php-container',
                'php', 'bin/console', 'messenger:consume', 'async',
                '--time-limit=3600',
                '--memory-limit=4096M', 
                '--limit=50',
                '--sleep=1000',
                '-vv'
            ];

            $this->logger->info('🚀 Démarrage du worker (Windows PowerShell)');

            $process = new Process($command);
            $process->setTimeout(30);
            $process->run();

            $result = [
                'success' => $process->isSuccessful(),
                'exit_code' => $process->getExitCode(),
                'output' => $process->getOutput(),
                'error_output' => $process->getErrorOutput()
            ];

            if ($result['success']) {
                $this->logger->info('✅ Worker démarré avec succès sous Windows');
            } else {
                $this->logger->error('❌ Erreur démarrage worker Windows', $result);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('💥 Exception démarrage worker Windows', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function executeDockerCommand(string $command): array
    {
        $fullCommand = "powershell -Command \"docker {$command}\"";
        
        $process = Process::fromShellCommandline($fullCommand);
        $process->setTimeout(60);
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
            'exit_code' => $process->getExitCode()
        ];
    }
}