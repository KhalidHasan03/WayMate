<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_route_cache', function (Blueprint $table) {
            $table->id();
            $table->char('route_hash', 40)->unique();
            $table->decimal('origin_lat', 10, 7);
            $table->decimal('origin_lng', 10, 7);
            $table->decimal('dest_lat', 10, 7);
            $table->decimal('dest_lng', 10, 7);
            $table->longText('encoded_polyline')->nullable();
            $table->json('route_path')->nullable();
            $table->unsignedInteger('distance_m');
            $table->unsignedInteger('duration_s');
            $table->enum('provider', ['google', 'fallback'])->default('fallback');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_route_cache');
    }
};
