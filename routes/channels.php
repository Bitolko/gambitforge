<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Game;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('game.{game}', function ($user, Game $game) {
    return $game->hasPlayer($user);
});
