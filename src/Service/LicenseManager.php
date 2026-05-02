<?php

namespace App\Service;

use App\Entity\AppLicense;
use App\Entity\School;
use App\Entity\SchoolLicensePayment;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LicenseManager
{
    public function __construct(
        private string $secret,
        private ?HttpClientInterface $httpClient = null,
    ) {}

    /**
     * Vérifie la validité d'une licence pour une école.
     * Lance une exception si la licence est expirée ou compromise.
     */
    public function checkLicense(School $school, AppLicense $license): void
    {
        $start      = $license->getLicenceStartAt();
        $duration   = $license->getLicenceDuration();
        $lastAccess = $school->getLastAccesAt();

        if (!$start || !$duration) {
            throw new \RuntimeException("Licence invalide ou non initialisée.");
        }

        $now = new DateTimeImmutable();
        $end = (new DateTimeImmutable($start->format('Y-m-d H:i:s')))->modify("+{$duration} days");

        // Détection de manipulation de la date système
        if ($lastAccess && $now < \DateTimeImmutable::createFromMutable($lastAccess)) {
            throw new \RuntimeException("Date système modifiée. Veuillez remettre la date correcte.");
        }

        if ($now > $end) {
            throw new \RuntimeException("Licence expirée. Veuillez renouveler votre licence.");
        }

        // Validation de l'intégrité via hash école
        if (method_exists($school, 'getLicenseHash') && $school->getLicenseHash()) {
            $expected = $this->generateActivationToken(
                $license->getLicenceStartAt(),
                $license->getLicenceDuration(),
                $license->getLicenseHash()
            );
            if ($school->getLicenseHash() !== $expected) {
                throw new \RuntimeException("Intégrité de la licence compromise.");
            }
        }

        $school->setLastAccesAt(\DateTime::createFromImmutable($now));
    }

    /** Génère le token d'activation/renouvellement pour une licence. */
    public function generateActivationToken(
        \DateTimeInterface $startDate,
        int $duration,
        string $baseHash
    ): string {
        $data = $startDate->format('Y-m-d') . '|' . $duration . '|' . $baseHash . '|' . $this->secret;
        return hash('sha256', $data);
    }

    /**
     * Valide un token de renouvellement et applique la prolongation.
     * Persiste les changements en base si le token est valide.
     */
    public function validateAndApplyToken(
        AppLicense $license,
        string $token,
        School $school,
        EntityManagerInterface $em
    ): bool {
        $expected = $this->generateActivationToken(
            $license->getLicenceStartAt(),
            $license->getLicenceDuration(),
            $license->getLicenseHash()
        );

        if ($token !== $expected) {
            return false;
        }

        // Prolonge à partir d'aujourd'hui
        $now = new \DateTime();
        $license->setLicenceStartAt($now);

        $newHash = $this->generateActivationToken($now, $license->getLicenceDuration(), $license->getLicenseHash());
        $license->setLicenseHash($newHash);

        if (method_exists($school, 'setLicenseHash')) {
            $school->setLicenseHash($newHash);
        }

        $em->persist($license);
        $em->persist($school);
        $em->flush();

        return true;
    }

    /** Indique si la licence est expirée. */
    public function isExpired(AppLicense $license): bool
    {
        $end = $this->getEndDate($license);
        return $end === null || new DateTimeImmutable() > $end;
    }

    /** Date d'expiration de la licence. */
    public function getEndDate(AppLicense $license): ?DateTimeImmutable
    {
        $start    = $license->getLicenceStartAt();
        $duration = $license->getLicenceDuration();

        if (!$start || !$duration) {
            return null;
        }

        return (new DateTimeImmutable($start->format('Y-m-d H:i:s')))->modify("+{$duration} days");
    }

    /** Nombre de jours restants (0 si expiré). */
    public function getRemainingDays(AppLicense $license): int
    {
        $end = $this->getEndDate($license);
        if (!$end) {
            return 0;
        }
        $now = new DateTimeImmutable();
        return $now >= $end ? 0 : (int) $now->diff($end)->days;
    }

    /**
     * Enregistre un paiement pour une licence.
     * @param string $paymentMethod 'cash' | 'mobile_money' | 'bank_transfer'
     */
    public function recordPayment(
        AppLicense $license,
        int $amount,
        string $paymentMethod,
        EntityManagerInterface $em,
        ?\DateTimeInterface $date = null
    ): SchoolLicensePayment {
        $payment = new SchoolLicensePayment();
        $payment->setLicense($license);
        $payment->setPaymentAmount($amount);
        $payment->setPaymentMethod($paymentMethod);
        $payment->setDatePayment($date ?? new \DateTime());

        $em->persist($payment);
        $em->flush();

        return $payment;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }
}
