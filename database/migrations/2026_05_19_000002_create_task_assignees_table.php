<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
            $table->index('user_id');
        });

        DB::table('tasks')
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->select(['id', 'assigned_to', 'created_at', 'updated_at'])
            ->chunkById(500, function ($tasks) {
                $rows = $tasks->map(fn ($task) => [
                    'task_id' => $task->id,
                    'user_id' => $task->assigned_to,
                    'created_at' => $task->created_at,
                    'updated_at' => $task->updated_at,
                ])->all();

                DB::table('task_assignees')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignees');
    }
};
