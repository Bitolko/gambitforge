<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Game;
use App\Models\Tournament;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('game.{game}', function ($user, Game $game) {
    return $game->hasPlayer($user);
});

Broadcast::channel('tournament.{tournament}', function ($user, Tournament $tournament) {
    return $tournament->owner_user_id === $user->id
        || $tournament->players()->where('user_id', $user->id)->exists();
});
