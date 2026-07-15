<?php

namespace App\Services;

use App\Models\InAppNotification;
use App\Models\Project;
use App\Models\ProjectStatusChangeRequest;
use App\Models\ProjectStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectStatusService
{
    public const LIFECYCLE_STATUSES = ['active', 'on_hold', 'completed'];

    public function change(
        Project $project,
        string $toStatus,
        User $actor,
        string $reason,
        string $source,
        ?ProjectStatusChangeRequest $approvedRequest = null,
    ): ProjectStatusHistory {
        if (! in_array($toStatus, self::LIFECYCLE_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Choose a valid project lifecycle status.']);
        }

        return DB::transaction(function () use ($project, $toStatus, $actor, $reason, $source, $approvedRequest) {
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);
            $fromStatus = $lockedProject->status;

            if ($fromStatus === $toStatus) {
                throw ValidationException::withMessages(['status' => 'The project already has this status.']);
            }

            $lockedProject->update(['status' => $toStatus]);

            $history = ProjectStatusHistory::create([
                'project_id' => $lockedProject->id,
                'changed_by' => $actor->id,
                'request_id' => $approvedRequest?->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'source' => $source,
                'reason' => $reason,
            ]);

            $supersededRequests = ProjectStatusChangeRequest::query()
                ->where('project_id', $lockedProject->id)
                ->where('status', 'pending')
                ->when($approvedRequest, fn ($query) => $query->whereKeyNot($approvedRequest->id))
                ->lockForUpdate()
                ->get();

            foreach ($supersededRequests as $statusRequest) {
                $statusRequest->update([
                    'status' => 'superseded',
                    'pending_project_id' => null,
                    'review_reason' => 'Closed because the project status changed before this request was reviewed.',
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                ]);
                $this->closeRequestNotifications($statusRequest);

                InAppNotification::create([
                    'user_id' => $statusRequest->requested_by,
                    'type' => 'project_status_request_reviewed',
                    'title' => 'Project status request closed',
                    'body' => $lockedProject->name.' changed to '.ucwords(str_replace('_', ' ', $toStatus)).', so your earlier request was closed.',
                    'url' => route('projects.index'),
                    'data' => ['project_id' => $lockedProject->id, 'request_id' => $statusRequest->id],
                ]);
            }

            $project->setRawAttributes($lockedProject->getAttributes(), true);

            return $history;
        });
    }

    public function closeRequestNotifications(ProjectStatusChangeRequest $statusRequest): void
    {
        InAppNotification::query()
            ->where('type', 'project_status_requested')
            ->where('data->request_id', $statusRequest->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
