<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->index(['user_id', 'role'], 'team_members_user_role_index');
            $table->index(['team_id', 'role'], 'team_members_team_role_index');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['assigned_to', 'status', 'due_date'], 'tasks_assigned_status_due_index');
            $table->index(['team_id', 'status', 'due_date'], 'tasks_team_status_due_index');
            $table->index(['project_id', 'status', 'due_date'], 'tasks_project_status_due_index');
        });

        Schema::table('task_assignees', function (Blueprint $table) {
            $table->index(['user_id', 'task_id'], 'task_assignees_user_task_index');
        });

        Schema::table('in_app_notifications', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'notifications_user_created_index');
        });

        Schema::table('journal_logs', function (Blueprint $table) {
            $table->index(['team_id', 'log_date'], 'journal_logs_team_date_index');
            $table->index(['task_id', 'log_date'], 'journal_logs_task_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('journal_logs', function (Blueprint $table) {
            $table->dropIndex('journal_logs_task_date_index');
            $table->dropIndex('journal_logs_team_date_index');
        });

        Schema::table('in_app_notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_created_index');
        });

        Schema::table('task_assignees', function (Blueprint $table) {
            $table->dropIndex('task_assignees_user_task_index');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_project_status_due_index');
            $table->dropIndex('tasks_team_status_due_index');
            $table->dropIndex('tasks_assigned_status_due_index');
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->dropIndex('team_members_team_role_index');
            $table->dropIndex('team_members_user_role_index');
        });
    }
};
