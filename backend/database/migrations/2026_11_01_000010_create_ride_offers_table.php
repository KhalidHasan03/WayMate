<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ride_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
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
            $table->unsignedSmallInteger('depart_window_min')->default(60);
            $table->unsignedTinyInteger('seats_total')->default(1);
            $table->unsignedTinyInteger('seats_allocated')->default(0);
            $table->unsignedBigInteger('cost_total_poisha')->default(0);
            $table->boolean('provider_participates')->default(true);
            $table->boolean('women_only')->default(false);
            $table->string('notes', 500)->nullable();
            $table->enum('status', ['draft', 'open', 'in_progress', 'completed', 'cancelled', 'expired'])->default('draft');
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'depart_at']);
            $table->index(['origin_geohash', 'depart_at']);
            $table->index(['dest_geohash', 'depart_at']);
            $table->index('provider_id');
            $table->index('vehicle_id');
        });

        DB::statement('ALTER TABLE ride_offers ADD CONSTRAINT ck_offer_seats CHECK (seats_total >= 1 AND seats_allocated BETWEEN 0 AND seats_total)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ride_offers');
    }
};
