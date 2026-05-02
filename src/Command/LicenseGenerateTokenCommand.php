<?php

namespace App\Command;

use App\Repository\AppLicenseRepository;
use App\Repository\SchoolRepository;
use App\Service\LicenseManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'license:generate-token',
    description: 'Génère un token de renouvellement de licence pour une école.',
)]
class LicenseGenerateTokenCommand extends Command
{
    public function __construct(
        private AppLicenseRepository $licenseRepo,
        private SchoolRepository $schoolRepo,
        private LicenseManager $licenseManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'school',
                InputArgument::OPTIONAL,
                'ID ou nom (partiel) de l\'école'
            )
            ->addOption(
                'list',
                'l',
                InputOption::VALUE_NONE,
                'Lister toutes les écoles et l\'état de leur licence'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Générateur de token de renouvellement de licence');

        // ── Mode liste ────────────────────────────────────────────────────────
        if ($input->getOption('list')) {
            return $this->listSchools($io, $output);
        }

        // ── Résolution de l'école ─────────────────────────────────────────────
        $schoolArg = $input->getArgument('school');
        $school    = null;

        if ($schoolArg) {
            // Cherche par ID d'abord
            if (is_numeric($schoolArg)) {
                $school = $this->schoolRepo->find((int) $schoolArg);
            }
            // Sinon par nom (LIKE)
            if (!$school) {
                $schools = $this->schoolRepo->createQueryBuilder('s')
                    ->where('s.name LIKE :name')
                    ->setParameter('name', '%' . $schoolArg . '%')
                    ->getQuery()
                    ->getResult();

                if (count($schools) === 1) {
                    $school = $schools[0];
                } elseif (count($schools) > 1) {
                    $choices = array_map(fn($s) => sprintf('[%d] %s', $s->getId(), $s->getName()), $schools);
                    $choice  = $io->choice('Plusieurs écoles correspondent, choisissez :', $choices);
                    preg_match('/^\[(\d+)\]/', $choice, $m);
                    $school = $this->schoolRepo->find((int) $m[1]);
                }
            }
        }

        // Mode interactif si aucune école trouvée
        if (!$school) {
            $allSchools = $this->schoolRepo->findAll();
            if (empty($allSchools)) {
                $io->error('Aucune école trouvée en base de données.');
                return Command::FAILURE;
            }

            $choices = array_map(fn($s) => sprintf('[%d] %s', $s->getId(), $s->getName()), $allSchools);
            $choice  = $io->choice('Sélectionnez une école :', $choices);
            preg_match('/^\[(\d+)\]/', $choice, $m);
            $school = $this->schoolRepo->find((int) $m[1]);
        }

        if (!$school) {
            $io->error('École introuvable.');
            return Command::FAILURE;
        }

        // ── Récupération de la licence ────────────────────────────────────────
        $license = $this->licenseRepo->findOneBy(['school' => $school, 'enabled' => true]);

        if (!$license) {
            $io->error(sprintf('Aucune licence active pour l\'école "%s".', $school->getName()));
            return Command::FAILURE;
        }

        // ── Affichage des informations ────────────────────────────────────────
        $endDate       = $this->licenseManager->getEndDate($license);
        $remainingDays = $this->licenseManager->getRemainingDays($license);
        $isExpired     = $this->licenseManager->isExpired($license);

        $io->section(sprintf('École : %s (ID %d)', $school->getName(), $school->getId()));

        $table = new Table($output);
        $table->setHeaders(['Champ', 'Valeur']);
        $table->addRows([
            ['Type',          strtoupper($license->getLicenseType())],
            ['Début',         $license->getLicenceStartAt()->format('d/m/Y')],
            ['Durée',         $license->getLicenceDuration() . ' jours'],
            ['Expiration',    $endDate ? $endDate->format('d/m/Y') : '—'],
            ['Jours restants', $remainingDays],
            ['Statut',        $isExpired ? '❌ EXPIRÉE' : '✅ ACTIVE'],
            ['Hash',          substr($license->getLicenseHash(), 0, 16) . '...'],
        ]);
        $table->render();

        // ── Génération du token ───────────────────────────────────────────────
        $token = $this->licenseManager->generateActivationToken(
            $license->getLicenceStartAt(),
            $license->getLicenceDuration(),
            $license->getLicenseHash()
        );

        $io->section('Token de renouvellement');
        $io->writeln('<comment>À communiquer au superadmin de l\'école :</comment>');
        $io->writeln('');
        $io->writeln(sprintf('<info>%s</info>', $token));
        $io->writeln('');

        $io->note([
            'Ce token est valide pour la période actuelle uniquement.',
            'Il sera invalidé dès que le superadmin l\'aura utilisé pour renouveler sa licence.',
            sprintf('Date de référence communiquée par le client : %s', $license->getLicenceStartAt()->format('Y-m-d')),
        ]);

        if ($isExpired) {
            $io->warning('La licence est déjà expirée. Le renouvellement repartira à partir d\'aujourd\'hui.');
        }

        return Command::SUCCESS;
    }

    // ── Méthode liste ─────────────────────────────────────────────────────────
    private function listSchools(SymfonyStyle $io, OutputInterface $output): int
    {
        $licenses = $this->licenseRepo->findBy(['enabled' => true]);

        if (empty($licenses)) {
            $io->warning('Aucune licence active en base de données.');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['ID École', 'École', 'Type', 'Début', 'Expiration', 'Jours restants', 'Statut']);

        foreach ($licenses as $license) {
            $school    = $license->getSchool();
            $endDate   = $this->licenseManager->getEndDate($license);
            $remaining = $this->licenseManager->getRemainingDays($license);
            $expired   = $this->licenseManager->isExpired($license);

            $table->addRow([
                $school?->getId() ?? '—',
                $school?->getName() ?? '—',
                strtoupper($license->getLicenseType()),
                $license->getLicenceStartAt()->format('d/m/Y'),
                $endDate ? $endDate->format('d/m/Y') : '—',
                $license->getLicenceDuration() === 0 ? '∞ (illimitée)' : $remaining,
                $expired ? '❌ Expirée' : '✅ Active',
            ]);
        }

        $table->render();
        $io->info(sprintf('%d licence(s) trouvée(s).', count($licenses)));

        return Command::SUCCESS;
    }
}
