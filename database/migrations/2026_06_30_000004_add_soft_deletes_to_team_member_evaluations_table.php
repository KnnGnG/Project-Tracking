<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_member_evaluations', function (Blueprint $table) {
            if (! Schema::hasColumn('team_member_evaluations', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('team_member_evaluations', function (Blueprint $table) {
            if (Schema::hasColumn('team_member_evaluations', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
