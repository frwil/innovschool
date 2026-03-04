<?php

namespace App\Service;

use App\Entity\AppLicense;
use App\Entity\School;
use DateTimeImmutable;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Repository\AppLicenseRepository;
use Doctrine\ORM\EntityManagerInterface;

class LicenseManager
{
    private string $secret;
    private ?HttpClientInterface $httpClient;
    private AppLicenseRepository $appLicenseRepo;

    public function __construct(string $licenseSecret, ?HttpClientInterface $httpClient = null)
    {
        $this->secret = $licenseSecret;
        $this->httpClient = $httpClient;
    }

    /**
     * Vérifie si la licence est expirée ou si la date système a été modifiée.
     * Met à jour la date de dernier accès si tout est OK.
     */
    public function checkLicense(School $school, AppLicense $licence): void
    {
        $currentLicense = $licence;
        if (!$currentLicense) {
            throw new \Exception("Aucune licence valide trouvée pour cette école.");
        }
        $start = $currentLicense->getLicenceStartAt();
        $duration = $currentLicense->getLicenceDuration();
        $lastAccess = $school->getLastAccesAt();

        if (!$start || !$duration) {
            throw new \Exception("Licence invalide ou non initialisée.");
        }

        $now = new DateTimeImmutable('now');
        $end = $start instanceof \DateTimeImmutable
            ? (new \DateTimeImmutable($start->format('Y-m-d H:i:s')))->modify("+{$duration} days")
            : (new \DateTimeImmutable($start->format('Y-m-d H:i:s')))->modify("+{$duration} days");

        // Vérifie si la date système a été reculée
        if ($lastAccess && $now < $lastAccess) {
            throw new \Exception("Date système modifiée. Veuillez remettre la date correcte.");
        }

        // Vérifie si la licence est expirée
        if ($now > $end) {
            throw new \Exception("Licence expirée. Veuillez prolonger votre licence.");
        }

        // Vérifie l'intégrité de la licence (optionnel, nécessite un champ hash dans School)
        // Dans checkLicense, vérifiez que les données sont cohérentes
        if (method_exists($school, 'getLicenseHash')) {
            $expectedHash = $this->generateLicenseHash($currentLicense);

            // Pour debug - à retirer en production
            error_log("Generated hash: " . $expectedHash);
            error_log("Stored hash: " . $school->getLicenseHash());
            error_log("License start: " . $currentLicense->getLicenceStartAt()->format('Y-m-d H:i:s'));
            error_log("License duration: " . $currentLicense->getLicenceDuration());

            if ($school->getLicenseHash() !== $expectedHash) {
                throw new \Exception("Intégrité de la licence compromise.");
            }
        }

        // Validation serveur si internet disponible
        if ($this->httpClient && $this->isInternetAvailable()) {
            $this->validateWithServer($currentLicense);
        }

        // Met à jour la date de dernier accès
        $school->setLastAccesAt(\DateTime::createFromImmutable($now));
    }

    /**
     * Génère un hash de vérification pour l'intégrité de la licence.
     * À stocker dans un champ 'licenseHash' dans School (optionnel).
     */
    public function generateLicenseHash(AppLicense $license): string
    {
        $startAt = $license->getLicenceStartAt();
        $duration = $license->getLicenceDuration();
        $licenseHash = $license->getLicenseHash(); // Le hash existant de la licence

        $data = ($startAt ? $startAt->format('Y-m-d') : '')
            . '|' . $duration
            . '|' . $licenseHash
            . '|' . $this->secret;

        return hash('sha256', $data);
    }

    // Validation serveur (exemple simple)
    private function validateWithServer(AppLicense $license): void
    {
        try {
            /* $response = $this->httpClient->request('POST', '', [
                'json' => [
                    'hash' => $this->generateLicenseHash($license),
                    'schoolId' => $license->getSchool()->getId(),
                ]
            ]);
            $data = $response->toArray();
            if (!($data['valid'] ?? false)) {
                throw new \Exception("Licence non valide côté serveur.");
            } */
        } catch (\Throwable $e) {
            // Si le serveur ne répond pas, on laisse passer (ou on bloque selon ta politique)
        }
    }

    // Vérifie la connexion internet (ping Google)
    private function isInternetAvailable(): bool
    {
        /* try {
            $headers = @get_headers('https://www.google.com');
            return is_array($headers) && strpos($headers[0], '200') !== false;
        } catch (\Throwable $e) {
            return false;
        } */
        return false; // Désactivé pour éviter les appels externes
    }

    public function isTrialExpired(AppLicense $license): bool
    {
        $start = $license->getLicenceStartAt();
        $duration = $license->getLicenceDuration();

        if (!$start || !$duration) {
            // Si l'une des infos n'est pas définie, considère la licence comme expirée
            return true;
        }

        $now = new DateTimeImmutable('now');
        $end = $start instanceof \DateTimeImmutable
            ? $start->modify("+{$duration} days")
            : (new \DateTimeImmutable($start->format('Y-m-d H:i:s')))->modify("+{$duration} days");

        return $now > $end;
    }

    public function getTrialEndDate(AppLicense $license): ?\DateTimeImmutable
    {
        $start = $license->getLicenceStartAt();
        $duration = $license->getLicenceDuration();

        if (!$start || !$duration) {
            return null;
        }

        return $start && $duration
            ? ($start instanceof \DateTimeImmutable
                ? $start->modify("+{$duration} days")
                : (new \DateTimeImmutable($start->format('Y-m-d H:i:s')))->modify("+{$duration} days"))
            : null;
    }

    public function getTrialRemainingDays(AppLicense $license): ?int
    {
        $now = new DateTimeImmutable('now');
        $end = $this->getTrialEndDate($license);

        if (!$end) {
            return null;
        }

        $interval = $now->diff($end);
        return $interval->invert ? 0 : $interval->days;
    }

    public function validateAndApplyToken(AppLicense $license, string $token,School $school,EntityManagerInterface $entityManager): bool
    {
        // Récupérer les données de la licence ACTUELLE
        $startAt = $license->getLicenceStartAt();
        $duration = $license->getLicenceDuration();
        $currentLicenseHash = $license->getLicenseHash();

        // Générer le token attendu avec la méthode dédiée
        $expectedToken = $this->generateActivationToken($startAt, $duration, $currentLicenseHash);

        

        if ($token === $expectedToken) {
            $school->setLicenseHash($token);
            $entityManager->persist($school);
            $entityManager->flush();

            return true;
        }

        return false;
    }

    public function generateActivationToken(\DateTimeInterface $startDate, int $duration, string $baseHash): string
    {
        $data = $startDate->format('Y-m-d') . '|' . $duration . '|' . $baseHash . '|' . $this->secret;
        return hash('sha256', $data);
    }

    public function getSecret(): string
    {
        return $this->secret;
    }
}
