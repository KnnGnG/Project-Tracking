<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_status_change_requests', function (Blueprint $table) {
            $table->string('requested_from_status', 30)->nullable()->after('requested_status');
            $table->text('review_reason')->nullable()->after('status');
            $table->unsignedBigInteger('pending_project_id')->nullable()->after('project_id');
        });

        $pendingGroups = DB::table('project_status_change_requests')
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get()
            ->groupBy('project_id');

        foreach ($pendingGroups as $projectId => $requests) {
            $currentStatus = DB::table('projects')->where('id', $projectId)->value('status');
            $latest = $requests->first();

            DB::table('project_status_change_requests')
                ->where('id', $latest->id)
                ->update([
                    'requested_from_status' => $currentStatus,
                    'pending_project_id' => $projectId,
                ]);

            $supersededIds = $requests->skip(1)->pluck('id');

            if ($supersededIds->isNotEmpty()) {
                DB::table('project_status_change_requests')
                    ->whereIn('id', $supersededIds)
                    ->update([
                        'status' => 'superseded',
                        'requested_from_status' => $currentStatus,
                        'review_reason' => 'Superseded while enforcing one pending request per project.',
                        'reviewed_at' => now(),
                    ]);
            }
        }

        Schema::table('project_status_change_requests', function (Blueprint $table) {
            $table->unique('pending_project_id', 'project_status_requests_one_pending_project');
        });
    }

    public function down(): void
    {
        Schema::table('project_status_change_requests', function (Blueprint $table) {
            $table->dropUnique('project_status_requests_one_pending_project');
            $table->dropColumn(['requested_from_status', 'review_reason', 'pending_project_id']);
        });
    }
};
