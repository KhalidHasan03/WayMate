<?php

namespace App\Console\Commands;

use App\Models\JoinRequest;
use App\Models\RideOffer;
use App\Models\RideRequest;
use Illuminate\Console\Command;

class ExpireStaleRecordsCommand extends Command
{
    protected $signature = 'waymate:expire-stale';

    protected $description = 'Expire open rides and pending join requests whose deadline has passed.';

    public function handle(): int
    {
        $requests = RideRequest::query()
            ->where('status', RideRequest::STATUS_OPEN)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => RideRequest::STATUS_EXPIRED]);

        $offers = RideOffer::query()
            ->where('status', RideOffer::STATUS_OPEN)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => RideOffer::STATUS_EXPIRED]);

        $joins = JoinRequest::query()
            ->where('status', JoinRequest::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => JoinRequest::STATUS_EXPIRED]);

        $this->info("Expired {$requests} ride requests, {$offers} ride offers, {$joins} join requests.");

        return self::SUCCESS;
    }
}
