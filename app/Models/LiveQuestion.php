<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class LiveQuestion extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    public const CHOICE_TYPES = [
        'ab' => ['A', 'B'],
        'abcd' => ['A', 'B', 'C', 'D'],
        'simnao' => ['SIM', 'NAO'],
    ];

    protected $fillable = ['live_session_id', 'admin_text', 'choice_type', 'status', 'order'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(LiveVote::class);
    }

    /**
     * The available choices for this question based on its choice_type.
     *
     * @return array<int, string>
     */
    public function choices(): array
    {
        return self::CHOICE_TYPES[$this->choice_type] ?? [];
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Vote counts keyed by choice, including zeros for choices with no votes.
     *
     * @return array<string, int>
     */
    public function tally(): array
    {
        $counts = $this->votes()
            ->select('choice', DB::raw('count(*) as total'))
            ->groupBy('choice')
            ->pluck('total', 'choice')
            ->all();

        $tally = [];
        foreach ($this->choices() as $choice) {
            $tally[$choice] = (int) ($counts[$choice] ?? 0);
        }

        return $tally;
    }
}
