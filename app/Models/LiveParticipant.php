<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveParticipant extends Model
{
    protected $fillable = ['live_session_id', 'device_token', 'name', 'photo_path', 'is_master'];

    protected function casts(): array
    {
        return [
            'is_master' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? asset('storage/'.$this->photo_path) : null;
    }
}
