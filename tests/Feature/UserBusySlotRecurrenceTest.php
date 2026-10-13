<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->input = [
        'name' => 'Private recurring lesson',
        'start_at' => '2026-01-31T09:00', 'end_at' => '2026-01-31T10:30',
        'frequency' => 'monthly', 'occurrence_count' => 3,
    ];
});

test('monthly recurrence clamps short months without shifting the original day', function (string $start, array $expected) {
    $this->input['start_at'] = $start.'T09:00';
    $this->input['end_at'] = $start.'T10:30';
    $this->post(route('activities.store'), $this->input)->assertSessionHasNoErrors();
    $activity = $this->user->busySlots()->firstOrFail();
    $periods = $activity->occurrencesBetween(CarbonImmutable::parse($start), CarbonImmutable::parse($start)->addMonths(4));
    expect(array_column($periods, 'start_at'))->toHaveCount(3);
    foreach ($periods as $index => $period) {
        expect($period['start_at']->format('Y-m-d H:i'))->toBe($expected[$index].' 09:00');
        expect($period['end_at']->format('H:i'))->toBe('10:30');
    }
    $this->assertDatabaseCount('user_busy_slots', 1);
    $this->assertDatabaseCount('busy_slot_recurrence_rules', 1);
})->with([
    ['2026-01-31', ['2026-01-31', '2026-02-28', '2026-03-31']],
    ['2024-01-31', ['2024-01-31', '2024-02-29', '2024-03-31']],
    ['2026-01-30', ['2026-01-30', '2026-02-28', '2026-03-30']],
    ['2024-02-29', ['2024-02-29', '2024-03-29', '2024-04-29']],
]);

test('weekly recurrence preserves duration across days and excludes touching endpoints and exhausted series', function () {
    $this->post(route('activities.store'), [
        ...$this->input, 'frequency' => 'weekly', 'occurrence_count' => 3,
        'start_at' => '2026-10-01T23:00', 'end_at' => '2026-10-03T01:00',
    ])->assertSessionHasNoErrors();
    $activity = $this->user->busySlots()->firstOrFail();
    $periods = $activity->occurrencesBetween(CarbonImmutable::parse('2026-10-09'), CarbonImmutable::parse('2026-10-10 01:00'));
    expect($periods)->toHaveCount(1);
    expect($periods[0]['start_at']->format('Y-m-d H:i'))->toBe('2026-10-08 23:00');
    expect($periods[0]['end_at']->format('Y-m-d H:i'))->toBe('2026-10-10 01:00');
    expect($activity->occurrencesBetween(CarbonImmutable::parse('2026-10-10 01:00'), CarbonImmutable::parse('2026-10-15 23:00')))->toBe([]);
    expect($activity->occurrencesBetween(CarbonImmutable::parse('2026-10-18'), CarbonImmutable::parse('2026-11-01')))->toBe([]);
});

test('large counts and old masters only expand the requested window', function (string $frequency, string $date) {
    $this->post(route('activities.store'), [
        ...$this->input, 'frequency' => $frequency, 'occurrence_count' => 2147483647,
        'start_at' => '1900-01-01T09:00', 'end_at' => '1900-01-01T10:00',
    ])->assertSessionHasNoErrors();
    $activity = $this->user->busySlots()->firstOrFail();
    $periods = $activity->occurrencesBetween(CarbonImmutable::parse($date), CarbonImmutable::parse($date)->addDay());
    expect($periods)->toHaveCount(1);
    expect($periods[0]['start_at']->format('Y-m-d H:i'))->toBe($date.' 09:00');
})->with([['weekly', '2026-10-12'], ['monthly', '2026-10-01']]);

