<?php

namespace App\Livewire\Waongpa;

use App\Models\Waongpa\Room;
use App\Services\Waongpa\Workflow;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Catalogue extends Component
{
    use WithPagination;

    public string $search = '';

    public string $code = '';

    public bool $mine = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMine(): void
    {
        $this->resetPage();
    }

    public function join(Workflow $workflow)
    {
        abort_unless(Auth::check(), 403);
        $this->validate(['code' => 'required|string|max:12']);
        $room = $workflow->joinRoom(Auth::user(), $this->code);

        return $this->redirectRoute('waongpa.rooms.show', ['roomId' => $room->id]);
    }

    public function render()
    {
        $query = Room::whereNull('archived_at');
        $query->where(function ($q) {
            $q->where('visibility', 'public');
            if (Auth::check()) {
                $q->orWhereHas('members', fn ($m) => $m->where('user_id', Auth::id())->where('status', 'active'));
            }
        });
        if ($this->mine) {
            $query->whereHas('members', fn ($m) => $m->where('user_id', Auth::id() ?? 0)->where('status', 'active'));
        }
        if ($this->search !== '') {
            $query->where('name', 'like', '%'.mb_substr($this->search, 0, 100).'%');
        }

        return view('livewire.waongpa.catalogue', ['rooms' => $query->latest()->paginate(9)])->layout('layouts.waongpa');
    }
}
