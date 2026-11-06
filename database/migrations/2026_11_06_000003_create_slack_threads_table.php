<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The first Slack message about an issue in each channel; later messages reply in its thread.
        Schema::create('slack_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('issue');
            $table->string('channel', 40);
            $table->string('ts', 32);
            $table->timestamps();

            $table->unique(['issue', 'channel']);
        });

        // Who reported an issue from Slack, so they hear back when it's done.
        Schema::create('slack_reporters', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('issue')->unique();
            $table->string('slack_user', 32);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_reporters');
        Schema::dropIfExists('slack_threads');
    }
};
