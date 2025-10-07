<?php

declare(strict_types=1);

namespace App\Infrastructure\Cli\Command;

use App\Application\Key\CreateKey\CreateKeyCommand;
use App\Application\Key\CreateKey\CreateKeyCommandHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'oauth2:key:generate', description: 'Generate key pair for JWT signature')]
class GenerateKeyCommand extends Command
{
    public function __construct(
        private readonly CreateKeyCommandHandler $createKeyCommandHandler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'algo',
            InputArgument::REQUIRED
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $algo */
        $algo = $input->getArgument('algo');
        try {
            $this->createKeyCommandHandler->__invoke(
                new CreateKeyCommand($algo),
            );
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
