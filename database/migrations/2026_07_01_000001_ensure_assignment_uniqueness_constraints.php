<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dedupeByLowestId('task_assignees', ['task_id', 'user_id']);
        $this->dedupeByLowestId('task_member_progress', ['task_id', 'user_id']);

        if (! $this->indexExists('task_assignees', 'task_assignees_task_id_user_id_unique')
            && ! $this->indexExists('task_assignees', 'task_assignees_unique_task_user_guard')) {
            Schema::table('task_assignees', function (Blueprint $table) {
                $table->unique(['task_id', 'user_id'], 'task_assignees_unique_task_user_guard');
            });
        }

        if (! $this->indexExists('task_member_progress', 'task_member_progress_task_id_user_id_unique')
            && ! $this->indexExists('task_member_progress', 'task_member_progress_unique_task_user_guard')) {
            Schema::table('task_member_progress', function (Blueprint $table) {
                $table->unique(['task_id', 'user_id'], 'task_member_progress_unique_task_user_guard');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('task_member_progress', 'task_member_progress_unique_task_user_guard')) {
            Schema::table('task_member_progress', function (Blueprint $table) {
                $table->dropUnique('task_member_progress_unique_task_user_guard');
            });
        }

        if ($this->indexExists('task_assignees', 'task_assignees_unique_task_user_guard')) {
            Schema::table('task_assignees', function (Blueprint $table) {
                $table->dropUnique('task_assignees_unique_task_user_guard');
            });
        }
    }

    private function dedupeByLowestId(string $table, array $columns): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $duplicates = DB::table($table)
            ->selectRaw('MIN(id) as keep_id, '.implode(', ', $columns).', COUNT(*) as duplicate_count')
            ->groupBy($columns)
            ->having('duplicate_count', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $query = DB::table($table)->where('id', '!=', $duplicate->keep_id);

            foreach ($columns as $column) {
                $query->where($column, $duplicate->{$column});
            }

            $query->delete();
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => ($row->name ?? null) === $index);
        }

        if ($driver === 'mysql') {
            return collect(DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]))->isNotEmpty();
        }

        return Schema::hasIndex($table, $index);
    }
};
