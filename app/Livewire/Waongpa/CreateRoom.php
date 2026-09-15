<?php

namespace App\Livewire\Waongpa;

use App\Services\Waongpa\Settings;
use App\Services\Waongpa\Workflow;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateRoom extends Component
{
    public array $roomForm = ['name' => '', 'description' => '', 'visibility' => 'private', 'owner_role' => 'student'];

    public array $settings = [];

    public function mount(): void
    {
        $this->settings = Settings::defaults();
    }

    public function save(Workflow $workflow)
    {
        $room = $workflow->createRoom(Auth::user(), $this->roomForm, $this->settings);

        return $this->redirectRoute('waongpa.rooms.show', ['roomId' => $room->id]);
    }

    public function render()
    {
        return view('livewire.waongpa.create-room')->layout('layouts.waongpa');
    }
}
