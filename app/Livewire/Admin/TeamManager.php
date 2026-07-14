<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\MatchesExistingTeams;
use App\Models\Team;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Teams')]
class TeamManager extends Component
{
    use MatchesExistingTeams, WithPagination;

    private const ALLOWED_TEAM_ROLES = ['team_lead', 'member'];

    // Team form fields
    public string $name      = '';
    public ?int   $leadId    = null;
    public array  $memberIds = [];
    public array $memberNotes = [];

    public bool $showForm  = false;
    public ?int $editingId = null;

    public bool $confirmingDelete = false;
    public ?int $deleteId = null;
    public ?int $detailsTeamId = null;

    public int $perPage = 10;

    protected function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'leadId'    => ['nullable', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', self::ALLOWED_TEAM_ROLES))],
            'memberIds' => 'nullable|array',
            'memberIds.*' => ['integer', Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', self::ALLOWED_TEAM_ROLES))],
            'memberNotes' => 'nullable|array',
            'memberNotes.*' => 'nullable|string|max:500',
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
        $team = Team::with('regularMembers')->findOrFail($id);

        $this->editingId = $id;
        $this->name      = $team->name;
        $this->leadId    = $team->lead_id;
        $this->memberIds = $team->regularMembers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->memberNotes = $team->regularMembers
            ->mapWithKeys(fn ($member) => [$member->id => $member->pivot?->notes ?? ''])
            ->all();
        $this->showForm  = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data): void {
            $payload = [
                'name'       => $data['name'],
                'lead_id'    => $data['leadId'] ?: null,
            ];

            if ($this->editingId) {
                $team = Team::findOrFail($this->editingId);
                $team->update($payload);
                session()->flash('success', 'Team updated successfully.');
            } else {
                $team = $this->findMatchingTeam($data['name'], $data['leadId'] ? (int) $data['leadId'] : null, $data['memberIds'] ?? []);

                if ($team) {
                    $team->update($payload);
                    session()->flash('success', 'Existing team updated successfully.');
                } else {
                    $team = Team::create($payload);
                    session()->flash('success', 'Team created successfully.');
                }
            }

            $this->syncTeamPeople($team, $data['leadId'] ? (int) $data['leadId'] : null, $data['memberIds'] ?? [], $data['memberNotes'] ?? []);
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

    private function resetForm(): void
    {
        $this->name           = '';
        $this->leadId         = null;
        $this->memberIds      = [];
        $this->memberNotes    = [];
        $this->editingId      = null;
        $this->resetValidation();
    }

    private function syncTeamPeople(Team $team, ?int $leadId, array $memberIds, array $memberNotes): void
    {
        $candidateIds = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->when($leadId, fn ($ids) => $ids->push($leadId))
            ->filter()
            ->unique()
            ->values();

        $allowedIds = $candidateIds->isEmpty()
            ? collect()
            : User::whereIn('role', self::ALLOWED_TEAM_ROLES)
                ->whereIn('id', $candidateIds)
                ->pluck('id');

        if ($leadId && ! $allowedIds->contains($leadId)) {
            throw ValidationException::withMessages([
                'leadId' => 'Choose a valid team lead or member account.',
            ]);
        }

        $sync = [];

        if ($leadId) {
            $sync[$leadId] = ['role' => 'lead', 'notes' => null];
        }

        collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $leadId && $id === $leadId)
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
        $teams    = Team::with(['project', 'projects', 'lead', 'members'])->withCount('tasks')->latest()->paginate($this->perPage);
        $people   = User::with(['teams.project', 'teams.projects'])
            ->whereIn('role', ['team_lead', 'member'])
            ->orderBy('name')
            ->get();
        $leads    = $people;
        $members  = $people;
        $detailsTeam = $this->detailsTeamId
            ? Team::with(['project', 'projects', 'lead', 'members'])
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
            compact('teams', 'leads', 'members', 'detailsTeam'));
    }
}
