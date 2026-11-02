<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['passenger', 'provider'])->default('passenger');
            $table->unsignedTinyInteger('seats')->default(1);
            $table->enum('status', ['consent_pending', 'confirmed', 'checked_in', 'completed', 'withdrawn', 'cancelled'])->default('consent_pending');
            $table->unsignedInteger('consented_fare_version')->default(0);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        DB::statement('ALTER TABLE trip_participants ADD CONSTRAINT ck_pt_seats CHECK ((role = \'provider\' AND seats = 0) OR (role = \'passenger\' AND seats >= 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_participants');
    }
};
