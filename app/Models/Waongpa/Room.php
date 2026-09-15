<?php

namespace App\Models\Waongpa;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    // Services pass only validated, explicitly selected fields.
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['archived_at' => 'immutable_datetime'];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members()
    {
        return $this->hasMany(RoomMember::class);
    }

    public function rounds()
    {
        return $this->hasMany(MeetingRound::class);
    }
}
