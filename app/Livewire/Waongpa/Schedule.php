<?php

namespace App\Livewire\Waongpa;

use App\Models\Waongpa\PersonalEvent;
use App\Services\Waongpa\Settings;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Schedule extends Component
{
    #[Locked]
    public ?int $editing = null;

    public array $event = ['title' => '', 'starts_at' => '', 'ends_at' => ''];

    public function save(Settings $settings): void
    {
        $data = $settings->event($this->event);
        if ($this->editing) {
            PersonalEvent::where('user_id', Auth::id())->findOrFail($this->editing)->update($data);
        } else {
            PersonalEvent::create([...$data, 'user_id' => Auth::id()]);
        }
        $this->clear();
        session()->flash('status', 'บันทึกตารางส่วนตัวแล้ว ตารางในรอบเดิมยังไม่เปลี่ยน');
    }

    public function edit(int $id): void
    {
        $e = PersonalEvent::where('user_id', Auth::id())->findOrFail($id);
        $this->editing = $e->id;
        $this->event = ['title' => $e->title, 'starts_at' => $e->starts_at->format('Y-m-d\TH:i'), 'ends_at' => $e->ends_at->format('Y-m-d\TH:i')];
    }

    public function clear(): void
    {
        $this->editing = null;
        $this->event = ['title' => '', 'starts_at' => '', 'ends_at' => ''];
    }

    public function delete(int $id): void
    {
        PersonalEvent::where('user_id', Auth::id())->findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.waongpa.schedule', ['events' => PersonalEvent::where('user_id', Auth::id())->orderBy('starts_at')->get()])->layout('layouts.waongpa');
    }
}
