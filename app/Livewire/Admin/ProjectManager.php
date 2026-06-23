<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use App\Models\Team;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Projects')]
class ProjectManager extends Component
{
    public string $name        = '';
    public string $description = '';
    public string $startDate   = '';
    public string $endDate     = '';
    public string $status      = 'active';
    public ?int   $clientId    = null;
    public array $projectTeamIds = [];
    public string $teamSearch = '';

    public bool $showForm    = false;
    public ?int $editingId   = null;
    public string $search    = '';

    public bool $confirmingDelete = false;
    public ?int $deleteId = null;

    public ?int $progressProjectId = null;
    public ?int $detailsProjectId = null;
    public $detailsProjectTasks = null;

    protected function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'startDate'   => 'required|date',
            'endDate'     => 'required|date|after_or_equal:startDate',
            'status'      => 'required|in:active,on_hold,completed',
            'clientId'    => 'nullable|exists:users,id',
            'projectTeamIds' => 'nullable|array',
            'projectTeamIds.*' => 'integer|exists:teams,id',
        ];
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
        $this->clientId    = $project->client_id;
        $this->projectTeamIds = $project->teams->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->showForm    = true;
    }

    public function save(): void
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

        DB::transaction(function () use ($payload, $selectedTeamIds) {
            if ($this->editingId) {
                $project = Project::findOrFail($this->editingId);
                $project->update($payload);

                Team::where('project_id', $project->id)
                    ->when($selectedTeamIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $selectedTeamIds))
                    ->update(['project_id' => null]);

                if ($selectedTeamIds->isNotEmpty()) {
                    Team::whereIn('id', $selectedTeamIds->all())
                        ->update(['project_id' => $project->id]);
                }

                session()->flash('success', 'Project updated successfully.');
            } else {
                $project = Project::create(array_merge($payload, ['created_by' => auth()->id()]));

                if ($selectedTeamIds->isNotEmpty()) {
                    Team::whereIn('id', $selectedTeamIds->all())
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
        $this->clientId    = null;
        $this->projectTeamIds = [];
        $this->teamSearch = '';
        $this->editingId   = null;
        $this->resetValidation();
    }

    private function projectTeamOptions()
    {
        return Team::with(['project:id,name', 'lead:id,name'])
            ->select('id', 'name', 'project_id', 'lead_id')
            ->when($this->teamSearch, fn ($q) => $q->where('name', 'like', "%{$this->teamSearch}%"))
            ->orderBy('name')
            ->limit(50)
            ->get();
    }

    public function render()
    {
        $projects = Project::with([
            'client',
            'teams.lead',
            'teams.members',
        ])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->get();

        $clients = User::where('role', 'client')->orderBy('name')->get();
        $projectTeamOptions = $this->projectTeamOptions();
        $selectedProjectTeams = Team::with(['project:id,name', 'lead:id,name'])
            ->select('id', 'name', 'project_id', 'lead_id')
            ->whereIn('id', array_map('intval', $this->projectTeamIds))
            ->orderBy('name')
            ->get();
        if ($this->detailsProjectId) {
            $detailsProject = Project::with([
                'client',
                'teams.lead',
                'teams.members',
            ])->find($this->detailsProjectId);

            $this->detailsProjectTasks = Task::where('project_id', $this->detailsProjectId)
                ->with(['team', 'assignee', 'assignees'])
                ->orderBy('due_date')
                ->limit(10)
                ->get();
        } else {
            $detailsProject = null;
            $this->detailsProjectTasks = null;
        }

        // expose the property as a local variable for compact() and the view
        $detailsProjectTasks = $this->detailsProjectTasks;

        return view('livewire.admin.project-manager', compact('projects', 'clients', 'detailsProject', 'detailsProjectTasks', 'projectTeamOptions', 'selectedProjectTeams'));
    }
}
