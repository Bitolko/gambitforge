<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Events\GameEnded;
use App\Events\GameJoined;
use App\Events\MovePlayed;
use App\Models\Game;
use App\Models\Move;
use App\Models\Tournament;
use App\Models\TournamentPairing;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;

Route::post('/register', function (Request $request) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'unique:users,email'],
        'password' => ['required', 'min:8'],
    ]);

    $user = User::create([
        'name' => $data['name'],
        'email' => $data['email'],
        'password' => Hash::make($data['password']),
    ]);

    return response()->json([
        'user' => $user,
        'token' => $user->createToken('api-token')->plainTextToken,
    ]);
});

Route::post('/login', function (Request $request) {
    $data = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    $user = User::where('email', $data['email'])->first();

    if (! $user || ! Hash::check($data['password'], $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    return response()->json([
        'user' => $user,
        'token' => $user->createToken('api-token')->plainTextToken,
    ]);
});

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->post('/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();

    return response()->json(['message' => 'Logged out']);
});

Route::middleware('auth:sanctum')->get('/games', function (Request $request) {
    return Game::query()
        ->with(['whiteUser', 'blackUser'])
        ->withCount('moves')
        ->where('white_user_id', $request->user()->id)
        ->orWhere('black_user_id', $request->user()->id)
        ->latest()
        ->get();
});

function hydratedGame(Game $game): Game
{
    return $game->fresh()->load(['whiteUser', 'blackUser', 'moves']);
}

function timeoutResult(string $timedOutColor): string
{
    return $timedOutColor === 'white' ? 'black_wins_timeout' : 'white_wins_timeout';
}

function resignationResult(string $resigningColor): string
{
    return $resigningColor === 'white' ? 'black_wins_resignation' : 'white_wins_resignation';
}

function broadcastSafely(object $event): void
{
    try {
        broadcast($event);
    } catch (Throwable $exception) {
        Log::warning('Broadcast failed; continuing request.', [
            'event' => $event::class,
            'message' => $exception->getMessage(),
        ]);
    }
}

function hydratedTournament(Tournament $tournament): Tournament
{
    return $tournament->fresh()->load([
        'owner',
        'players.user',
        'rounds.pairings.whiteUser',
        'rounds.pairings.blackUser',
        'rounds.pairings.game',
    ]);
}

function initialChessFen(): string
{
    return 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';
}

function timeControlBaseMs(string $timeControl): int
{
    $minutes = (int) str($timeControl)->before('+')->toString();

    return max(1, $minutes) * 60 * 1000;
}

function timeControlIncrementMs(string $timeControl): int
{
    return (int) str($timeControl)->after('+')->toString() * 1000;
}

function pairingScoreDelta(TournamentPairing $pairing, ?string $result): array
{
    return match ($result) {
        'white_win' => [$pairing->white_user_id => 1.0, $pairing->black_user_id => 0.0],
        'black_win' => [$pairing->white_user_id => 0.0, $pairing->black_user_id => 1.0],
        'draw' => [$pairing->white_user_id => 0.5, $pairing->black_user_id => 0.5],
        'bye' => [$pairing->white_user_id => 1.0],
        default => [],
    };
}

function applyPairingScore(TournamentPairing $pairing, ?string $result, int $direction = 1): void
{
    foreach (pairingScoreDelta($pairing, $result) as $userId => $score) {
        if (! $userId) {
            continue;
        }

        TournamentPlayer::query()
            ->where('tournament_id', $pairing->tournament_id)
            ->where('user_id', $userId)
            ->increment('score', $score * $direction);
    }
}

function createTournamentGame(Tournament $tournament, User|int $white, User|int $black, int $roundNumber, int $boardNumber): Game
{
    $whiteUserId = $white instanceof User ? $white->id : $white;
    $blackUserId = $black instanceof User ? $black->id : $black;
    $baseTimeMs = timeControlBaseMs($tournament->time_control);

    return Game::create([
        'white_user_id' => $whiteUserId,
        'black_user_id' => $blackUserId,
        'title' => "{$tournament->name} - Round {$roundNumber} Board {$boardNumber}",
        'fen' => initialChessFen(),
        'status' => 'active',
        'turn' => 'white',
        'white_time_ms' => $baseTimeMs,
        'black_time_ms' => $baseTimeMs,
        'last_move_at' => now(),
        'time_control' => $tournament->time_control,
        'increment_ms' => $tournament->increment_ms,
    ]);
}

function previousOpponentPairs(Tournament $tournament): array
{
    return $tournament->pairings()
        ->where('is_bye', false)
        ->get()
        ->mapWithKeys(function (TournamentPairing $pairing) {
            $ids = [$pairing->white_user_id, $pairing->black_user_id];
            sort($ids);

            return [implode('-', $ids) => true];
        })
        ->all();
}

function playerPairKey(TournamentPlayer $a, TournamentPlayer $b): string
{
    $ids = [$a->user_id, $b->user_id];
    sort($ids);

    return implode('-', $ids);
}

function selectByePlayer($players): ?TournamentPlayer
{
    if ($players->count() % 2 === 0) {
        return null;
    }

    return $players
        ->filter(fn (TournamentPlayer $player) => $player->byes === 0)
        ->sortBy([
            ['score', 'asc'],
            ['created_at', 'asc'],
        ])
        ->first()
        ?? $players->sortBy([['score', 'asc'], ['created_at', 'asc']])->first();
}

function pairPlayersByScore($players, array $previousPairs): array
{
    $pool = $players->sortByDesc('score')->values();
    $pairs = [];

    while ($pool->count() >= 2) {
        $player = $pool->shift();
        $opponentIndex = $pool->search(
            fn (TournamentPlayer $candidate) => ! isset($previousPairs[playerPairKey($player, $candidate)])
        );

        if ($opponentIndex === false) {
            $opponentIndex = 0;
        }

        $opponent = $pool->splice($opponentIndex, 1)->first();
        $pairs[] = [$player, $opponent];
    }

    return $pairs;
}

function currentRoundHasUnfinishedPairings(Tournament $tournament): bool
{
    $currentRound = $tournament->rounds()->with('pairings')->latest('round_number')->first();

    if (! $currentRound) {
        return false;
    }

    return $currentRound->pairings->contains(fn (TournamentPairing $pairing) => ! $pairing->result);
}

function markCompletedRounds(Tournament $tournament): void
{
    $tournament->rounds()->with('pairings')->get()->each(function (TournamentRound $round) {
        if ($round->pairings->isNotEmpty() && $round->pairings->every(fn (TournamentPairing $pairing) => $pairing->result)) {
            $round->update(['status' => 'completed']);
        }
    });
}

Route::middleware('auth:sanctum')->get('/tournaments', function () {
    return Tournament::query()
        ->with(['owner', 'players.user'])
        ->withCount('players')
        ->latest()
        ->get();
});

Route::middleware('auth:sanctum')->post('/tournaments', function (Request $request) {
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'time_control' => ['nullable', 'string', 'max:20'],
    ]);

    $timeControl = $data['time_control'] ?? '10+0';

    $tournament = Tournament::create([
        'owner_user_id' => $request->user()->id,
        'name' => $data['name'],
        'status' => 'registration',
        'time_control' => $timeControl,
        'increment_ms' => timeControlIncrementMs($timeControl),
        'rounds_count' => 1,
    ]);

    TournamentPlayer::create([
        'tournament_id' => $tournament->id,
        'user_id' => $request->user()->id,
    ]);

    return response()->json(hydratedTournament($tournament), 201);
});

