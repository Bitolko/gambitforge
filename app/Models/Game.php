<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'white_user_id', 'black_user_id', 'title', 'fen', 'status', 'turn'])]
class Game extends Model
{
    use HasFactory;

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
