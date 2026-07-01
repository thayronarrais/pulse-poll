<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveVote extends Model
{
    protected $fillable = ['live_question_id', 'device_token', 'voter_name', 'choice'];

    public function question(): BelongsTo
    {
        return $this->belongsTo(LiveQuestion::class, 'live_question_id');
    }
}