Route::middleware('auth:sanctum')->get('/tournaments/{tournament}', function (Tournament $tournament) {
    return hydratedTournament($tournament);
});

Route::middleware('auth:sanctum')->post('/tournaments/{tournament}/join', function (Request $request, Tournament $tournament) {
    if ($tournament->status !== 'registration') {
        return response()->json(['message' => 'This tournament is no longer accepting players.'], 422);
    }

    TournamentPlayer::firstOrCreate([
        'tournament_id' => $tournament->id,
        'user_id' => $request->user()->id,
    ]);

    return response()->json(hydratedTournament($tournament));
});

Route::middleware('auth:sanctum')->post('/tournaments/{tournament}/start', function (Request $request, Tournament $tournament) {
    abort_unless($tournament->owner_user_id === $request->user()->id, 403);

    if ($tournament->status !== 'registration') {
        return response()->json(['message' => 'This tournament has already started.'], 422);
    }

    $players = $tournament->players()->with('user')->get()->shuffle()->values();

    if ($players->count() < 2) {
        return response()->json(['message' => 'At least two players are required to start.'], 422);
    }

    $round = TournamentRound::create([
        'tournament_id' => $tournament->id,
        'round_number' => 1,
        'status' => 'active',
    ]);

    $boardNumber = 1;

    if ($players->count() % 2 === 1) {
        $byePlayer = $players->pop();
        $byePlayer->increment('byes');
        $byePlayer->increment('score', 1);

        TournamentPairing::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $round->id,
            'white_user_id' => $byePlayer->user_id,
            'is_bye' => true,
            'result' => 'bye',
        ]);
    }

    $players->chunk(2)->each(function ($pair) use ($tournament, $round, &$boardNumber) {
        $pair = $pair->values();
        $white = $pair[0];
        $black = $pair[1];

        $game = createTournamentGame($tournament, $white->user_id, $black->user_id, 1, $boardNumber);

        TournamentPairing::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $round->id,
            'white_user_id' => $white->user_id,
            'black_user_id' => $black->user_id,
            'game_id' => $game->id,
        ]);

        $boardNumber++;
    });

    $tournament->update(['status' => 'active']);

    return response()->json(hydratedTournament($tournament));
});

