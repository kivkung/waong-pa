<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_rounds', function (Blueprint $table) {
            $table->timestamp('snapshot_taken_at')->nullable();
        });
        // Migration-local models keep backfill independent of future application casts.
        $rounds = new class extends Model
        {
            protected $table = 'meeting_rounds';
        };
        $members = new class extends Model
        {
            protected $table = 'round_members';
        };
        foreach ($rounds->newQuery()->where('phase', '<>', 'join')->cursor() as $round) {
            $latest = $members->newQuery()->where('meeting_round_id', $round->getKey())->max('confirmed_at');
            if ($latest !== null) {
                $round->setAttribute('snapshot_taken_at', $latest);
                $round->save();
            }
        }
        Schema::table('round_members', function (Blueprint $table) {
            $table->dropColumn('confirmed_at');
        });
        Schema::create('round_member_busy_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_member_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->index('round_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('round_member_busy_periods');
        Schema::table('round_members', function (Blueprint $table) {
            $table->dateTime('confirmed_at')->nullable();
        });
        Schema::table('meeting_rounds', function (Blueprint $table) {
            $table->dropColumn('snapshot_taken_at');
        });
    }
};
