<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE team_member_evaluations ADD period_start_key DATE GENERATED ALWAYS AS (COALESCE(period_start, DATE('1000-01-01'))) STORED");
        DB::statement("ALTER TABLE team_member_evaluations ADD period_end_key DATE GENERATED ALWAYS AS (COALESCE(period_end, DATE('1000-01-01'))) STORED");
        DB::statement("ALTER TABLE team_member_evaluations ADD active_unique_key TINYINT GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED");
        DB::statement("CREATE UNIQUE INDEX evaluations_unique_active_period ON team_member_evaluations (team_id, evaluator_id, member_id, period_start_key, period_end_key, active_unique_key)");
    }

    public function down(): void
    {
        DB::statement("DROP INDEX evaluations_unique_active_period ON team_member_evaluations");
        DB::statement("ALTER TABLE team_member_evaluations DROP COLUMN active_unique_key");
        DB::statement("ALTER TABLE team_member_evaluations DROP COLUMN period_end_key");
        DB::statement("ALTER TABLE team_member_evaluations DROP COLUMN period_start_key");
    }
};
