<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use Illuminate\Console\Command;

class ApproveVehicleCommand extends Command
{
    protected $signature = 'waymate:vehicle:approve
        {registration : Vehicle registration number}
        {--reject= : Reject instead, with a reason}';

    protected $description = 'Approve or reject a vehicle registration (local operations tool).';

    public function handle(): int
    {
        $vehicle = Vehicle::query()
            ->where('registration_no', strtoupper((string) $this->argument('registration')))
            ->first();

        if (! $vehicle) {
            $this->error('Vehicle not found.');

            return self::FAILURE;
        }

        $reason = $this->option('reject');

        if (is_string($reason) && $reason !== '') {
            $vehicle->update([
                'status' => Vehicle::STATUS_REJECTED,
                'verified_at' => null,
                'rejection_reason' => $reason,
            ]);

            $this->warn("Rejected {$vehicle->registration_no}.");

            return self::SUCCESS;
        }

        $vehicle->update([
            'status' => Vehicle::STATUS_ACTIVE,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->info("Activated {$vehicle->make} {$vehicle->model} ({$vehicle->registration_no}).");

        return self::SUCCESS;
    }
}
