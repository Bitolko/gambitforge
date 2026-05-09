<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Game;

class GameJoined implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("game.{$this->game->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GameJoined';
    }

    public function broadcastWith(): array
    {
        return [
            'game' => $this->game->loadMissing(['whiteUser', 'blackUser', 'moves'])->toArray(),
        ];
    }
}
