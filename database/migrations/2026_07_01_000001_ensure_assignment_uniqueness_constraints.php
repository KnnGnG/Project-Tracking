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
        $this->mergeDuplicateTaskMemberProgress();

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
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $query = DB::table($table)->where('id', '!=', $duplicate->keep_id);

            foreach ($columns as $column) {
                $query->where($column, $duplicate->{$column});
            }

            $query->delete();
        }
    }

    private function mergeDuplicateTaskMemberProgress(): void
    {
        if (! Schema::hasTable('task_member_progress')) {
            return;
        }

        $duplicates = DB::table('task_member_progress')
            ->selectRaw('task_id, user_id, COUNT(*) as duplicate_count')
            ->groupBy('task_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $statusRank = [
            'pending' => 0,
            'in_progress' => 1,
            'review' => 2,
            'done' => 3,
        ];

        foreach ($duplicates as $duplicate) {
            $rows = DB::table('task_member_progress')
                ->where('task_id', $duplicate->task_id)
                ->where('user_id', $duplicate->user_id)
                ->orderBy('id')
                ->get();

            $survivor = $rows->first();
            $bestStatus = $rows
                ->pluck('status')
                ->filter()
                ->sortByDesc(fn ($status) => $statusRank[$status] ?? -1)
                ->first() ?: 'pending';

            $startedAt = $rows->pluck('started_at')->filter()->sort()->first();
            $completedAt = $rows->pluck('completed_at')->filter()->sortDesc()->first();
            $createdAt = $rows->pluck('created_at')->filter()->sort()->first();
            $updatedAt = $rows->pluck('updated_at')->filter()->sortDesc()->first();

            DB::table('task_member_progress')
                ->where('id', $survivor->id)
                ->update([
                    'status' => $bestStatus,
                    'progress' => $rows->max('progress') ?? 0,
                    'started_at' => $startedAt,
                    'completed_at' => $completedAt,
                    'created_at' => $createdAt ?? $survivor->created_at,
                    'updated_at' => $updatedAt ?? $survivor->updated_at,
                ]);

            DB::table('task_member_progress')
                ->whereIn('id', $rows->pluck('id')->skip(1)->all())
                ->delete();
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
