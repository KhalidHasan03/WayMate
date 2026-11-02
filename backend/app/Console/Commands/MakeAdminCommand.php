<?php

namespace App\Console\Commands;

use App\Domains\Identity\Support\PhoneNormalizer;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

class MakeAdminCommand extends Command
{
    protected $signature = 'waymate:user:admin {phone : Bangladeshi mobile number}';

    protected $description = 'Grant the admin role to a user (local operations tool).';

    public function handle(PhoneNormalizer $normalizer): int
    {
        try {
            $phone = $normalizer->normalize((string) $this->argument('phone'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $user = User::query()->where('phone', $phone)->first();

        if (! $user) {
            $this->error("No user found for {$phone}.");

            return self::FAILURE;
        }

        $user->assignRole('admin');

        $this->info("{$user->name} is now an admin.");

        return self::SUCCESS;
    }
}
