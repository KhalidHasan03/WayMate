<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->unique()->after('id');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->enum('gender', ['male', 'female', 'other', 'undisclosed'])->default('undisclosed')->after('name');
            $table->date('dob')->nullable()->after('gender');
            $table->string('avatar_path')->nullable()->after('dob');
            $table->text('nid_number_encrypted')->nullable();
            $table->timestamp('nid_verified_at')->nullable();
            $table->timestamp('license_verified_at')->nullable();
            $table->enum('status', ['active', 'suspended', 'deactivated', 'deleted'])->default('active');
            $table->decimal('last_lat', 10, 7)->nullable();
            $table->decimal('last_lng', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->unsignedSmallInteger('last_accuracy_m')->nullable();
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);
            $table->unsignedInteger('trips_completed')->default(0);
            $table->unsignedInteger('no_show_count')->default(0);
            $table->enum('locale', ['en', 'bn'])->default('en');
            $table->softDeletes();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn([
                'phone', 'phone_verified_at', 'gender', 'dob', 'avatar_path', 'nid_number_encrypted',
                'nid_verified_at', 'license_verified_at', 'status', 'last_lat',
                'last_lng', 'last_location_at', 'last_accuracy_m', 'rating_avg',
                'rating_count', 'trips_completed', 'no_show_count', 'locale', 'deleted_at',
            ]);
        });
    }
};
