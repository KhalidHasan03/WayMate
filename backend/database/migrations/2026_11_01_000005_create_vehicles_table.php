<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['motorcycle', 'scooter', 'car', 'other'])->default('motorcycle');
            $table->string('make', 60);
            $table->string('model', 60);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('registration_no', 30)->unique();
            $table->string('color', 30)->nullable();
            $table->unsignedTinyInteger('seat_capacity')->default(1);
            $table->enum('status', ['pending', 'active', 'rejected', 'suspended'])->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT ck_vehicle_seats CHECK (seat_capacity BETWEEN 1 AND 6)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
