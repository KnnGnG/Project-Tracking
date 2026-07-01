<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE in_app_notifications MODIFY type VARCHAR(64) NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE in_app_notifications MODIFY type ENUM('task_assigned', 'task_completed', 'task_comment', 'member_evaluation') NOT NULL");
    }
};
