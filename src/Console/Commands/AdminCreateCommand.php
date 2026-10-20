<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Console\Io;
use App\Domain\Actor;
use App\Domain\DomainException;
use App\Domain\PasswordPolicy;
use App\Services\Contracts\AdminAccountServiceInterface;

/**
 * The only way to create the admin. There is no web route for it, and it refuses to run
 * once an admin exists (the database singleton constraint backs this up).
 */
final class AdminCreateCommand implements CommandInterface
{
    public function __construct(private readonly AdminAccountServiceInterface $accounts)
    {
    }

    public function name(): string
    {
        return 'admin:create';
    }

    public function description(): string
    {
        return 'Create the single back-office admin (prompts for email and password)';
    }

    public function run(array $args, Io $io): int
    {
        if ($this->accounts->adminExists()) {
            $io->error('An admin already exists. Only one admin is allowed; change its password from the Account page.');

            return 1;
        }

        $email = $io->ask('Admin email:');
        if (DIRECTORY_SEPARATOR === '\\') {
            $io->line('(Note: the password will be visible while you type on Windows terminals.)');
        }
        $password = $io->askHidden('Password (at least ' . PasswordPolicy::MIN_LENGTH . ' characters):');
        $confirmation = $io->askHidden('Confirm password:');

        if (!hash_equals($password, $confirmation)) {
            $io->error('The passwords do not match. Nothing was created.');

            return 1;
        }

        try {
            $admin = $this->accounts->createAdmin($email, $password, Actor::console());
        } catch (DomainException $e) {
            $io->error($e->getMessage() . ' Nothing was created.');

            return 1;
        }

        $io->line("Admin {$admin->email} created. Sign in at /admin/login.");

        return 0;
    }
}
