<?php

namespace App\Events;

use App\Models\Tournament;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TournamentUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Tournament $tournament,
        public string $action,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tournament.{$this->tournament->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TournamentUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'tournament' => $this->tournament->loadMissing([
                'owner',
                'players.user',
                'rounds.pairings.whiteUser',
                'rounds.pairings.blackUser',
                'rounds.pairings.game',
            ])->toArray(),
        ];
    }
}
