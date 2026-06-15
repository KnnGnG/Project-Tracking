<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('team_members', 'role')) {
            Schema::table('team_members', function (Blueprint $table) {
                $table->string('role', 20)->default('member')->after('user_id');
            });
        }

        DB::table('team_members')->update(['role' => 'member']);

        DB::table('teams')
            ->whereNotNull('lead_id')
            ->orderBy('id')
            ->select(['id', 'lead_id'])
            ->chunk(100, function ($teams): void {
                $now = now();

                foreach ($teams as $team) {
                    $existing = DB::table('team_members')
                        ->where('team_id', $team->id)
                        ->where('user_id', $team->lead_id)
                        ->exists();

                    DB::table('team_members')->updateOrInsert(
                        [
                            'team_id' => $team->id,
                            'user_id' => $team->lead_id,
                        ],
                        [
                            'role' => 'lead',
                            ...($existing ? [] : ['created_at' => $now]),
                            'updated_at' => $now,
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('team_members', 'role')) {
            Schema::table('team_members', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }
};