test('owners can switch recurrence modes and deleting a series removes its rule', function () {
    $this->post(route('activities.store'), [...$this->input, 'frequency' => 'none'])->assertSessionHasNoErrors();
    $activity = $this->user->busySlots()->firstOrFail();
    expect($activity->recurrence_rule_id)->toBeNull();
    $this->assertDatabaseCount('busy_slot_recurrence_rules', 0);

    $url = route('activities.update', $activity);
    $this->patch($url, $this->input)->assertSessionHasNoErrors();
    $ruleId = $activity->fresh()->recurrence_rule_id;
    $this->patch($url, [...$this->input, 'frequency' => 'weekly', 'occurrence_count' => 5])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('busy_slot_recurrence_rules', ['id' => $ruleId, 'frequency' => 'weekly', 'occurrence_count' => 5]);
    $this->assertDatabaseCount('busy_slot_recurrence_rules', 1);

    $this->patch($url, [...$this->input, 'frequency' => 'none'])->assertSessionHasNoErrors();
    expect($activity->fresh()->recurrence_rule_id)->toBeNull();
    $this->assertDatabaseCount('busy_slot_recurrence_rules', 0);
    $this->patch($url, $this->input)->assertSessionHasNoErrors();
    $this->delete(route('activities.destroy', $activity))->assertRedirect();
    $this->assertSoftDeleted('user_busy_slots', ['id' => $activity->id]);
    $this->assertDatabaseCount('busy_slot_recurrence_rules', 0);
    $this->get(route('activities.index'))->assertDontSee($this->input['name']);
});

test('recurring activity CRUD remains owner only', function () {
    $this->post(route('activities.store'), $this->input);
    $activity = $this->user->busySlots()->firstOrFail();
    $this->actingAs(User::factory()->create());
    $this->get(route('activities.edit', $activity))->assertNotFound();
    $this->patch(route('activities.update', $activity), $this->input)->assertNotFound();
    $this->delete(route('activities.destroy', $activity))->assertNotFound();
    $this->get(route('activities.index'))->assertDontSee($this->input['name']);
    $this->assertDatabaseCount('busy_slot_recurrence_rules', 1);
});

test('invalid rules do not create partial activities or orphan rules', function (array $changes, string $field) {
    $this->from(route('activities.index'))->post(route('activities.store'), array_replace($this->input, $changes))
        ->assertSessionHasErrors($field);
    $this->assertDatabaseCount('user_busy_slots', 0);
    $this->assertDatabaseCount('busy_slot_recurrence_rules', 0);
})->with([
    [['frequency' => 'daily'], 'frequency'],
    [['occurrence_count' => null], 'occurrence_count'],
    [['occurrence_count' => 1], 'occurrence_count'],
    [['occurrence_count' => 2.5], 'occurrence_count'],
    [['occurrence_count' => 2147483648], 'occurrence_count'],
    [['end_at' => '2026-01-31T08:00'], 'end_at'],
]);

test('calendar expands occurrences independently of both paginated lists', function () {
    $this->post(route('activities.store'), $this->input);
    for ($index = 0; $index < 21; $index++) {
        $this->user->busySlots()->create(['name' => 'One time '.$index, 'start_at' => '2026-02-28 12:00', 'end_at' => '2026-02-28 13:00']);
    }
    $this->get(route('activities.index', ['week' => '2026-02-28', 'activities_page' => 2]))->assertOk()
        ->assertViewHas('calendarActivities', function ($activities) {
            $recurring = $activities->where('name', $this->input['name'])->first();

            return $activities->count() === 22 && $recurring->start_at->format('Y-m-d') === '2026-02-28';
        })
        ->assertSee('กิจกรรมเกิดซ้ำ')->assertSee('กิจกรรมครั้งเดียว')->assertSee('กิจกรรมซ้ำ · ทุกเดือน')
        ->assertViewHas('activities', fn ($activities) => $activities->currentPage() === 2 && $activities->count() === 1)
        ->assertViewHas('recurringActivities', fn ($activities) => $activities->total() === 1);
    $this->get(route('activities.index', ['week' => '2026-04-30']))->assertOk()
        ->assertViewHas('calendarActivities', fn ($activities) => $activities->isEmpty());
    $this->get(route('activities.edit', $this->user->busySlots()->whereNotNull('recurrence_rule_id')->firstOrFail()))
        ->assertOk()->assertSee('การแก้ไขมีผลกับทั้งชุด');
});

test('recurrence migration rolls back and reapplies while preserving existing one time activities', function () {
    $activity = $this->user->busySlots()->create(['name' => 'Existing activity', 'start_at' => '2026-10-13 09:00', 'end_at' => '2026-10-13 10:00']);
    $migration = require database_path('migrations/2026_10_13_000001_add_busy_slot_recurrence_rules.php');
    $migration->down();
    expect(Schema::hasColumn('user_busy_slots', 'recurrence_rule_id'))->toBeFalse();
    expect(Schema::hasTable('busy_slot_recurrence_rules'))->toBeFalse();
    $migration->up();
    expect($activity->fresh()->name)->toBe('Existing activity');
    expect($activity->fresh()->recurrence_rule_id)->toBeNull();
});
