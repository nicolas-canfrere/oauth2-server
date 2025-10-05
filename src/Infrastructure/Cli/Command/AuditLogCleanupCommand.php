<?php

declare(strict_types=1);

namespace App\Infrastructure\Cli\Command;

use App\Repository\AuditLogRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to clean up old audit log entries.
 *
 * This command deletes audit logs older than the configured retention period.
 * Should be run periodically via CRON (e.g., daily at 3 AM).
 *
 * Usage examples:
 * - php bin/console audit:cleanup                    # Use default retention from .env
 * - php bin/console audit:cleanup --days=30          # Override retention period
 * - php bin/console audit:cleanup --dry-run          # Preview deletion without executing
 */
#[AsCommand(
    name: 'oauth2:audit:cleanup',
    description: 'Clean up audit logs older than retention period',
)]
final class AuditLogCleanupCommand extends Command
{
    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogRepository,
        private readonly int $defaultRetentionDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Number of days to retain audit logs',
                $this->defaultRetentionDays
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Preview deletion without actually deleting records'
            )
            ->setHelp(
                <<<'HELP'
                The <info>%command.name%</info> command deletes audit logs older than the retention period.

                <info>Configuration:</info>
                Set retention period via AUDIT_LOG_RETENTION_DAYS environment variable (default: 90 days)

                <info>Examples:</info>
                  <comment>php bin/console %command.name%</comment>
                  Uses default retention period from configuration

                  <comment>php bin/console %command.name% --days=30</comment>
                  Delete logs older than 30 days

                  <comment>php bin/console %command.name% --dry-run</comment>
                  Preview deletion without executing

                <info>Recommended CRON schedule:</info>
                  <comment>0 3 * * * php bin/console audit:cleanup</comment>
                  Runs daily at 3:00 AM
                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var int $retentionDays */
        $retentionDays = $input->getOption('days');
        $isDryRun = (bool) $input->getOption('dry-run');

        if ($retentionDays <= 0) {
            $io->error('Retention period must be a positive number of days.');

            return Command::FAILURE;
        }

        $cutoffDate = new \DateTimeImmutable(sprintf('-%d days', $retentionDays));

        $io->title('Audit Log Cleanup');
        $io->text([
            sprintf('Retention period: <info>%d days</info>', $retentionDays),
            sprintf('Cutoff date: <info>%s</info>', $cutoffDate->format('Y-m-d H:i:s')),
            sprintf('Mode: <comment>%s</comment>', $isDryRun ? 'DRY RUN' : 'EXECUTE'),
        ]);

        if ($isDryRun) {
            $io->warning('DRY RUN mode: No records will be deleted');
        }

        try {
            $io->section('Analyzing audit logs...');

            if ($isDryRun) {
                // For dry-run, count records that would be deleted
                $beforeDate = $cutoffDate;
                $candidateCount = $this->countLogsBeforeDate($beforeDate);

                $io->success(sprintf(
                    'Would delete %d audit log(s) older than %s',
                    $candidateCount,
                    $cutoffDate->format('Y-m-d H:i:s')
                ));

                return Command::SUCCESS;
            }

            // Execute actual deletion
            $deletedCount = $this->auditLogRepository->deleteOlderThan($cutoffDate);

            if (0 === $deletedCount) {
                $io->info('No audit logs to delete.');

                return Command::SUCCESS;
            }

            $io->success(sprintf(
                'Successfully deleted %d audit log(s) older than %s',
                $deletedCount,
                $cutoffDate->format('Y-m-d H:i:s')
            ));

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error([
                'Failed to clean up audit logs:',
                $exception->getMessage(),
            ]);

            if ($output->isVerbose()) {
                $io->text($exception->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }

    /**
     * Count audit logs before a specific date (for dry-run).
     */
    private function countLogsBeforeDate(\DateTimeImmutable $beforeDate): int
    {
        // Find logs in batches and count them
        $startDate = new \DateTimeImmutable('1970-01-01');
        $logs = $this->auditLogRepository->findByDateRange($startDate, $beforeDate, limit: 100000);

        return \count($logs);
    }
}
