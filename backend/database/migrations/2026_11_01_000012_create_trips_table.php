<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ride_offer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('coordinator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('transport_mode', ['undecided', 'external_ride', 'private_vehicle'])->default('undecided');
            $table->unsignedTinyInteger('pool_capacity')->nullable();
            $table->unsignedTinyInteger('seats_allocated')->nullable();
            $table->decimal('meeting_lat', 10, 7)->nullable();
            $table->decimal('meeting_lng', 10, 7)->nullable();
            $table->string('meeting_label', 255)->nullable();
            $table->enum('status', ['forming', 'ready', 'in_progress', 'completed', 'cancelled', 'expired'])->default('forming');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('coordinator_id');
            $table->index('provider_id');
        });

        DB::statement('ALTER TABLE trips ADD CONSTRAINT ck_trip_source CHECK ((ride_request_id IS NULL) <> (ride_offer_id IS NULL))');
        DB::statement('ALTER TABLE trips ADD CONSTRAINT ck_trip_capacity CHECK (seats_allocated IS NULL OR seats_allocated <= pool_capacity)');
        DB::statement('ALTER TABLE trips ADD CONSTRAINT ck_trip_request_fields CHECK (ride_request_id IS NULL OR (pool_capacity IS NOT NULL AND seats_allocated IS NOT NULL))');
        DB::statement('ALTER TABLE trips ADD CONSTRAINT ck_trip_offer_fields CHECK (ride_offer_id IS NULL OR (pool_capacity IS NULL AND seats_allocated IS NULL))');
        DB::statement('ALTER TABLE trips ADD CONSTRAINT ck_trip_provider CHECK (transport_mode <> \'private_vehicle\' OR provider_id IS NOT NULL)');

        DB::statement("ALTER TABLE trips ADD COLUMN active_request_key BIGINT GENERATED ALWAYS AS (CASE WHEN status IN ('forming','ready','in_progress') THEN ride_request_id END) STORED");
        DB::statement('ALTER TABLE trips ADD UNIQUE KEY uq_active_request_trip (active_request_key)');
        DB::statement("ALTER TABLE trips ADD COLUMN active_offer_key BIGINT GENERATED ALWAYS AS (CASE WHEN status IN ('forming','ready','in_progress') THEN ride_offer_id END) STORED");
        DB::statement('ALTER TABLE trips ADD UNIQUE KEY uq_active_offer_trip (active_offer_key)');
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
