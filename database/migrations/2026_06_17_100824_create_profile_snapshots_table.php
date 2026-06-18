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
        Schema::create('profile_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')
                ->constrained()
                ->onDelete('cascade');
            $table->unsignedBigInteger('followers_count');
            $table->unsignedBigInteger('following_count');
            $table->unsignedBigInteger('post_count');
            $table->timestampTz('captured_at');
            $table->timestampsTz();
        });

        // Index for the 30-day history query
        DB::statement("CREATE INDEX snapshots_profile_captured
                    ON profile_snapshots (profile_id, captured_at DESC)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_snapshots');
    }
};
