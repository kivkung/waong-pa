<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $roomId = DB::table('rooms')->insertGetId([
        'owner_id' => User::factory()->create()->id,
        'name' => 'Round schema test',
        'join_code' => 'ROUNDTEST001',
    ]);

    $this->roundData = [
        'room_id' => $roomId,
        'round_no' => 1,
        'search_start_date' => '2026-10-05',
        'search_end_date' => '2026-10-09',
        'join_starts_at' => '2026-10-01 08:00:00',
        'review_starts_at' => '2026-10-02 08:00:00',
        'voting_starts_at' => '2026-10-03 08:00:00',
        'final_starts_at' => '2026-10-04 08:00:00',
    ];
});

test('rounds default to at least one professor and accept all with no minimum', function () {
    DB::table('meeting_rounds')->insert($this->roundData);
    $this->assertDatabaseHas('meeting_rounds', [
        'professor_rule' => 'at_least', 'min_professors' => 1,
        'duration_minutes' => 60, 'status' => 'active', 'phase' => 'join',
    ]);

    DB::table('meeting_rounds')->update(['professor_rule' => 'all', 'min_professors' => null]);
    $this->assertDatabaseHas('meeting_rounds', ['professor_rule' => 'all', 'min_professors' => null]);
});

test('database rejects an unknown professor rule', function () {
    DB::table('meeting_rounds')->insert([...$this->roundData, 'professor_rule' => 'any']);
})->throws(QueryException::class);

test('database rejects two active rounds in the same room', function () {
    DB::table('meeting_rounds')->insert($this->roundData);
    DB::table('meeting_rounds')->insert([...$this->roundData, 'round_no' => 2]);
})->throws(QueryException::class);

test('multiple historical rounds can coexist with one active round', function () {
    foreach ([1, 2] as $number) {
        DB::table('meeting_rounds')->insert([
            ...$this->roundData, 'round_no' => $number,
            'status' => 'cancelled', 'active_marker' => null, 'cancelled_at' => '2026-10-01 09:00:00',
        ]);
    }
    DB::table('meeting_rounds')->insert([...$this->roundData, 'round_no' => 3]);
    $this->assertDatabaseCount('meeting_rounds', 3);
});

test('round numbers cannot be reused within a room even after cancellation', function () {
    DB::table('meeting_rounds')->insert([
        ...$this->roundData, 'status' => 'cancelled', 'active_marker' => null,
    ]);
    DB::table('meeting_rounds')->insert($this->roundData);
})->throws(QueryException::class);
