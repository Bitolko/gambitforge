<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('white_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('black_user_id')->nullable()->after('white_user_id')->constrained('users')->nullOnDelete();
        });

        DB::table('games')->update([
            'white_user_id' => DB::raw('user_id'),
            'status' => 'waiting',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropForeign(['black_user_id']);
            $table->dropForeign(['white_user_id']);
            $table->dropColumn(['black_user_id', 'white_user_id']);
        });
    }
};
