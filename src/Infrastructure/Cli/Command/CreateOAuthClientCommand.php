<?php

declare(strict_types=1);

namespace App\Infrastructure\Cli\Command;

use App\Application\OAuthClient\CreateOAuthClient\CreateOAuthClientCommand as ApplicationCreateOAuthClientCommand;
use App\Application\OAuthClient\CreateOAuthClient\CreateOAuthClientCommandHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'oauth2:client:create', description: 'Create a new OAuth2 client')]
final class CreateOAuthClientCommand extends Command
{
    public function __construct(
        private readonly CreateOAuthClientCommandHandler $handler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Client display name')
            ->addOption('redirect-uri', null, InputOption::VALUE_REQUIRED, 'Registered redirect URI')
            ->addOption('grant-type', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Grant type (repeat option for multiple)')
            ->addOption('scope', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Allowed scope (repeat option for multiple)')
            ->addOption('confidential', null, InputOption::VALUE_NONE, 'Mark the client as confidential (requires a secret)')
            ->addOption('public', null, InputOption::VALUE_NONE, 'Explicitly mark the client as public')
            ->addOption('pkce-required', null, InputOption::VALUE_NONE, 'Require PKCE when using authorization code flow')
            ->addOption('no-pkce', null, InputOption::VALUE_NONE, 'Disable PKCE requirement')
            ->addOption('client-id', null, InputOption::VALUE_REQUIRED, 'Provide a custom client identifier')
            ->addOption('client-secret', null, InputOption::VALUE_REQUIRED, 'Provide a pre-defined client secret (confidential clients only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $this->resolveStringOption($io, $input->getOption('name'), 'Client name');
        if (null === $name) {
            $io->error('Client name is required.');

            return Command::FAILURE;
        }

        $redirectUri = $this->resolveStringOption($io, $input->getOption('redirect-uri'), 'Redirect URI');
        if (null === $redirectUri) {
            $io->error('Redirect URI is required.');

            return Command::FAILURE;
        }

        /** @var list<string> $grantTypes */
        $grantTypes = $this->resolveListOption(
            $io,
            $input->getOption('grant-type'),
            'Grant types (comma separated)',
            'authorization_code'
        );

        if ([] === $grantTypes) {
            $io->error('At least one grant type is required.');

            return Command::FAILURE;
        }

        /** @var list<string> $scopes */
        $scopes = $this->resolveListOption(
            $io,
            $input->getOption('scope'),
            'Scopes (comma separated, optional)',
            default: ''
        );

        $isConfidential = $this->resolveConfidentialFlag($io, $input);
        if (null === $isConfidential) {
            return Command::FAILURE;
        }

        $pkceRequired = $this->resolvePkceFlag($io, $input, $isConfidential);
        if (null === $pkceRequired) {
            return Command::FAILURE;
        }

        $clientId = $this->resolveClientId($io, $input->getOption('client-id'));
        $clientSecret = $this->resolveClientSecret(
            $io,
            $input->getOption('client-secret'),
            $isConfidential
        );

        try {
            $result = ($this->handler)(
                new ApplicationCreateOAuthClientCommand(
                    name: $name,
                    redirectUri: $redirectUri,
                    grantTypes: $grantTypes,
                    scopes: $scopes,
                    isConfidential: $isConfidential,
                    pkceRequired: $pkceRequired,
                    clientId: $clientId,
                    clientSecret: $clientSecret,
                )
            );
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('OAuth2 client created successfully.');
        $io->writeln(sprintf('Client ID: <info>%s</info>', $result['client_id']));

        if (null !== $result['client_secret']) {
            $io->writeln(sprintf('Client secret: <comment>%s</comment>', $result['client_secret']));
            $io->warning('Store this client secret securely; it will not be shown again.');
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

    private function resolveConfidentialFlag(SymfonyStyle $io, InputInterface $input): ?bool
    {
        $confidential = (bool) $input->getOption('confidential');
        $public = (bool) $input->getOption('public');

        if ($confidential && $public) {
            $io->error('Options "--confidential" and "--public" cannot be used together.');

            return null;
        }

        if ($confidential) {
            return true;
        }

        if ($public) {
            return false;
        }

        return $io->confirm('Is the client confidential?', false);
    }

    private function resolvePkceFlag(SymfonyStyle $io, InputInterface $input, bool $isConfidential): ?bool
    {
        $pkceRequired = (bool) $input->getOption('pkce-required');
        $pkceDisabled = (bool) $input->getOption('no-pkce');

        if ($pkceRequired && $pkceDisabled) {
            $io->error('Options "--pkce-required" and "--no-pkce" cannot be used together.');

            return null;
        }

        if ($pkceRequired) {
            return true;
        }

        if ($pkceDisabled) {
            return false;
        }

        $default = $isConfidential ? false : true;

        return $io->confirm('Require PKCE?', $default);
    }

    private function resolveClientId(SymfonyStyle $io, mixed $value): ?string
    {
        if (is_string($value) && '' !== trim($value)) {
            return trim($value);
        }

        if (!$io->confirm('Do you want to provide a custom client ID?', false)) {
            return null;
        }
        /** @var string|null $answer */
        $answer = $io->ask('Client ID');
        if (null === $answer) {
            return null;
        }

        $answer = trim($answer);

        return '' !== $answer ? $answer : null;
    }

    private function resolveClientSecret(
        SymfonyStyle $io,
        mixed $value,
        bool $isConfidential,
    ): ?string {
        if (!$isConfidential) {
            return null;
        }

        if (is_string($value) && '' !== $value) {
            return $value;
        }

        if (!$io->confirm('Provide the client secret manually?', false)) {
            return null;
        }
        /** @var string|null $answer */
        $answer = $io->askHidden('Client secret');
        if (null === $answer) {
            return null;
        }

        $answer = trim($answer);

        return '' !== $answer ? $answer : null;
    }
}
