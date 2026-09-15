<?php

namespace Tests\Feature;

use App\Livewire\Waongpa\RoundBoard;
use App\Livewire\Waongpa\Schedule;
use App\Models\User;
use App\Models\Waongpa\MeetingRound;
use App\Models\Waongpa\PersonalEvent;
use App\Models\Waongpa\Room;
use App\Models\Waongpa\RoundBusyPeriod;
use App\Models\Waongpa\TimeSlot;
use App\Models\Waongpa\Vote;
use App\Services\Waongpa\Settings;
use App\Services\Waongpa\SlotRanker;
use App\Services\Waongpa\Workflow;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WaongpaTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $student;

    private Room $room;

    private MeetingRound $round;

    private Workflow $flow;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'Asia/Bangkok', 'waongpa.timezone' => 'Asia/Bangkok']);
        date_default_timezone_set('Asia/Bangkok');
        $this->travelTo(CarbonImmutable::parse('2026-09-15 09:00:00', 'Asia/Bangkok'));
        $this->withoutVite();
        $this->owner = User::factory()->create();
        $this->student = User::factory()->create();
        $this->flow = app(Workflow::class);
        $this->room = $this->flow->createRoom($this->owner, ['name' => 'Public demo', 'description' => 'Meeting', 'visibility' => 'public', 'owner_role' => 'professor'], Settings::defaults());
        $this->flow->joinRoom($this->student, $this->room->join_code);
        $this->round = $this->room->rounds()->first();
    }

    private function confirmed(): void
    {
        foreach ([$this->owner, $this->student] as $u) {
            $this->flow->confirmSchedule($u, $this->round->id, 1);
        }
    }

    public function test_late_manual_skip_preserves_ordered_phase_boundaries(): void
    {
        $this->confirmed();
        $this->travelTo($this->round->voting_starts_at->addMinute());
        $this->flow->advance($this->owner, $this->round->id, 1);
        $this->round->refresh();
        $this->assertTrue($this->round->review_starts_at->lt($this->round->voting_starts_at));
    }

    private function voting(): void
    {
        $this->confirmed();
        $this->flow->advance($this->owner, $this->round->id, 1);
        $this->flow->advance($this->owner, $this->round->id, 1);
    }

    public function test_guest_sees_public_room_but_not_member_names_invite_or_private_room(): void
    {
        $this->get('/waongpa')->assertOk()->assertSee('Public demo');
        $this->get('/waongpa/rooms/'.$this->room->id)->assertOk()->assertDontSee($this->room->join_code)->assertDontSee($this->student->name);
        $this->room->update(['visibility' => 'private']);
        $this->get('/waongpa/rooms/'.$this->room->id)->assertNotFound();
        $this->get('/waongpa/rounds/'.$this->round->id)->assertNotFound();
        $this->get('/waongpa')->assertDontSee('Public demo');
    }

    public function test_guest_must_login_to_edit_schedule_and_create_room(): void
    {
        $this->get('/waongpa/schedule')->assertRedirect(route('login'));
        $this->get('/waongpa/rooms/create')->assertRedirect(route('login'));
    }

    public function test_authenticated_pages_render_with_official_auth_starter(): void
    {
        $this->actingAs($this->owner);
        foreach (['/waongpa', '/waongpa/schedule', '/waongpa/rooms/create', '/waongpa/rooms/'.$this->room->id, '/waongpa/rounds/'.$this->round->id] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_schedule_crud_and_invalid_end_are_validated(): void
    {
        Livewire::actingAs($this->student)->test(Schedule::class)
            ->set('event', ['title' => 'Class', 'starts_at' => '2026-09-17T09:00', 'ends_at' => '2026-09-17T08:00'])->call('save')->assertHasErrors('ends_at')
            ->set('event.ends_at', '2026-09-17T10:00')->call('save')->assertHasNoErrors();
        $event = PersonalEvent::where('user_id', $this->student->id)->firstOrFail();
        Livewire::actingAs($this->student)->test(Schedule::class)->call('edit', $event->id)->set('event.title', 'Edited')->call('save')->assertHasNoErrors();
        $this->assertSame('Edited', $event->fresh()->title);
        Livewire::actingAs($this->student)->test(Schedule::class)->call('delete', $event->id)->assertHasNoErrors();
        $this->assertDatabaseMissing('personal_events', ['id' => $event->id]);
    }

    public function test_personal_events_cannot_be_edited_by_another_user(): void
    {
        $event = PersonalEvent::create(['user_id' => $this->owner->id, 'title' => 'Secret', 'starts_at' => '2026-09-17 09:00', 'ends_at' => '2026-09-17 10:00']);
        try {
            Livewire::actingAs($this->student)->test(Schedule::class)->call('delete', $event->id);
            $this->fail('Expected scoped lookup rejection');
        } catch (ModelNotFoundException $e) {
            $this->assertSame(PersonalEvent::class, $e->getModel());
        }
        $this->assertDatabaseHas('personal_events', ['id' => $event->id]);
    }

    public function test_snapshot_survives_source_edit_and_delete(): void
    {
        $event = PersonalEvent::create(['user_id' => $this->student->id, 'title' => 'Original', 'starts_at' => '2026-09-17 09:00', 'ends_at' => '2026-09-17 10:00']);
        $this->flow->syncSchedule($this->student, $this->round->id, 1);
        $event->update(['title' => 'Changed']);
        $event->delete();
        $busy = RoundBusyPeriod::firstOrFail();
        $this->assertSame('Original', $busy->title);
        $this->assertNull($busy->source_event_id);
    }

    public function test_empty_unconfirmed_schedule_does_not_count_as_free(): void
    {
        $this->expectException(ValidationException::class);
        $this->flow->advance($this->owner, $this->round->id, 1);
    }

    public function test_half_open_boundaries_and_contiguous_duration(): void
    {
        $this->round->update(['duration_minutes' => 90, 'daily_end_time' => '12:00', 'search_end_date' => '2026-09-17']);
        $this->flow->saveBusy($this->owner, $this->round->id, 1, ['title' => 'Busy', 'starts_at' => '2026-09-17 09:30', 'ends_at' => '2026-09-17 10:00']);
        $this->confirmed();
        $slots = app(SlotRanker::class)->rank($this->round->fresh());
        $this->assertSame('2026-09-17 08:00:00', $slots[0]['starts_at']);
        $this->assertSame('2026-09-17 09:30:00', $slots[0]['ends_at']);
        $this->assertSame('2026-09-17 10:00:00', $slots[1]['starts_at']);
    }

    public function test_weighted_ranking_prefers_more_available_weight_over_earlier_time(): void
    {
        $member = $this->room->members()->where('user_id', $this->student->id)->first();
        $this->flow->setMember($this->owner, $this->room->id, $member->id, 'student', 5);
        $this->flow->saveBusy($this->student, $this->round->id, 1, ['title' => 'Busy', 'starts_at' => '2026-09-17 08:00', 'ends_at' => '2026-09-17 09:00']);
        $this->confirmed();
        $slots = app(SlotRanker::class)->rank($this->round);
        $this->assertSame('2026-09-17 09:00:00', $slots[0]['starts_at']);
        $this->assertEquals(6, $slots[0]['weighted_score']);
    }

    public function test_no_professor_available_leaves_join_and_no_slots(): void
    {
        $this->flow->saveBusy($this->owner, $this->round->id, 1, ['title' => 'Away', 'starts_at' => '2026-09-16 00:00', 'ends_at' => '2026-09-20 00:00']);
        $this->confirmed();
        try {
            $this->flow->advance($this->owner, $this->round->id, 1);
            $this->fail('Expected no eligible slots');
        } catch (ValidationException) {
        }
        $this->assertSame('join', $this->round->fresh()->phase);
        $this->assertSame(0, $this->round->slots()->count());
    }

    public function test_revote_updates_instead_of_adding_vote_and_tie_uses_earliest(): void
    {
        $this->voting();
        $slots = $this->round->slots()->orderBy('rank_no')->get();
        $this->flow->vote($this->student, $this->round->id, 1, $slots[2]->id);
        $this->flow->vote($this->student, $this->round->id, 1, $slots[1]->id);
        $this->assertSame(1, Vote::count());
        $this->flow->vote($this->owner, $this->round->id, 1, $slots[0]->id);
        $this->flow->advance($this->owner, $this->round->id, 1);
        $result = $this->round->result()->first();
        $this->assertSame($slots[0]->id, $result->time_slot_id);
        $this->assertSame('tie_earliest', $result->decision_reason);
    }

    public function test_no_vote_selects_first_rank_and_rerun_preserves_old_result(): void
    {
        $this->voting();
        $slot = $this->round->slots()->where('rank_no', 1)->first();
        $this->flow->advance($this->owner, $this->round->id, 1);
        $this->assertSame($slot->id, $this->round->result()->first()->time_slot_id);
        $this->assertSame('no_votes_top_rank', $this->round->result()->first()->decision_reason);
        $next = $this->flow->startAgain($this->owner, $this->room->id, Settings::defaults());
        $this->assertSame(2, $next->round_no);
        $this->assertSame(2, $next->participants()->count());
        $this->assertNotNull($this->round->result()->first());
        $this->assertSame(0, $next->slots()->count());
    }

    public function test_reset_clears_votes_and_rejects_stale_version(): void
    {
        $this->voting();
        $this->flow->vote($this->student, $this->round->id, 1, $this->round->slots()->first()->id);
        $this->flow->reset($this->owner, $this->round->id, 1, Settings::defaults());
        $this->assertSame(0, Vote::count());
        $this->assertSame(0, $this->round->slots()->count());
        $this->assertSame(0, $this->round->participants()->whereNotNull('schedule_confirmed_at')->count());
        $this->expectException(ValidationException::class);
        $this->flow->confirmSchedule($this->student, $this->round->id, 1);
    }

    public function test_student_cannot_skip_owner_phase(): void
    {
        Livewire::actingAs($this->student)->test(RoundBoard::class, ['roundId' => $this->round->id])->call('advance')->assertForbidden();
        $this->assertSame('join', $this->round->fresh()->phase);
    }

    public function test_cross_round_vote_is_rejected(): void
    {
        $this->voting();
        $other = $this->flow->createRoom($this->owner, ['name' => 'Other', 'visibility' => 'public', 'owner_role' => 'professor'], Settings::defaults())->rounds()->first();
        $this->flow->confirmSchedule($this->owner, $other->id, 1);
        $this->flow->advance($this->owner, $other->id, 1);
        try {
            Livewire::actingAs($this->student)->test(RoundBoard::class, ['roundId' => $this->round->id])->call('vote', $other->slots()->first()->id);
            $this->fail('Expected scoped lookup rejection');
        } catch (ModelNotFoundException $e) {
            $this->assertSame(TimeSlot::class, $e->getModel());
        }
        $this->assertSame(0, Vote::count());
    }

    public function test_vote_after_deadline_rejected_even_if_scheduler_has_not_run(): void
    {
        $this->voting();
        $this->travelTo($this->round->fresh()->final_starts_at);
        $this->expectException(ValidationException::class);
        $this->flow->vote($this->student, $this->round->id, 1, $this->round->slots()->first()->id);
    }

    public function test_scheduler_catches_up_and_is_idempotent(): void
    {
        $this->confirmed();
        $this->travelTo($this->round->final_starts_at->addMinute());
        $this->artisan('waongpa:tick')->assertSuccessful();
        $this->artisan('waongpa:tick')->assertSuccessful();
        $this->assertSame('final', $this->round->fresh()->phase);
        $this->assertSame(1, $this->round->result()->count());
    }

    public function test_settings_reject_invalid_phase_order(): void
    {
        $input = Settings::defaults();
        $input['voting_starts_at'] = $input['join_starts_at'];
        $this->expectException(ValidationException::class);
        app(Settings::class)->validate($input);
    }

    public function test_second_active_round_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->flow->startAgain($this->owner, $this->room->id, Settings::defaults());
    }

    public function test_calendar_export_uses_utc_and_requires_membership(): void
    {
        $this->voting();
        $this->flow->advance($this->owner,$this->round->id,1);
        $this->actingAs($this->student)->get('/waongpa/rounds/'.$this->round->id.'/calendar')->assertOk()->assertSee('DTSTART:20260917T010000Z',false)->assertSee('BEGIN:VCALENDAR',false);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get('/waongpa/rounds/'.$this->round->id.'/calendar')->assertForbidden();
    }
}
