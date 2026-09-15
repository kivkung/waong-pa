<?php

namespace App\Livewire\Waongpa;

use App\Models\User;
use App\Models\Waongpa\Room;
use App\Services\Waongpa\Access;
use App\Services\Waongpa\Settings;
use App\Services\Waongpa\Workflow;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RoomBoard extends Component
{
    private function actor(): User
    {
        abort_unless(Auth::check(), 403);

        return Auth::user();
    }

    #[Locked]
    public int $roomId;

    #[Locked]
    public ?int $activeId = null;

    #[Locked]
    public ?int $version = null;

    public array $roomForm = [];

    public array $settings = [];

    public array $memberForms = [];

    public function mount(int $roomId): void
    {
        $this->roomId = $roomId;
        $room = $this->room();
        if (Auth::id() === $room->owner_id) {
            $this->roomForm = $room->only(['name', 'description', 'visibility']);
            $this->settings = Settings::defaults();
            $active = $room->rounds()->whereNotNull('active_marker')->first();
            $this->activeId = $active?->id;
            $this->version = $active?->version;
            $previous = $room->rounds()->latest('round_no')->first();
            if ($previous) {
                foreach (['daily_start_time', 'daily_end_time', 'duration_minutes', 'professor_rule', 'min_professors'] as $key) {
                    $this->settings[$key] = $previous->$key;
                }
            }
            foreach ($room->members()->where('status', 'active')->get() as $member) {
                $this->memberForms[$member->id] = ['role' => $member->role, 'weight' => $member->weight];
            }
        }
    }

    private function room(): Room
    {
        $room = Room::findOrFail($this->roomId);
        app(Access::class)->view(Auth::user(), $room);

        return $room;
    }

    public function saveRoom(Workflow $workflow): void
    {
        $workflow->updateRoom($this->actor(), $this->roomId, $this->roomForm);
        session()->flash('status', 'บันทึกห้องแล้ว');
    }

    public function saveMember(int $id, Workflow $workflow): void
    {
        $form = $this->memberForms[$id] ?? [];
        $workflow->setMember($this->actor(), $this->roomId, $id, $form['role'] ?? '', $form['weight'] ?? null);
        session()->flash('status', 'บันทึกบทบาทและน้ำหนักแล้ว');
    }

    public function removeMember(int $id, Workflow $workflow)
    {
        $workflow->removeMember($this->actor(), $this->roomId, $id);

        return $this->redirectRoute('waongpa.rooms.show', ['roomId' => $this->roomId]);
    }

    public function startAgain(Workflow $workflow)
    {
        $round = $workflow->startAgain($this->actor(), $this->roomId, $this->settings);

        return $this->redirectRoute('waongpa.rounds.show', ['roundId' => $round->id]);
    }

    public function resetRound(Workflow $workflow)
    {
        if (! $this->activeId || ! $this->version) {
            Settings::fail('ไม่มีรอบที่รีเซ็ตได้');
        }
        $workflow->reset($this->actor(), $this->activeId, $this->version, $this->settings);

        return $this->redirectRoute('waongpa.rounds.show', ['roundId' => $this->activeId]);
    }

    public function render()
    {
        $room = $this->room();
        $access = app(Access::class);
        $isMember = $access->isMember(Auth::user(), $room);

        return view('livewire.waongpa.room-board', [
            'room' => $room, 'isMember' => $isMember, 'isOwner' => Auth::id() === $room->owner_id,
            'members' => $isMember ? $room->members()->where('status', 'active')->with('user')->get() : collect(),
            'rounds' => $room->rounds()->with('result.slot')->latest('round_no')->get(),
        ])->layout('layouts.waongpa');
    }
}
