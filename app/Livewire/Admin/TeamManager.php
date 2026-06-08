<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Teams')]
class TeamManager extends Component
{
    // Team form fields
    public string $name      = '';
    public ?int   $projectId = null;
    public ?int   $leadId    = null;
    public array $projectTeamIds = [];
    public string $teamSearch = '';

    public bool $showForm  = false;
    public ?int $editingId = null;

    public bool $confirmingDelete = false;
    public ?int $deleteId = null;
    public bool $showingDetails = false;
    public ?int $detailsTeamId = null;

    protected function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'projectId' => 'required|exists:projects,id',
            'leadId'    => 'required|exists:users,id',
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
        $team = Team::findOrFail($id);

        $this->editingId = $id;
        $this->name      = $team->name;
        $this->projectId = $team->project_id;
        $this->leadId    = $team->lead_id;
        $this->loadProjectTeamSelection();
        $this->showForm  = true;
    }

    public function updatedProjectId(): void
    {
        $this->loadProjectTeamSelection();
    }

    public function save(): void
    {
        $data = $this->validate();

        $payload = [
            'name'       => $data['name'],
            'project_id' => $data['projectId'],
            'lead_id'    => $data['leadId'],
        ];

        if ($this->editingId) {
            $team = Team::findOrFail($this->editingId);
            $team->update($payload);
            session()->flash('success', 'Team updated successfully.');
        } else {
            $team = Team::create($payload);
            session()->flash('success', 'Team created successfully.');
        }

        $projectTeamIds = collect($data['projectTeamIds'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->push($team->id)
            ->unique()
            ->values();

        Team::whereIn('id', $projectTeamIds->all())->update(['project_id' => $team->project_id]);

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Team::findOrFail($id)->delete();
        session()->flash('success', 'Team deleted.');
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

    public function showDetails(int $teamId): void
    {
        $this->detailsTeamId = $teamId;
        $this->showingDetails = true;
    }

    public function closeDetails(): void
    {
        $this->showingDetails = false;
        $this->detailsTeamId = null;
    }

    public function selectAllProjectTeams(): void
    {
        if (! $this->projectId) {
            return;
        }

        $this->projectTeamIds = $this->projectTeamOptions()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function clearProjectTeams(): void
    {
        $this->projectTeamIds = [];
    }

    private function resetForm(): void
    {
        $this->name           = '';
        $this->projectId      = null;
        $this->leadId         = null;
        $this->projectTeamIds = [];
        $this->teamSearch     = '';
        $this->editingId      = null;
        $this->resetValidation();
    }

    private function loadProjectTeamSelection(): void
    {
        if (! $this->projectId) {
            $this->projectTeamIds = [];
            return;
        }

        $this->projectTeamIds = Team::where('project_id', $this->projectId)
            ->pluck('id')
            ->when($this->editingId, fn ($ids) => $ids->push($this->editingId))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function projectTeamOptions()
    {
        // Intentionally global: selected teams are reassigned into the chosen project on save.
        return Team::with(['project', 'lead'])
            ->when($this->teamSearch, fn ($q) => $q->where('name', 'like', "%{$this->teamSearch}%"))
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        $teams    = Team::with(['project', 'lead', 'members'])->withCount('tasks')->latest()->get();
        $projects = Project::orderBy('name')->get();
        $leads    = User::where('role', 'team_lead')->orderBy('name')->get();
        $projectTeamOptions = $this->projectTeamOptions();
        $selectedProjectTeams = Team::with(['project', 'lead'])
            ->whereIn('id', array_map('intval', $this->projectTeamIds))
            ->orderBy('name')
            ->get();
        $detailsTeam = $this->detailsTeamId
            ? Team::with(['project', 'lead', 'members', 'tasks.assignee', 'tasks.assignees'])
                ->withCount('tasks')
                ->find($this->detailsTeamId)
            : null;

        return view('livewire.admin.team-manager',
            compact('teams', 'projects', 'leads', 'projectTeamOptions', 'selectedProjectTeams', 'detailsTeam'));
    }
}
