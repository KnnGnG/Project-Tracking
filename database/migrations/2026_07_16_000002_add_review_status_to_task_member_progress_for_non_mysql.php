<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_06_08_000001_add_review_status_to_task_member_progress_table intentionally
 * skipped non-MySQL connections, leaving the `review` status missing from the
 * column's CHECK constraint everywhere else (SQLite, used by the test suite,
 * in particular) — so a status of 'review' could never actually be persisted
 * there even though the application relies on it throughout.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('task_member_progress') || DB::getDriverName() === 'mysql') {
            return;
        }

        Schema::table('task_member_progress', function (Blueprint $table) {
            $table->enum('status', ['pending', 'in_progress', 'review', 'done'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('task_member_progress') || DB::getDriverName() === 'mysql') {
            return;
        }

        DB::table('task_member_progress')->where('status', 'review')->update(['status' => 'in_progress']);

        Schema::table('task_member_progress', function (Blueprint $table) {
            $table->enum('status', ['pending', 'in_progress', 'done'])
                ->default('pending')
                ->change();
        });
    }
};
