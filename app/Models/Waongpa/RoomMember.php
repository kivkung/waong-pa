<?php

namespace App\Models\Waongpa;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RoomMember extends Model
{
    // Services pass only validated, explicitly selected fields.
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['weight' => 'decimal:2', 'joined_at' => 'immutable_datetime', 'left_at' => 'immutable_datetime'];
    }

    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function participations()
    {
        return $this->hasMany(RoundParticipant::class);
    }
}