Route::middleware('auth:sanctum')->post('/tournaments/{tournament}/rounds/next', function (Request $request, Tournament $tournament) {
    abort_unless($tournament->owner_user_id === $request->user()->id, 403);

    if ($tournament->status !== 'active') {
        return response()->json(['message' => 'Only active tournaments can generate another round.'], 422);
    }

    if (currentRoundHasUnfinishedPairings($tournament)) {
        return response()->json(['message' => 'Finish all current round pairings before generating the next round.'], 422);
    }

    markCompletedRounds($tournament);

    $roundNumber = ($tournament->rounds()->max('round_number') ?? 0) + 1;
    $round = TournamentRound::create([
        'tournament_id' => $tournament->id,
        'round_number' => $roundNumber,
        'status' => 'active',
    ]);

    $players = $tournament->players()->with('user')->get();
    $byePlayer = selectByePlayer($players);

    if ($byePlayer) {
        $players = $players->reject(fn (TournamentPlayer $player) => $player->id === $byePlayer->id)->values();
        $byePlayer->increment('byes');
        $byePlayer->increment('score', 1);

        TournamentPairing::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $round->id,
            'white_user_id' => $byePlayer->user_id,
            'is_bye' => true,
            'result' => 'bye',
        ]);
    }

    $previousPairs = previousOpponentPairs($tournament);
    $boardNumber = 1;

    foreach (pairPlayersByScore($players, $previousPairs) as [$white, $black]) {
        $game = createTournamentGame($tournament, $white->user_id, $black->user_id, $roundNumber, $boardNumber);

        TournamentPairing::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $round->id,
            'white_user_id' => $white->user_id,
            'black_user_id' => $black->user_id,
            'game_id' => $game->id,
        ]);

        $boardNumber++;
    }

    return response()->json(hydratedTournament($tournament));
});

Route::middleware('auth:sanctum')->post('/tournaments/{tournament}/finish', function (Request $request, Tournament $tournament) {
    abort_unless($tournament->owner_user_id === $request->user()->id, 403);

    if ($tournament->status !== 'active') {
        return response()->json(['message' => 'Only active tournaments can be finished.'], 422);
    }

    if (currentRoundHasUnfinishedPairings($tournament)) {
        return response()->json(['message' => 'Finish all current round pairings before finishing the tournament.'], 422);
    }

    markCompletedRounds($tournament);

    $tournament->update(['status' => 'finished']);

    return response()->json(hydratedTournament($tournament));
});

Route::middleware('auth:sanctum')->post('/tournament-pairings/{pairing}/result', function (Request $request, TournamentPairing $pairing) {
    $tournament = $pairing->tournament;

    abort_unless($tournament->owner_user_id === $request->user()->id, 403);

    $data = $request->validate([
        'result' => ['required', 'string', 'in:white_win,black_win,draw,bye'],
    ]);

    if ($pairing->is_bye && $data['result'] !== 'bye') {
        return response()->json(['message' => 'Bye pairings can only be marked as bye.'], 422);
    }

    if (! $pairing->is_bye && $data['result'] === 'bye') {
        return response()->json(['message' => 'Only bye pairings can receive a bye result.'], 422);
    }

    applyPairingScore($pairing, $pairing->result, -1);

    $pairing->update(['result' => $data['result']]);

    applyPairingScore($pairing->fresh(), $data['result']);
    markCompletedRounds($tournament);

    return response()->json(hydratedTournament($tournament));
});

Route::middleware('auth:sanctum')->post('/games', function (Request $request) {
    $data = $request->validate([
        'title' => ['nullable', 'string', 'max:255'],
        'fen' => ['required', 'string', 'max:255'],
    ]);

    $turn = str_contains($data['fen'], ' w ') ? 'white' : 'black';

    $game = Game::create([
        'white_user_id' => $request->user()->id,
        'title' => $data['title'] ?? 'Training game',
        'fen' => $data['fen'],
        'status' => 'waiting',
        'turn' => $turn,
        'white_time_ms' => 600000,
        'black_time_ms' => 600000,
        'time_control' => '10+0',
        'increment_ms' => 0,
    ]);

    return response()->json($game->load(['whiteUser', 'blackUser', 'moves']), 201);
});

