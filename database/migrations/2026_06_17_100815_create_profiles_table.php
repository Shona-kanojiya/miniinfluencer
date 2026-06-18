<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100);
            $table->enum('status', ['pending','fetching','fetched','failed'])
                ->default('pending');
            $table->string('bio')->nullable();
            $table->string('profile_picture_url')->nullable();
            $table->unsignedBigInteger('followers_count')->nullable();
            $table->unsignedBigInteger('following_count')->nullable();
            $table->unsignedBigInteger('post_count')->nullable();
            $table->text('last_error')->nullable();
            $table->timestampTz('last_refreshed_at')->nullable();
            $table->timestampsTz();
        });

        // Partial unique index on lowercase username
        DB::statement("CREATE UNIQUE INDEX profiles_username_unique
                    ON profiles (lower(username))");

        // Composite index for the list query
        DB::statement("CREATE INDEX profiles_status_refreshed
                    ON profiles (status, last_refreshed_at DESC)
                    INCLUDE (username)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
