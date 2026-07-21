<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_member_evaluations', function (Blueprint $table) {
            $table->json('criteria_labels')->nullable()->after('reliability_score');
        });

        Schema::table('team_lead_evaluations', function (Blueprint $table) {
            $table->json('criteria_labels')->nullable()->after('fairness_score');
        });
    }

    public function down(): void
    {
        Schema::table('team_member_evaluations', function (Blueprint $table) {
            $table->dropColumn('criteria_labels');
        });

        Schema::table('team_lead_evaluations', function (Blueprint $table) {
            $table->dropColumn('criteria_labels');
        });
    }
};
