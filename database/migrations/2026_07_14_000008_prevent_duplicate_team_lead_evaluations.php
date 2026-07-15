<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'team_lead_evaluations_unique_period';

    private const PERIOD_START_KEY = 'period_start_key';

    private const PERIOD_END_KEY = 'period_end_key';

    private const ACTIVE_KEY = 'active_unique_key';

    public function up(): void
    {
        DB::table('team_lead_evaluations')
            ->select('team_id', 'evaluator_id', 'lead_id', 'period_start', 'period_end')
            ->groupBy('team_id', 'evaluator_id', 'lead_id', 'period_start', 'period_end')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function ($duplicate): void {
                $ids = DB::table('team_lead_evaluations')
                    ->where('team_id', $duplicate->team_id)
                    ->where('evaluator_id', $duplicate->evaluator_id)
                    ->where('lead_id', $duplicate->lead_id)
                    ->when(
                        $duplicate->period_start === null,
                        fn ($query) => $query->whereNull('period_start'),
                        fn ($query) => $query->where('period_start', $duplicate->period_start),
                    )
                    ->when(
                        $duplicate->period_end === null,
                        fn ($query) => $query->whereNull('period_end'),
                        fn ($query) => $query->where('period_end', $duplicate->period_end),
                    )
                    ->orderByDesc('id')
                    ->pluck('id');

                DB::table('team_lead_evaluations')->whereIn('id', $ids->skip(1))->delete();
            });

        DB::statement("ALTER TABLE team_lead_evaluations ADD ".self::PERIOD_START_KEY." DATE GENERATED ALWAYS AS (COALESCE(period_start, DATE('1000-01-01'))) STORED");
        DB::statement("ALTER TABLE team_lead_evaluations ADD ".self::PERIOD_END_KEY." DATE GENERATED ALWAYS AS (COALESCE(period_end, DATE('1000-01-01'))) STORED");
        DB::statement('ALTER TABLE team_lead_evaluations ADD '.self::ACTIVE_KEY.' TINYINT GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END) STORED');

        Schema::table('team_lead_evaluations', function (Blueprint $table) {
            $table->unique(
                ['team_id', 'evaluator_id', 'lead_id', self::PERIOD_START_KEY, self::PERIOD_END_KEY, self::ACTIVE_KEY],
                self::INDEX,
            );
        });
    }

    public function down(): void
    {
        Schema::table('team_lead_evaluations', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
            $table->dropColumn([self::ACTIVE_KEY, self::PERIOD_END_KEY, self::PERIOD_START_KEY]);
        });
    }
};
