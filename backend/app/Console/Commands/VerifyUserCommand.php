<?php

namespace App\Console\Commands;

use App\Domains\Identity\Support\PhoneNormalizer;
use App\Models\User;
use Illuminate\Console\Command;
use InvalidArgumentException;

class VerifyUserCommand extends Command
{
    protected $signature = 'waymate:user:verify
        {phone : Bangladeshi mobile number}
        {--nid-only : Verify only the NID, not the driving licence}';

    protected $description = 'Mark a user as identity verified (local operations tool).';

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

        $user->forceFill([
            'nid_verified_at' => $user->nid_verified_at ?? now(),
            'license_verified_at' => $this->option('nid-only')
                ? $user->license_verified_at
                : ($user->license_verified_at ?? now()),
        ])->save();

        $this->info("Verified {$user->name} ({$phone}).");

        return self::SUCCESS;
    }
}
