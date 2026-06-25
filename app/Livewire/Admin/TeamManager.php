<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use App\Models\Team;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Teams')]
class TeamManager extends Component
{
    private const ALLOWED_TEAM_ROLES = ['team_lead', 'member'];

    // Team form fields
    public string $name      = '';
    public ?int   $projectId = null;
    public ?int   $leadId    = null;
    public array  $memberIds = [];
    public array $memberNotes = [];
    public array $projectTeamIds = [];
    public array $previousProjectTeamIds = [];
    public string $teamSearch = '';

    public bool $showForm  = false;
    public ?int $editingId = null;

    public bool $confirmingDelete = false;
    public ?int $deleteId = null;
    public ?int $detailsTeamId = null;

    protected function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'projectId' => 'required|exists:projects,id',
            'leadId'    => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', self::ALLOWED_TEAM_ROLES))],
            'memberIds' => 'nullable|array',
            'memberIds.*' => ['integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', self::ALLOWED_TEAM_ROLES))],
            'memberNotes' => 'nullable|array',
            'memberNotes.*' => 'nullable|string|max:500',
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
        $this->memberIds = $team->regularMembers()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        $this->memberNotes = $team->regularMembers()
            ->get()
            ->mapWithKeys(fn ($member) => [$member->id => $member->pivot?->notes ?? ''])
            ->all();
        $this->loadProjectTeamSelection();
        $this->showForm  = true;
    }

    public function updatedProjectId(): void
    {
        $this->loadProjectTeamSelection();
    }

    public function updatedProjectTeamIds(): void
    {
        $currentIds = collect($this->projectTeamIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $previousIds = collect($this->previousProjectTeamIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $addedIds = $currentIds->diff($previousIds)->values();

        $this->previousProjectTeamIds = $currentIds->all();

        if ($addedIds->count() === 1) {
            $this->autofillFromTeam((int) $addedIds->first());
        }
    }

    public function autofillFromTeam(int $teamId): void
    {
        $team = Team::with('regularMembers')->find($teamId);

        if (! $team) {
            return;
        }

        $this->name = $team->name;
        $this->leadId = $team->lead_id;
        $this->memberIds = $team->regularMembers
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->memberNotes = $team->regularMembers
            ->mapWithKeys(fn ($member) => [$member->id => $member->pivot?->notes ?? ''])
            ->all();
    }

    public function save(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data): void {
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

            $this->syncTeamPeople($team, (int) $data['leadId'], $data['memberIds'] ?? [], $data['memberNotes'] ?? []);
        });

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
        // Toggle inline details for the given team
        $this->detailsTeamId = $this->detailsTeamId === $teamId ? null : $teamId;
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
        $this->previousProjectTeamIds = $this->projectTeamIds;
    }

    public function clearProjectTeams(): void
    {
        $this->projectTeamIds = [];
        $this->previousProjectTeamIds = [];
    }

    private function resetForm(): void
    {
        $this->name           = '';
        $this->projectId      = null;
        $this->leadId         = null;
        $this->memberIds      = [];
        $this->memberNotes    = [];
        $this->projectTeamIds = [];
        $this->previousProjectTeamIds = [];
        $this->teamSearch     = '';
        $this->editingId      = null;
        $this->resetValidation();
    }

    private function loadProjectTeamSelection(): void
    {
        if (! $this->projectId) {
            $this->projectTeamIds = [];
            $this->previousProjectTeamIds = [];
            return;
        }

        $this->projectTeamIds = Team::where('project_id', $this->projectId)
            ->pluck('id')
            ->when($this->editingId, fn ($ids) => $ids->push($this->editingId))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $this->previousProjectTeamIds = $this->projectTeamIds;
    }

    private function projectTeamOptions()
    {
        // Intentionally global: selected teams are reassigned into the chosen project on save.
        return Team::with(['project', 'lead'])
            ->when($this->teamSearch, fn ($q) => $q->where('name', 'like', "%{$this->teamSearch}%"))
            ->orderBy('name')
            ->get();
    }

    private function syncTeamPeople(Team $team, int $leadId, array $memberIds, array $memberNotes): void
    {
        $candidateIds = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->push($leadId)
            ->unique()
            ->values();
        $allowedIds = User::whereIn('role', self::ALLOWED_TEAM_ROLES)
            ->whereIn('id', $candidateIds)
            ->pluck('id');

        if (! $allowedIds->contains($leadId)) {
            throw ValidationException::withMessages([
                'leadId' => 'Choose a valid team lead or member account.',
            ]);
        }

        $sync = [
            $leadId => ['role' => 'lead', 'notes' => null],
        ];

        collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $leadId)
            ->filter(fn ($id) => $allowedIds->contains($id))
            ->unique()
            ->each(function (int $memberId) use (&$sync, $memberNotes): void {
                $note = trim((string) ($memberNotes[$memberId] ?? ''));
                $sync[$memberId] = [
                    'role' => 'member',
                    'notes' => $note !== '' ? $note : null,
                ];
            });

        $team->members()->sync($sync);
    }

    public function render()
    {
        $teams    = Team::with(['project', 'lead', 'members'])->withCount('tasks')->latest()->get();
        $projects = Project::orderBy('name')->get();
        $people   = User::whereIn('role', ['team_lead', 'member'])->orderBy('name')->get();
        $leads    = $people;
        $members  = $people;
        $projectTeamOptions = $this->projectTeamOptions();
        $selectedProjectTeams = Team::with(['project', 'lead'])
            ->whereIn('id', array_map('intval', $this->projectTeamIds))
            ->orderBy('name')
            ->get();
        $detailsTeam = $this->detailsTeamId
            ? Team::with(['project', 'lead', 'members'])
                ->withCount('tasks')
                ->find($this->detailsTeamId)
            : null;

        if ($detailsTeam) {
            $detailsTeam->limitedTasks = Task::where('team_id', $this->detailsTeamId)
                ->with(['assignee', 'assignees'])
                ->orderBy('due_date')
                ->limit(8)
                ->get();
        }

        return view('livewire.admin.team-manager',
            compact('teams', 'projects', 'leads', 'members', 'projectTeamOptions', 'selectedProjectTeams', 'detailsTeam'));
    }
}
