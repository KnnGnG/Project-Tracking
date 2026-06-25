<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Assign Teams')]
class AssignTeams extends Component
{
    private const ALLOWED_TEAM_ROLES = ['team_lead', 'member'];

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $projectId = null;

    public ?int $leadId = null;

    public array $memberIds = [];

    public array $memberNotes = [];

    public bool $confirmingDelete = false;

    public ?int $deleteId = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'projectId' => ['nullable', 'integer', 'exists:projects,id'],
            'leadId' => ['required', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', self::ALLOWED_TEAM_ROLES))],
            'memberIds' => ['array'],
            'memberIds.*' => ['integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', self::ALLOWED_TEAM_ROLES))],
            'memberNotes' => ['array'],
            'memberNotes.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $team = Team::with('regularMembers')->findOrFail($id);

        $this->editingId = $team->id;
        $this->name = $team->name;
        $this->projectId = $team->project_id;
        $this->leadId = $team->lead_id;
        $this->memberIds = $team->regularMembers
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->memberNotes = $team->regularMembers
            ->mapWithKeys(fn ($member) => [$member->id => $member->pivot?->notes ?? ''])
            ->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();
        $leadId = (int) $data['leadId'];

        DB::transaction(function () use ($data, $leadId): void {
            $payload = [
                'name' => $data['name'],
                'project_id' => $data['projectId'] ?: null,
                'lead_id' => $leadId,
            ];

            $team = $this->editingId
                ? Team::findOrFail($this->editingId)
                : Team::create($payload);

            if ($this->editingId) {
                $team->update($payload);
            }

            $this->syncPeople($team, $leadId, $data['memberIds'] ?? [], $data['memberNotes'] ?? []);
        });

        session()->flash('success', $this->editingId ? 'Team updated.' : 'Premade team created.');

        $this->resetForm();
        $this->showForm = false;
    }

    public function confirmDelete(int $id): void
    {
        Team::findOrFail($id);
        $this->deleteId = $id;
        $this->confirmingDelete = true;
    }

    public function deleteConfirmed(): void
    {
        if ($this->deleteId) {
            Team::findOrFail($this->deleteId)->delete();
        }

        $this->confirmingDelete = false;
        $this->deleteId = null;
        session()->flash('success', 'Team deleted.');
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

    private function resetForm(): void
    {
        $this->name = '';
        $this->projectId = null;
        $this->leadId = null;
        $this->memberIds = [];
        $this->memberNotes = [];
        $this->editingId = null;
        $this->resetValidation();
    }

    private function syncPeople(Team $team, int $leadId, array $memberIds, array $memberNotes): void
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
        $teams = Team::with(['project', 'lead', 'regularMembers'])
            ->withCount('tasks')
            ->orderByRaw('project_id is not null')
            ->orderBy('name')
            ->get();

        $projects = Project::orderBy('name')->get();
        $people = User::whereIn('role', ['team_lead', 'member'])
            ->orderBy('name')
            ->get();

        return view('livewire.admin.assign-teams', [
            'teams' => $teams,
            'projects' => $projects,
            'leads' => $people,
            'members' => $people,
        ]);
    }
}
