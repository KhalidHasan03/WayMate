<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->boolean('tracking_enabled')->default(false);
            $table->timestamp('tracking_started_at')->nullable();
            $table->timestamp('tracking_stopped_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['tracking_enabled', 'tracking_started_at', 'tracking_stopped_at']);
        });
    }
};
