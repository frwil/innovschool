<?php

namespace App\Controller;

use App\Entity\CoreUpdate;
use App\Service\UpdateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class UpdateController extends AbstractController
{
    #[Route('/update', name: 'app_update_index')]
    public function update(): Response
    {
        return $this->render('update/update.html.twig', []);
    }

    #[Route('/update-check', name: 'app_update_check')]
    public function checkUpdate(UpdateService $updateService): Response
    {
        return $this->json($updateService->checkForUpdate());
    }

    #[Route('/update-core', name: 'app_update_update_core')]
    public function updateCore(KernelInterface $kernel, UpdateService $updateService, ParameterBagInterface $params): Response
    {
        $projectDir = $kernel->getProjectDir();
        $zipUrl = $params->get('app_update_zip_url');
        $zipPath = $projectDir . '/var/core.zip';
        $extractPath = $projectDir;

        // Vérifier la mise à jour
        $updateInfo = $updateService->checkForUpdate();
        if (!$updateInfo['update_available']) {
            return $this->json(['message' => 'Aucune mise à jour disponible.']);
        }

        try {
            // Télécharger le fichier ZIP
            file_put_contents($zipPath, file_get_contents($zipUrl));

            // Décompresser l'archive
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) === true) {
                $zip->extractTo($extractPath);
                $zip->close();
            } else {
                throw new \Exception('Erreur lors de l’extraction du fichier ZIP.');
            }

            // Exécuter les commandes
            $this->runCommand(['composer', 'install', '--no-interaction', '--optimize-autoloader'], $extractPath);
            $this->runCommand(['php', 'bin/console', 'doctrine:migrations:migrate', '--no-interaction'], $extractPath);
            $this->runCommand(['php', 'bin/console', 'cache:clear'], $extractPath);
            $this->runCommand(['php', 'bin/console', 'assets:install'], $extractPath);
            $this->runCommand(['php', 'bin/console', 'asset-map:compile'], $extractPath);

            // Enregistrer la nouvelle version en BDD
            $updateService->saveNewVersion($updateInfo['latest_version']);

            return $this->json(['message' => 'Mise à jour appliquée avec succès.']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur lors de la mise à jour : ' . $e->getMessage()], 500);
        }
    }

    private function runCommand(array $command, string $workingDir): void
    {
        $process = new Process($command);
        $process->setWorkingDirectory($workingDir);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
