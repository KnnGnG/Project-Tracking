<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add support for review stage (CFD/analytics): store status as VARCHAR so all DB drivers accept it.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status', 32)->default('pending')->change();
        });
    }

    /**
     * @note Any tasks in review will become in_progress before reverting enum (SQLite/mysql).
     */
    public function down(): void
    {
        DB::table('tasks')
            ->where('status', 'review')
            ->update(['status' => 'in_progress']);

        DB::table('tasks')
            ->whereNotIn('status', ['pending', 'in_progress', 'done'])
            ->update(['status' => 'pending']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending')->change();
        });
    }
};
