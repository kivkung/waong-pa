<?php

namespace App\Livewire\Waongpa;

use App\Models\User;
use App\Models\Waongpa\MeetingRound;
use App\Services\Waongpa\Access;
use App\Services\Waongpa\Workflow;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RoundBoard extends Component
{
    private function actor(): User
    {
        abort_unless(Auth::check(), 403);

        return Auth::user();
    }

    #[Locked]
    public int $roundId;

    #[Locked]
    public int $version;

    #[Locked]
    public ?int $editing = null;

    public array $event = ['title' => '', 'starts_at' => '', 'ends_at' => ''];

    public function mount(int $roundId): void
    {
        $this->roundId = $roundId;
        $this->version = $this->round()->version;
    }

    private function round(): MeetingRound
    {
        $round = MeetingRound::findOrFail($this->roundId);
        app(Access::class)->view(Auth::user(), $round->room);

        return $round;
    }

    public function syncSchedule(Workflow $w): void
    {
        $w->syncSchedule($this->actor(), $this->roundId, $this->version);
        session()->flash('status', 'ดึงตารางล่าสุดแล้ว กรุณายืนยันอีกครั้ง');
    }

    public function confirm(Workflow $w): void
    {
        $w->confirmSchedule($this->actor(), $this->roundId, $this->version);
    }

    public function saveBusy(Workflow $w): void
    {
        $w->saveBusy($this->actor(), $this->roundId, $this->version, $this->event, $this->editing);
        $this->clear();
    }

    public function editBusy(int $id): void
    {
        $round = $this->round();
        $person = app(Access::class)->participant($this->actor(), $round);
        $busy = $person->busyPeriods()->findOrFail($id);
        $this->editing = $id;
        $this->event = ['title' => $busy->title, 'starts_at' => $busy->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $busy->ends_at->format('Y-m-d\TH:i')];
    }

    public function clear(): void
    {
        $this->editing = null;
        $this->event = ['title' => '', 'starts_at' => '', 'ends_at' => ''];
    }

    public function deleteBusy(int $id, Workflow $w): void
    {
        $w->deleteBusy($this->actor(), $this->roundId, $this->version, $id);
    }

    public function vote(int $id, Workflow $w): void
    {
        $w->vote($this->actor(), $this->roundId, $this->version, $id);
        session()->flash('status', 'บันทึกโหวตแล้ว');
    }

    public function advance(Workflow $w): void
    {
        $w->advance($this->actor(), $this->roundId, $this->version);
    }

    public function syncDue(Workflow $w): void
    {
        $round = $this->round();
        app(Access::class)->owner($this->actor(), $round->room);
        $w->syncDue($round->id);
    }

    public function render()
    {
        $round = $this->round();
        $isMember = app(Access::class)->isMember(Auth::user(), $round->room);
        $person = $isMember ? $round->participants()->whereHas('member', fn ($q) => $q->where('user_id', Auth::id())->where('status', 'active'))->with(['busyPeriods', 'vote'])->first() : null;

        return view('livewire.waongpa.round-board', [
            'round' => $round, 'isMember' => $isMember, 'isOwner' => Auth::id() === $round->room->owner_id,
            'person' => $person, 'timeOptions' => $isMember ? $round->slots()->withCount('votes')->orderBy('rank_no')->get() : collect(),
            'result' => $round->result()->with('slot')->first(),
            'peopleCount' => $round->participants()->count(), 'confirmedCount' => $round->participants()->whereNotNull('schedule_confirmed_at')->count(),
        ])->layout('layouts.waongpa');
    }
}
