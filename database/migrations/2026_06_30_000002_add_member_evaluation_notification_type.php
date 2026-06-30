<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE in_app_notifications MODIFY type ENUM('task_assigned', 'task_completed', 'task_comment', 'member_evaluation') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE in_app_notifications MODIFY type ENUM('task_assigned', 'task_completed', 'task_comment') NOT NULL");
    }
};
