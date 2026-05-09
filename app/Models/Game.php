<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'white_user_id',
    'black_user_id',
    'title',
    'fen',
    'status',
    'result',
    'turn',
    'white_time_ms',
    'black_time_ms',
    'last_move_at',
    'time_control',
    'increment_ms',
])]
class Game extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_move_at' => 'datetime',
        ];
    }

    public function whiteUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'white_user_id');
    }

    public function blackUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'black_user_id');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(Move::class)->orderBy('move_number');
    }

    public function hasPlayer(User $user): bool
    {
        return $this->white_user_id === $user->id || $this->black_user_id === $user->id;
    }

    public function playerColor(User $user): ?string
    {
        return match ($user->id) {
            $this->white_user_id => 'white',
            $this->black_user_id => 'black',
            default => null,
        };
    }
}
