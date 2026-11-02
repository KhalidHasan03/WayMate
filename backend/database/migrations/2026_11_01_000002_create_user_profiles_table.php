<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('bio', 500)->nullable();
            $table->enum('preferred_vehicle_type', ['motorcycle', 'scooter', 'car', 'any'])->default('any');
            $table->boolean('women_only_opt_in')->default(false);
            $table->json('notify_prefs')->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('cost_share_disclaimer_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
