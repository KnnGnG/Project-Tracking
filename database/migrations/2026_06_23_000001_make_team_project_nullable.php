<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->change();

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        if (DB::table('teams')->whereNull('project_id')->exists()) {
            $fallbackProjectId = DB::table('projects')->orderBy('id')->value('id');

            if (! $fallbackProjectId) {
                throw new RuntimeException('Cannot roll back nullable team projects while teams with no project exist.');
            }

            DB::table('teams')
                ->whereNull('project_id')
                ->update(['project_id' => $fallbackProjectId]);
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable(false)
                ->change();

            $table->foreign('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
        });
    }
};
