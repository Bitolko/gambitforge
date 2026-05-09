<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('result')->nullable()->after('status');
            $table->unsignedInteger('white_time_ms')->default(600000)->after('turn');
            $table->unsignedInteger('black_time_ms')->default(600000)->after('white_time_ms');
            $table->timestamp('last_move_at')->nullable()->after('black_time_ms');
            $table->string('time_control')->default('10+0')->after('last_move_at');
            $table->unsignedInteger('increment_ms')->default(0)->after('time_control');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn([
                'result',
                'white_time_ms',
                'black_time_ms',
                'last_move_at',
                'time_control',
                'increment_ms',
            ]);
        });
    }
};