Route::middleware('auth:sanctum')->get('/games/{game}', function (Request $request, Game $game) {
    abort_unless($game->hasPlayer($request->user()) || $game->status === 'waiting', 403);

    return $game->load(['whiteUser', 'blackUser', 'moves']);
});

Route::middleware('auth:sanctum')->post('/games/{game}/join', function (Request $request, Game $game) {
    if ($game->white_user_id === $request->user()->id) {
        return response()->json(['message' => 'You already created this game as white.'], 422);
    }

    if ($game->black_user_id) {
        return response()->json(['message' => 'This game already has both players.'], 422);
    }

    if ($game->status !== 'waiting') {
        return response()->json(['message' => 'This game is not accepting players.'], 422);
    }

    $game->update([
        'black_user_id' => $request->user()->id,
        'status' => 'active',
        'last_move_at' => now(),
    ]);

    $freshGame = hydratedGame($game);

    broadcastSafely(new GameJoined($freshGame));

    return response()->json($freshGame);
});

Route::middleware('auth:sanctum')->post('/games/{game}/resign', function (Request $request, Game $game) {
    abort_unless($game->hasPlayer($request->user()), 403);

    if ($game->status !== 'active') {
        return response()->json(['message' => 'Only active games can be resigned.'], 422);
    }

    $color = $game->playerColor($request->user());

    $game->update([
        'status' => 'finished',
        'result' => resignationResult($color),
        'last_move_at' => null,
    ]);

    $freshGame = hydratedGame($game);

    broadcastSafely(new GameEnded($freshGame));

    return response()->json($freshGame);
});

Route::middleware('auth:sanctum')->post('/games/{game}/moves', function (Request $request, Game $game) {
    abort_unless($game->hasPlayer($request->user()), 403);

    if ($game->status !== 'active') {
        return response()->json(['message' => 'This game is not active yet.'], 422);
    }

    if ($game->playerColor($request->user()) !== $game->turn) {
        return response()->json(['message' => "It is {$game->turn}'s turn."], 403);
    }

    $data = $request->validate([
        'from' => ['required', 'string', 'size:2'],
        'to' => ['required', 'string', 'size:2'],
        'promotion' => ['nullable', 'string', 'size:1'],
        'san' => ['required', 'string', 'max:32'],
        'fen_before' => ['required', 'string', 'max:255'],
        'fen_after' => ['required', 'string', 'max:255'],
        'game_over' => ['nullable', 'boolean'],
        'result' => ['nullable', 'string', 'in:white_wins_checkmate,black_wins_checkmate,draw_stalemate,draw_insufficient_material,draw_threefold_repetition,draw_fifty_move_rule,draw'],
    ]);

    $movingColor = $game->turn;
    $clockColumn = $movingColor === 'white' ? 'white_time_ms' : 'black_time_ms';
    $elapsedMs = $game->last_move_at
        ? max(0, now()->diffInMilliseconds($game->last_move_at))
        : 0;
    $remainingMs = max(0, $game->{$clockColumn} - $elapsedMs);

    if ($remainingMs <= 0) {
        $game->update([
            $clockColumn => 0,
            'status' => 'finished',
            'result' => timeoutResult($movingColor),
            'last_move_at' => null,
        ]);

        $freshGame = hydratedGame($game);

        broadcastSafely(new GameEnded($freshGame));

        return response()->json([
            'message' => ucfirst($movingColor).' flagged on time.',
            'game' => $freshGame,
        ], 422);
    }

    $move = Move::create([
        'game_id' => $game->id,
        'move_number' => $game->moves()->count() + 1,
        'from' => $data['from'],
        'to' => $data['to'],
        'promotion' => $data['promotion'] ?? null,
        'san' => $data['san'],
        'fen_before' => $data['fen_before'],
        'fen_after' => $data['fen_after'],
    ]);

    $nextTurn = str_contains($data['fen_after'], ' w ') ? 'white' : 'black';
    $moveEndsGame = ($data['game_over'] ?? false) && isset($data['result']);

    $game->update([
        'fen' => $data['fen_after'],
        'turn' => $nextTurn,
        'status' => $moveEndsGame ? 'finished' : 'active',
        'result' => $moveEndsGame ? $data['result'] : null,
        $clockColumn => $remainingMs + $game->increment_ms,
        'last_move_at' => $moveEndsGame ? null : now(),
    ]);

    $freshGame = hydratedGame($game);

    broadcastSafely(new MovePlayed($freshGame, $move));

    if ($moveEndsGame) {
        broadcastSafely(new GameEnded($freshGame));
    }

    return response()->json([
        'game' => $freshGame,
        'move' => $move,
    ], 201);
});
