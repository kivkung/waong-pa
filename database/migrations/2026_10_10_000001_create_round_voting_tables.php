<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('round_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_round_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->unsignedInteger('available_count');
            $table->unsignedInteger('professor_count');
            $table->json('available_member_ids');
            $table->boolean('is_winner')->default(false);
            $table->unique(['meeting_round_id', 'start_at']);
        });
        Schema::create('round_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_member_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('round_candidate_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::table('meeting_rounds', function (Blueprint $table) {
            $table->dateTime('candidates_generated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('meeting_rounds', fn (Blueprint $table) => $table->dropColumn('candidates_generated_at'));
        Schema::dropIfExists('round_votes');
        Schema::dropIfExists('round_candidates');
    }
};
