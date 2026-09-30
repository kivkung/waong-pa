<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundMember extends Model
{
    protected $fillable = [
        'room_member_id',
        'role',
        'weight',
        'joined_at',
    ];

    /** @return BelongsTo<MeetingRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(MeetingRound::class, 'meeting_round_id');
    }

    /** @return BelongsTo<RoomMember, $this> */
    public function roomMember(): BelongsTo
    {
        return $this->belongsTo(RoomMember::class);
    }

    protected function casts(): array
    {
        return [
            'joined_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'weight' => 'decimal:2',
        ];
    }
}
