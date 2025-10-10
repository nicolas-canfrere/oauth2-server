<?php

declare(strict_types=1);

namespace App\Infrastructure\Cli\Command;

use App\Application\User\CreateUser\CreateUserCommand as ApplicationCreateUserCommand;
use App\Application\User\CreateUser\CreateUserCommandHandler;
use App\Domain\User\Repository\UserRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:create', description: 'Create a new user')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly CreateUserCommandHandler $handler,
        private readonly UserRepositoryInterface $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'User email address')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'User password')
            ->addOption('role', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'User role (repeat option for multiple)')
            ->addOption('2fa', null, InputOption::VALUE_NONE, 'Enable two-factor authentication')
            ->addOption('totp-secret', null, InputOption::VALUE_REQUIRED, 'TOTP secret for 2FA');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $this->resolveStringOption($io, $input->getOption('email'), 'User email');
        if (null === $email) {
            $io->error('Email is required.');

            return Command::FAILURE;
        }

        $password = $this->resolvePasswordOption($io, $input->getOption('password'));
        if (null === $password) {
            $io->error('Password is required.');

            return Command::FAILURE;
        }

        /** @var list<string> $roles */
        $roles = $this->resolveListOption(
            $io,
            $input->getOption('role'),
            'User roles (comma separated, default: ROLE_USER)',
            'ROLE_USER'
        );

        if ([] === $roles) {
            $roles = ['ROLE_USER'];
        }

        // Check if trying to create an admin user when one already exists
        if (in_array('ROLE_ADMIN', $roles, true) && $this->userRepository->adminExists()) {
            $io->error('An admin user already exists. Only one admin user is allowed.');

            return Command::FAILURE;
        }

        $is2faEnabled = (bool) $input->getOption('2fa');
        $totpSecret = null;

        if ($is2faEnabled) {
            $totpSecret = $this->resolveStringOption(
                $io,
                $input->getOption('totp-secret'),
                'TOTP secret (leave empty to skip)'
            );
        }

        try {
            $userId = ($this->handler)(
                new ApplicationCreateUserCommand(
                    email: $email,
                    plainPassword: $password,
                    roles: $roles,
                    isTwoFactorEnabled: $is2faEnabled,
                    totpSecret: $totpSecret,
                )
            );
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('User created successfully.');
        $io->writeln(sprintf('User ID: <info>%s</info>', $userId));
        $io->writeln(sprintf('Email: <info>%s</info>', $email));
        $io->writeln(sprintf('Roles: <info>%s</info>', implode(', ', $roles)));

        if ($is2faEnabled) {
            $io->writeln('Two-factor authentication: <info>enabled</info>');
        }

        return Command::SUCCESS;
    }

    private function resolveStringOption(SymfonyStyle $io, mixed $value, string $question): ?string
    {
        if (is_string($value) && '' !== trim($value)) {
            return trim($value);
        }

        /** @var string|null $answer */
        $answer = $io->ask($question);
        if (null === $answer) {
            return null;
        }

        $answer = trim($answer);

        return '' !== $answer ? $answer : null;
    }

    private function resolvePasswordOption(SymfonyStyle $io, mixed $value): ?string
    {
        if (is_string($value) && '' !== $value) {
            return $value;
        }

        /** @var string|null $answer */
        $answer = $io->askHidden('User password');
        if (null === $answer) {
            return null;
        }

        $answer = trim($answer);

        return '' !== $answer ? $answer : null;
    }

    /**
     * @return list<string>
     */
    private function resolveListOption(
        SymfonyStyle $io,
        mixed $values,
        string $question,
        string $default,
    ): array {
        if (is_array($values) && [] !== $values) {
            /** @var array<int, mixed> $values */
            return $this->normaliseList($values);
        }
        /** @var string|null $answer */
        $answer = $io->ask($question, '' === $default ? null : $default);

        return $this->normaliseListFromString($answer);
    }

    /**
     * @param array<int, mixed> $values
     *
     * @return list<string>
     */
    private function normaliseList(array $values): array
    {
        $items = array_map(
            static fn(mixed $value): string => is_string($value) ? trim($value) : '',
            $values
        );

        return array_values(
            array_filter($items, static fn(string $value): bool => '' !== $value)
        );
    }

    /**
     * @return list<string>
     */
    private function normaliseListFromString(?string $value): array
    {
        if (null === $value) {
            return [];
        }

        $trimmed = trim($value);
        if ('' === $trimmed) {
            return [];
        }

        $items = preg_split('/\s*,\s*/', $trimmed) ?: [];

        return array_values(
            array_filter(
                array_map(static fn(string $item): string => trim($item), $items),
                static fn(string $item): bool => '' !== $item
            )
        );
    }
}
