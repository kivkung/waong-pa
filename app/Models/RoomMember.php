<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomMember extends Model
{
    // Only these fields may be passed to firstOrCreate().
    protected $fillable = [
        'room_id', 'user_id', 'role', 'weight', 'status', 'joined_at', 'left_at',
    ];

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<RoundMember, $this> */
    public function roundMembers(): HasMany
    {
        return $this->hasMany(RoundMember::class);
    }
}
