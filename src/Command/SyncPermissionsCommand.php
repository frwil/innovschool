<?php

namespace App\Command;

use App\Service\PermissionSyncService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-permissions',
    description: 'Synchronise le catalogue des permissions et des rôles système vers la base',
)]
class SyncPermissionsCommand extends Command
{
    public function __construct(private PermissionSyncService $permissionSyncService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simule sans rien écrire en base');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->permissionSyncService->sync($io, (bool) $input->getOption('dry-run'));

        return Command::SUCCESS;
    }
}
