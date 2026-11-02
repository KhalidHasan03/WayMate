<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('origin_lat', 10, 7);
            $table->decimal('origin_lng', 10, 7);
            $table->string('origin_label', 255);
            $table->decimal('dest_lat', 10, 7);
            $table->decimal('dest_lng', 10, 7);
            $table->string('dest_label', 255);
            $table->char('origin_geohash', 9)->nullable();
            $table->char('dest_geohash', 9)->nullable();
            $table->longText('route_polyline')->nullable();
            $table->json('route_path')->nullable();
            $table->unsignedInteger('distance_m')->default(0);
            $table->unsignedInteger('duration_s')->default(0);
            $table->dateTime('depart_at');
            $table->unsignedSmallInteger('depart_window_min')->default(90);
            $table->unsignedTinyInteger('seats_needed')->default(1);
            $table->unsignedTinyInteger('pool_max_seats')->default(3);
            $table->boolean('accepting_copassengers')->default(true);
            $table->unsignedBigInteger('budget_per_seat_poisha')->nullable();
            $table->boolean('women_only')->default(false);
            $table->enum('transport_preference', ['any', 'external', 'private'])->default('any');
            $table->string('notes', 500)->nullable();
            $table->enum('status', ['draft', 'open', 'in_progress', 'completed', 'cancelled', 'expired'])->default('draft');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'depart_at']);
            $table->index(['origin_geohash', 'depart_at']);
            $table->index(['dest_geohash', 'depart_at']);
            $table->index('creator_id');
        });

        DB::statement('ALTER TABLE ride_requests ADD CONSTRAINT ck_req_party CHECK (seats_needed BETWEEN 1 AND pool_max_seats)');
        DB::statement('ALTER TABLE ride_requests ADD CONSTRAINT ck_req_pool CHECK (pool_max_seats BETWEEN 1 AND 4)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_requests');
    }
};
