<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ride_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ride_offer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['passenger', 'provider'])->default('passenger');
            $table->unsignedTinyInteger('seats')->default(1);
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('start_lat', 10, 7)->nullable();
            $table->decimal('start_lng', 10, 7)->nullable();
            $table->dateTime('intended_depart_at')->nullable();
            $table->string('message', 300)->nullable();
            $table->unsignedBigInteger('consented_contribution_poisha')->nullable();
            $table->unsignedInteger('consented_fare_version')->nullable();
            $table->char('pair_key', 41);
            $table->date('service_date');
            $table->enum('status', ['pending', 'accepted', 'declined', 'withdrawn', 'expired', 'cancelled'])->default('pending');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['ride_request_id', 'status']);
            $table->index(['ride_offer_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });

        DB::statement('ALTER TABLE join_requests ADD CONSTRAINT ck_join_source CHECK ((ride_request_id IS NULL) <> (ride_offer_id IS NULL))');
        DB::statement('ALTER TABLE join_requests ADD CONSTRAINT ck_join_seats CHECK (seats >= 1 OR role = \'provider\')');
        DB::statement('ALTER TABLE join_requests ADD CONSTRAINT ck_join_provider CHECK (role <> \'provider\' OR (vehicle_id IS NOT NULL AND start_lat IS NOT NULL AND start_lng IS NOT NULL AND intended_depart_at IS NOT NULL))');
        DB::statement("ALTER TABLE join_requests ADD COLUMN active_target_key VARCHAR(80) GENERATED ALWAYS AS (CASE WHEN status IN ('pending','accepted') THEN CONCAT(COALESCE(ride_request_id,0),'-',COALESCE(ride_offer_id,0),'-',user_id) END) STORED");
        DB::statement('ALTER TABLE join_requests ADD UNIQUE KEY uq_join_active_target (active_target_key)');
        DB::statement("ALTER TABLE join_requests ADD COLUMN active_pair_key CHAR(41) GENERATED ALWAYS AS (CASE WHEN status IN ('pending','accepted') THEN pair_key END) STORED");
        DB::statement('ALTER TABLE join_requests ADD UNIQUE KEY uq_join_active_pair_day (active_pair_key, service_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('join_requests');
    }
};
