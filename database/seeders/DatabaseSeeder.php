<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Tournament;
use App\Models\TournamentPairing;
use App\Models\TournamentPlayer;
use App\Models\TournamentRound;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = Hash::make('password123');
        $initialFen = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

        $users = collect([
            ['name' => 'GambitForge Owner', 'email' => 'owner@gambitforge.test', 'role' => 'admin'],
            ['name' => 'Demo Organizer', 'email' => 'organizer@gambitforge.test'],
            ['name' => 'Ana Novak', 'email' => 'ana@gambitforge.test'],
            ['name' => 'Boris Petrov', 'email' => 'boris@gambitforge.test'],
            ['name' => 'Mina Chen', 'email' => 'mina@gambitforge.test'],
            ['name' => 'Leo Smith', 'email' => 'leo@gambitforge.test'],
        ])->mapWithKeys(function (array $demoUser) use ($password) {
            $user = User::updateOrCreate(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'password' => $password,
                ]
            );

            $user->forceFill(['role' => $demoUser['role'] ?? 'user'])->save();

            return [$demoUser['email'] => $user];
        });

        Tournament::where('name', 'GambitForge Club Night Demo')->delete();
        Game::where('title', 'like', 'GambitForge Club Night Demo%')->delete();

        $tournament = Tournament::create([
            'owner_user_id' => $users['organizer@gambitforge.test']->id,
            'name' => 'GambitForge Club Night Demo',
            'status' => 'active',
            'time_control' => '10+0',
            'increment_ms' => 0,
            'rounds_count' => 2,
        ]);

        foreach ($users as $email => $user) {
            TournamentPlayer::create([
                'tournament_id' => $tournament->id,
                'user_id' => $user->id,
                'score' => match ($email) {
                    'organizer@gambitforge.test', 'ana@gambitforge.test', 'leo@gambitforge.test' => 1.0,
                    default => 0.0,
                },
                'byes' => $email === 'leo@gambitforge.test' ? 1 : 0,
            ]);
        }

        $roundOne = TournamentRound::create([
            'tournament_id' => $tournament->id,
            'round_number' => 1,
            'status' => 'completed',
        ]);

        TournamentPairing::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $roundOne->id,
            'white_user_id' => $users['organizer@gambitforge.test']->id,
            'black_user_id' => $users['boris@gambitforge.test']->id,
            'game_id' => Game::create([
                'white_user_id' => $users['organizer@gambitforge.test']->id,
                'black_user_id' => $users['boris@gambitforge.test']->id,
                'title' => 'GambitForge Club Night Demo - Round 1 Board 1',
                'fen' => $initialFen,
                'status' => 'finished',
                'result' => 'white_wins_checkmate',
                'turn' => 'white',
                'white_time_ms' => 522000,
                'black_time_ms' => 481000,
                'time_control' => '10+0',
                'increment_ms' => 0,
            ])->id,
            'result' => 'white_win',
        ]);

        TournamentPairing::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $roundOne->id,
            'white_user_id' => $users['ana@gambitforge.test']->id,
            'black_user_id' => $users['mina@gambitforge.test']->id,
            'game_id' => Game::create([
                'white_user_id' => $users['ana@gambitforge.test']->id,
                'black_user_id' => $users['mina@gambitforge.test']->id,
                'title' => 'GambitForge Club Night Demo - Round 1 Board 2',
                'fen' => $initialFen,
                'status' => 'finished',
                'result' => 'white_wins_checkmate',
                'turn' => 'white',
                'white_time_ms' => 558000,
                'black_time_ms' => 497000,
                'time_control' => '10+0',
                'increment_ms' => 0,
            ])->id,
            'result' => 'white_win',
        ]);

        TournamentPairing::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $roundOne->id,
            'white_user_id' => $users['leo@gambitforge.test']->id,
            'is_bye' => true,
            'result' => 'bye',
        ]);

        $roundTwo = TournamentRound::create([
            'tournament_id' => $tournament->id,
            'round_number' => 2,
            'status' => 'active',
        ]);

        TournamentPairing::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $roundTwo->id,
            'white_user_id' => $users['organizer@gambitforge.test']->id,
            'black_user_id' => $users['ana@gambitforge.test']->id,
            'game_id' => Game::create([
                'white_user_id' => $users['organizer@gambitforge.test']->id,
                'black_user_id' => $users['ana@gambitforge.test']->id,
                'title' => 'GambitForge Club Night Demo - Round 2 Board 1',
                'fen' => $initialFen,
                'status' => 'active',
                'turn' => 'white',
                'white_time_ms' => 600000,
                'black_time_ms' => 600000,
                'last_move_at' => now(),
                'time_control' => '10+0',
                'increment_ms' => 0,
            ])->id,
        ]);

        TournamentPairing::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $roundTwo->id,
            'white_user_id' => $users['leo@gambitforge.test']->id,
            'black_user_id' => $users['boris@gambitforge.test']->id,
            'game_id' => Game::create([
                'white_user_id' => $users['leo@gambitforge.test']->id,
                'black_user_id' => $users['boris@gambitforge.test']->id,
                'title' => 'GambitForge Club Night Demo - Round 2 Board 2',
                'fen' => $initialFen,
                'status' => 'active',
                'turn' => 'white',
                'white_time_ms' => 600000,
                'black_time_ms' => 600000,
                'last_move_at' => now(),
                'time_control' => '10+0',
                'increment_ms' => 0,
            ])->id,
        ]);

        TournamentPairing::create([
            'tournament_id' => $tournament->id,
            'tournament_round_id' => $roundTwo->id,
            'white_user_id' => $users['mina@gambitforge.test']->id,
            'is_bye' => true,
            'result' => 'bye',
        ]);
    }
}
