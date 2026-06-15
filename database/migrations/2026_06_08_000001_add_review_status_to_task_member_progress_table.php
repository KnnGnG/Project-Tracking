<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_member_progress')) {
            return;
        }

        DB::statement("ALTER TABLE task_member_progress MODIFY status ENUM('pending', 'in_progress', 'review', 'done') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('task_member_progress')) {
            return;
        }

        DB::statement("UPDATE task_member_progress SET status = 'in_progress' WHERE status = 'review'");
        DB::statement("ALTER TABLE task_member_progress MODIFY status ENUM('pending', 'in_progress', 'done') NOT NULL DEFAULT 'pending'");
    }
};