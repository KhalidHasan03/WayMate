<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_fares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('estimated_total_poisha')->default(0);
            $table->unsignedBigInteger('agreed_total_poisha')->nullable();
            $table->unsignedBigInteger('actual_total_poisha')->nullable();
            $table->unsignedTinyInteger('participant_count')->default(0);
            $table->boolean('provider_participates')->default(true);
            $table->unsignedBigInteger('rounding_absorbed_poisha')->default(0);
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('fare_version')->default(1);
            $table->enum('status', ['draft', 'agreeing', 'agreed', 'adjustment_pending', 'settled'])->default('draft');
            $table->timestamp('agreed_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fare_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_fare_id')->constrained()->cascadeOnDelete();
            $table->string('label', 100);
            $table->unsignedBigInteger('amount_poisha');
            $table->enum('split_method', ['equal', 'provider_only'])->default('equal');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('fare_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_fare_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('fare_version');
            $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('new_total_poisha');
            $table->string('reason', 300)->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'expired'])->default('pending');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['trip_fare_id', 'fare_version']);
        });

        Schema::create('fare_adjustment_votes', function (Blueprint $table) {
            $table->foreignId('adjustment_id')->constrained('fare_adjustments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('vote', ['accept', 'reject']);
            $table->timestamp('voted_at')->useCurrent();

            $table->primary(['adjustment_id', 'user_id']);
        });

        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('fare_version');
            $table->foreignId('fare_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('kind', ['share', 'toll', 'extra'])->default('share');
            $table->unsignedBigInteger('amount_poisha');
            $table->enum('status', ['pending', 'cash_paid', 'waived', 'void'])->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 300)->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        DB::statement('ALTER TABLE fare_items ADD CONSTRAINT ck_item_amount CHECK (amount_poisha >= 0)');
        DB::statement('ALTER TABLE contributions ADD CONSTRAINT ck_contrib_amount CHECK (amount_poisha >= 0)');
        DB::statement("ALTER TABLE contributions ADD COLUMN contribution_key VARCHAR(120) GENERATED ALWAYS AS (CONCAT(trip_id,'-',user_id,'-',fare_version,'-',kind,'-',COALESCE(fare_item_id,0))) STORED");
        DB::statement('ALTER TABLE contributions ADD UNIQUE KEY uq_contribution (contribution_key)');
    }

    public function down(): void
    {
        Schema::dropIfExists('contributions');
        Schema::dropIfExists('fare_adjustment_votes');
        Schema::dropIfExists('fare_adjustments');
        Schema::dropIfExists('fare_items');
        Schema::dropIfExists('trip_fares');
    }
};
