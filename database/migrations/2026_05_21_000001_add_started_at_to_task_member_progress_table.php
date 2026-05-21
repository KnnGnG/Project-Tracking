<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_member_progress', function (Blueprint $table) {
            if (! Schema::hasColumn('task_member_progress', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('progress');
            }
        });
    }

    public function down(): void
    {
        Schema::table('task_member_progress', function (Blueprint $table) {
            if (Schema::hasColumn('task_member_progress', 'started_at')) {
                $table->dropColumn('started_at');
            }
        });
    }
};
