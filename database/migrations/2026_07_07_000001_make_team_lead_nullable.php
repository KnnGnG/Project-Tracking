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
            $table->dropForeign(['lead_id']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('lead_id')
                ->nullable()
                ->change();

            $table->foreign('lead_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
        });

        if (DB::table('teams')->whereNull('lead_id')->exists()) {
            $fallbackLeadId = DB::table('users')
                ->whereIn('role', ['team_lead', 'member'])
                ->orderBy('id')
                ->value('id');

            if (! $fallbackLeadId) {
                throw new RuntimeException('Cannot roll back nullable team leads while teams with no lead exist.');
            }

            DB::table('teams')
                ->whereNull('lead_id')
                ->update(['lead_id' => $fallbackLeadId]);
        }

        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('lead_id')
                ->nullable(false)
                ->change();

            $table->foreign('lead_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};