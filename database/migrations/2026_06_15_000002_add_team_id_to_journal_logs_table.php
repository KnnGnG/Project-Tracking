<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('journal_logs', 'team_id')) {
            Schema::table('journal_logs', function (Blueprint $table) {
                $table->foreignId('team_id')
                    ->nullable()
                    ->after('task_id')
                    ->constrained('teams')
                    ->nullOnDelete();

                $table->index(['team_id', 'log_date']);
            });
        }

        DB::table('journal_logs')
            ->join('tasks', 'journal_logs.task_id', '=', 'tasks.id')
            ->whereNull('journal_logs.team_id')
            ->select('journal_logs.id', 'tasks.team_id')
            ->orderBy('journal_logs.id')
            ->chunkById(500, function ($logs): void {
                foreach ($logs as $log) {
                    DB::table('journal_logs')
                        ->whereKey($log->id)
                        ->update(['team_id' => $log->team_id]);
                }
            }, 'journal_logs.id', 'id');
    }

    public function down(): void
    {
        if (Schema::hasColumn('journal_logs', 'team_id')) {
            Schema::table('journal_logs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('team_id');
            });
        }
    }
};

