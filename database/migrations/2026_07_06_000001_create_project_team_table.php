<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('project_team')) {
            Schema::create('project_team', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('team_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['project_id', 'team_id']);
            });
        }

        $now = now();

        DB::table('teams')
            ->whereNotNull('project_id')
            ->select(['id', 'project_id'])
            ->orderBy('id')
            ->chunkById(200, function ($teams) use ($now): void {
                foreach ($teams as $team) {
                    DB::table('project_team')->updateOrInsert(
                        [
                            'project_id' => $team->project_id,
                            'team_id' => $team->id,
                        ],
                        [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_team');
    }
};
