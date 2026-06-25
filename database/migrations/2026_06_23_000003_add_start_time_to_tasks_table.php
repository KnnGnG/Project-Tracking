<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'start_time')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->time('start_time')->nullable()->after('start_date');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tasks', 'start_time')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropColumn('start_time');
            });
        }
    }
};
