<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use App\Events\GameJoined;
use App\Events\MovePlayed;
use App\Models\Game;
use App\Models\Move;
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
    ]);

    $freshGame = $game->fresh()->load(['whiteUser', 'blackUser', 'moves']);

    broadcast(new GameJoined($freshGame));

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
    ]);

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

    $game->update([
        'fen' => $data['fen_after'],
        'turn' => str_contains($data['fen_after'], ' w ') ? 'white' : 'black',
    ]);

    $freshGame = $game->fresh()->load(['whiteUser', 'blackUser', 'moves']);

    broadcast(new MovePlayed($freshGame, $move));

    return response()->json([
        'game' => $freshGame,
        'move' => $move,
    ], 201);
});
