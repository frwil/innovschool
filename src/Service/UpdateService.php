<?php

namespace App\Service;

use App\Entity\CoreUpdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UpdateService
{

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $em,
        private ParameterBagInterface $params,
    ) {}

    public function checkForUpdate(): array
    {
        $remoteUrl = $this->params->get('app_update_json_url');
        
        try {
            $response = $this->httpClient->request('GET', $remoteUrl);
            $data = $response->toArray();
            $latestVersion = $data['version'];
            
            $localVersion = $this->getLocalVersion();

            return [
                'update_available' => version_compare($latestVersion, $localVersion, '>'),
                'latest_version' => $latestVersion,
                'current_version' => $localVersion,
            ];
        } catch (\Exception $e) {
            return ['error' => 'Impossible de vérifier la mise à jour'];
        }
    }

    public function getLocalVersion(): string
    {
        return $this->em->getRepository(CoreUpdate::class)->findOneBy([])?->getVersion() ?? '0.0.0';
    }

    public function saveNewVersion(string $newVersion): void
    {
        $coreUpdate = $this->em->getRepository(CoreUpdate::class)->findOneBy([]) ?? new CoreUpdate();
        $coreUpdate->setVersion($newVersion);
        $this->em->persist($coreUpdate);
        $this->em->flush();
    }
}
