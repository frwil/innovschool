<?php
// src/Twig/AppExtension.php
namespace App\Twig;

use Twig\TwigFunction;
use App\Service\LicenseManager;
use App\Repository\SchoolRepository;
use Twig\Extension\GlobalsInterface;
use Twig\Extension\AbstractExtension;
use App\Repository\AppLicenseRepository;
use App\Repository\SchoolPeriodRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class AppExtension extends AbstractExtension implements GlobalsInterface
{
    private $schoolRepo;
    private $periodRepo;
    private $security;
    private $requestStack;
    private $appLicenseRepo;
    private $licenseManager;
    private $currentPeriod;
    private $currentSchool;

    public function __construct(SchoolRepository $schoolRepo, SchoolPeriodRepository $periodRepo, Security $security, RequestStack $requestStack, AppLicenseRepository $appLicenseRepo, LicenseManager $licenseManager)
    {
        $this->schoolRepo = $schoolRepo;
        $this->periodRepo = $periodRepo;
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->appLicenseRepo = $appLicenseRepo;
        $this->licenseManager = $licenseManager;
    }

    public function getGlobals(): array
    {
        $session = $this->requestStack->getSession();
        $this->currentSchool = $session && $session->has('school_id') ? $this->schoolRepo->find($session->get('school_id')) : null;
        $currentPeriod = $session && $session->has('period_id') ? $this->periodRepo->find($session->get('period_id')) : null;

        $isLicenseExpired = false;
        if ($this->currentSchool) {
            $license = $this->appLicenseRepo->findOneBy(['school' => $this->currentSchool, 'enabled' => true]);
            try {
                if ($license) {
                    if ($license->getLicenceDuration() == 0) {
                        $isLicenseExpired = false;
                    } else {
                        $this->licenseManager->checkLicense($this->currentSchool, $license);
                    }
                } else {
                    $isLicenseExpired = true;
                }
            } catch (\Exception $e) {
                $isLicenseExpired = true;
            }
        }

        return [
            'schools' => $this->schoolRepo->findAll(),
            'periods' => $this->periodRepo->findAll(),
            'currentSchool' => $this->currentSchool,
            'currentPeriod' => $currentPeriod,
            'isLicenseExpired' => $isLicenseExpired,
            'isGranted' => function ($role) {
                return $this->security->isGranted($role);
            },
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_bareme', [$this, 'getBareme']),
        ];
    }

    public function getBareme(float $note, ?array $baremes): ?object
    {
        if ($baremes === null || empty($baremes)) {
            return null;
        }

        // Trier par note maximale croissante
        usort(
            $baremes,
            fn($a, $b) =>
            $a->getEvaluationAppreciationMaxNote() <=> $b->getEvaluationAppreciationMaxNote()
        );

        foreach ($baremes as $bareme) {
            if ($note <= $bareme->getEvaluationAppreciationMaxNote()) {
                return $bareme;
            }
        }

        return null;
    }
}
