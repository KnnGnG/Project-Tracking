<?php

namespace App\Livewire\Admin;

use App\Models\InAppNotification;
use App\Models\Project;
use App\Models\ProjectStatusChangeRequest;
use App\Models\ProjectStatusHistory;
use App\Models\Team;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Projects')]
class ProjectManager extends Component
{
    use WithPagination;

    public string $name        = '';
    public string $description = '';
    public string $startDate   = '';
    public string $endDate     = '';
    public string $status      = 'active';
    public string $originalStatus = 'active';
    public string $statusChangeReason = '';
    public ?int   $clientId    = null;
    public array $projectTeamIds = [];
    public string $teamSearch = '';

    public bool $showForm    = false;
    public ?int $editingId   = null;
    public string $search    = '';
    public int $perPage = 10;

    public bool $confirmingDelete = false;
    public ?int $deleteId = null;

    public ?int $progressProjectId = null;
    public ?int $detailsProjectId = null;
    public $detailsProjectTasks = null;
    public array $requestReviewReasons = [];

    protected function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'startDate'   => 'required|date',
            'endDate'     => 'required|date|after_or_equal:startDate',
            'status'      => 'required|in:active,on_hold,completed',
            'statusChangeReason' => [
                Rule::requiredIf(fn () => $this->editingId && $this->status !== $this->originalStatus),
                'nullable',
                'string',
                'min:5',
                'max:500',
            ],
            'clientId'    => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'client'))],
            'projectTeamIds' => 'nullable|array',
            'projectTeamIds.*' => 'integer|exists:teams,id',
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->normalizedPerPage();
        $this->resetPage();
    }

    private function normalizedPerPage(): int
    {
        return in_array((int) $this->perPage, [10, 15, 25, 50], true)
            ? (int) $this->perPage
            : 10;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm  = true;
        $this->editingId = null;
    }

    public function openEdit(int $id): void
    {
        $project = Project::findOrFail($id);

        $this->editingId   = $id;
        $this->name        = $project->name;
        $this->description = $project->description ?? '';
        $this->startDate   = $project->start_date->toDateString();
        $this->endDate     = $project->end_date->toDateString();
        $this->status      = $project->status;
        $this->originalStatus = $project->status;
        $this->statusChangeReason = '';
        $this->clientId    = $project->client_id;
        $this->projectTeamIds = $project->teams->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->showForm    = true;
    }

    public function save(ProjectStatusService $statusService): void
    {
        $data = $this->validate();

        $payload = [
            'name'        => $data['name'],
            'description' => $data['description'],
            'start_date'  => $data['startDate'],
            'end_date'    => $data['endDate'],
            'status'      => $data['status'],
            'client_id'   => $data['clientId'],
        ];

        $selectedTeamIds = collect($this->projectTeamIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($payload, $selectedTeamIds, $statusService) {
            if ($this->editingId) {
                $project = Project::query()->lockForUpdate()->findOrFail($this->editingId);

                if ($project->status !== $this->originalStatus) {
                    throw ValidationException::withMessages([
                        'status' => 'The project status changed after you opened this form. Reopen it and try again.',
                    ]);
                }

                $requestedStatus = $payload['status'];
                unset($payload['status']);
                $project->update($payload);

                if ($project->status !== $requestedStatus) {
                    $statusService->change(
                        $project,
                        $requestedStatus,
                        auth()->user(),
                        $this->statusChangeReason,
                        'admin',
                    );
                }

                $previousTeamIds = $project->teams()->pluck('teams.id');
                $project->teams()->sync($selectedTeamIds->all());
                $removedTeamIds = $previousTeamIds->diff($selectedTeamIds);

                if ($removedTeamIds->isNotEmpty()) {
                    Team::whereIn('id', $removedTeamIds->all())
                        ->where('project_id', $project->id)
                        ->update(['project_id' => null]);
                }

                if ($selectedTeamIds->isNotEmpty()) {
                    Team::whereIn('id', $selectedTeamIds->all())
                        ->whereNull('project_id')
                        ->update(['project_id' => $project->id]);
                }

                session()->flash('success', 'Project updated successfully.');
            } else {
                $project = Project::create(array_merge($payload, ['created_by' => auth()->id()]));
                ProjectStatusHistory::create([
                    'project_id' => $project->id,
                    'changed_by' => auth()->id(),
                    'from_status' => null,
                    'to_status' => $project->status,
                    'source' => 'creation',
                    'reason' => 'Project created.',
                ]);

                if ($selectedTeamIds->isNotEmpty()) {
                    $project->teams()->sync($selectedTeamIds->all());

                    Team::whereIn('id', $selectedTeamIds->all())
                        ->whereNull('project_id')
                        ->update(['project_id' => $project->id]);
                }

                session()->flash('success', 'Project created successfully.');
            }
        });

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Project::findOrFail($id)->delete();
        session()->flash('success', 'Project deleted.');
    }

    public function confirmDelete(int $id): void
    {
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function deleteConfirmed(): void
    {
        if ($this->deleteId) {
            $this->delete($this->deleteId);
        }

        $this->cancelDelete();
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
        $this->deleteId = null;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function approveStatusRequest(int $requestId, ProjectStatusService $statusService): void
    {
        $this->reviewStatusRequest($requestId, true, $statusService);
    }

    public function rejectStatusRequest(int $requestId, ProjectStatusService $statusService): void
    {
        $this->validate([
            "requestReviewReasons.{$requestId}" => 'required|string|min:5|max:500',
        ], [
            "requestReviewReasons.{$requestId}.required" => 'Add a reason before declining this request.',
        ]);
        $this->reviewStatusRequest($requestId, false, $statusService);
    }

    private function reviewStatusRequest(int $requestId, bool $approve, ProjectStatusService $statusService): void
    {
        $reviewReason = trim($this->requestReviewReasons[$requestId] ?? '');
        $outcome = DB::transaction(function () use ($requestId, $approve, $reviewReason, $statusService) {
            $statusRequest = ProjectStatusChangeRequest::with('project')
                ->lockForUpdate()
                ->findOrFail($requestId);
            $this->authorizeProjectStatusManager();

            if ($statusRequest->status !== 'pending') {
                return 'already_reviewed';
            }

            $project = Project::query()->lockForUpdate()->findOrFail($statusRequest->project_id);
            $isStale = $project->status !== $statusRequest->requested_from_status
                || $project->status === $statusRequest->requested_status;

            if ($approve && $isStale) {
                $statusRequest->update([
                    'status' => 'superseded',
                    'pending_project_id' => null,
                    'review_reason' => 'The project status changed after this request was submitted.',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);
                $statusService->closeRequestNotifications($statusRequest);
                $this->notifyStatusRequester($statusRequest, false, 'The project changed before review, so this request was closed.');

                return 'stale';
            }

            if ($approve) {
                $statusService->change(
                    $project,
                    $statusRequest->requested_status,
                    auth()->user(),
                    $statusRequest->reason,
                    'request',
                    $statusRequest,
                );
            }

            $statusRequest->update([
                'status' => $approve ? 'approved' : 'rejected',
                'pending_project_id' => null,
                'review_reason' => $reviewReason ?: null,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
            $statusService->closeRequestNotifications($statusRequest);
            $this->notifyStatusRequester($statusRequest, $approve, $reviewReason);

            return $approve ? 'approved' : 'rejected';
        });

        unset($this->requestReviewReasons[$requestId]);

        match ($outcome) {
            'approved' => session()->flash('success', 'Project status request approved.'),
            'rejected' => session()->flash('success', 'Project status request declined.'),
            'stale' => session()->flash('error', 'The project changed after this request was submitted. The stale request was closed.'),
            default => null,
        };
    }

    private function notifyStatusRequester(ProjectStatusChangeRequest $statusRequest, bool $approved, string $reviewReason): void
    {
        $statusLabel = ucwords(str_replace('_', ' ', $statusRequest->requested_status));
        $body = $approved
            ? $statusRequest->project->name.' is now '.$statusLabel.'.'
            : 'Your '.$statusLabel.' request for '.$statusRequest->project->name.' was not applied.';

        if ($reviewReason !== '') {
            $body .= ' '.$reviewReason;
        }

        InAppNotification::create([
            'user_id' => $statusRequest->requested_by,
            'type' => 'project_status_request_reviewed',
            'title' => $approved ? 'Project status approved' : 'Project status request closed',
            'body' => $body,
            'url' => route('projects.index'),
            'data' => ['project_id' => $statusRequest->project_id, 'request_id' => $statusRequest->id],
        ]);
    }

    private function authorizeProjectStatusManager(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    public function toggleProgressDetails(int $projectId): void
    {
        $this->progressProjectId = $this->progressProjectId === $projectId ? null : $projectId;
    }

    public function showDetails(int $projectId): void
    {
        // Toggle the inline details dropdown for the given project
        $this->detailsProjectId = $this->detailsProjectId === $projectId ? null : $projectId;
    }

    private function resetForm(): void
    {
        $this->name        = '';
        $this->description = '';
        $this->startDate   = '';
        $this->endDate     = '';
        $this->status      = 'active';
        $this->originalStatus = 'active';
        $this->statusChangeReason = '';
        $this->clientId    = null;
        $this->projectTeamIds = [];
        $this->teamSearch = '';
        $this->editingId   = null;
        $this->resetValidation();
    }

    private function projectTeamOptions()
    {
        return Team::with(['project:id,name', 'projects:id,name', 'lead:id,name'])
            ->select('id', 'name', 'project_id', 'lead_id')
            ->when($this->teamSearch, fn ($q) => $q->where('name', 'like', "%{$this->teamSearch}%"))
            ->orderBy('name')
            ->limit(50)
            ->get();
    }

    public function render()
    {
        $this->perPage = $this->normalizedPerPage();

        $projects = Project::with([
            'client',
            'teams.lead',
            'teams.members',
        ])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->paginate($this->perPage);

        $clients = User::where('role', 'client')->orderBy('name')->get();
        $projectTeamOptions = $this->projectTeamOptions();
        $selectedProjectTeams = Team::with(['project:id,name', 'projects:id,name', 'lead:id,name'])
            ->select('id', 'name', 'project_id', 'lead_id')
            ->whereIn('id', array_map('intval', $this->projectTeamIds))
            ->orderBy('name')
            ->get();
        $pendingStatusRequestCount = ProjectStatusChangeRequest::where('status', 'pending')->count();
        $pendingStatusRequests = ProjectStatusChangeRequest::with(['project:id,name,created_by,status', 'requester:id,name'])
            ->where('status', 'pending')
            ->oldest()
            ->limit(10)
            ->get();
        if ($this->detailsProjectId) {
            $detailsProject = Project::with([
                'client',
                'teams.lead',
                'teams.members',
                'statusHistories' => fn ($query) => $query->with('actor:id,name')->limit(10),
            ])->find($this->detailsProjectId);

            // Not limit()-ed: the blade computes the panel's completion/status
            // stats from this same collection, so it needs every task for the
            // project. The task list below only ever renders the first 10.
            $this->detailsProjectTasks = Task::where('project_id', $this->detailsProjectId)
                ->with(['team', 'assignee', 'assignees'])
                ->orderBy('due_date')
                ->get();
        } else {
            $detailsProject = null;
            $this->detailsProjectTasks = null;
        }

        // expose the property as a local variable for compact() and the view
        $detailsProjectTasks = $this->detailsProjectTasks;

        return view('livewire.admin.project-manager', compact(
            'projects',
            'clients',
            'detailsProject',
            'detailsProjectTasks',
            'projectTeamOptions',
            'selectedProjectTeams',
            'pendingStatusRequests',
            'pendingStatusRequestCount',
        ));
    }
}


