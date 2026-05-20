<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_member_progress')) {
            Schema::create('task_member_progress', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->enum('status', ['pending', 'in_progress', 'done'])->default('pending');
                $table->unsignedTinyInteger('progress')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['task_id', 'user_id']);
                $table->index(['user_id', 'status']);
            });
        }

        if (Schema::hasTable('task_assignees')) {
            DB::table('task_assignees')
                ->orderBy('id')
                ->select(['id', 'task_id', 'user_id', 'created_at', 'updated_at'])
                ->chunkById(500, function ($assignees) {
                    $rows = $assignees->map(fn ($assignee) => [
                        'task_id' => $assignee->task_id,
                        'user_id' => $assignee->user_id,
                        'status' => 'pending',
                        'progress' => 0,
                        'created_at' => $assignee->created_at,
                        'updated_at' => $assignee->updated_at,
                    ])->all();

                    DB::table('task_member_progress')->insertOrIgnore($rows);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_member_progress');
    }
};
