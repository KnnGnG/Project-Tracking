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

    public bool $showForm  = false;
    public ?int $editingId = null;

    // Member management panel
    public ?int $managingTeamId = null;
    public ?int $memberToAdd    = null;

    public bool $confirmingDelete = false;
    public ?int $deleteId = null;

    protected function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'projectId' => 'required|exists:projects,id',
            'leadId'    => 'required|exists:users,id',
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
        $this->showForm  = true;
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
            Team::findOrFail($this->editingId)->update($payload);
            session()->flash('success', 'Team updated successfully.');
        } else {
            Team::create($payload);
            session()->flash('success', 'Team created successfully.');
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Team::findOrFail($id)->delete();
        if ($this->managingTeamId === $id) {
            $this->managingTeamId = null;
        }
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

    public function openMembers(int $teamId): void
    {
        $this->managingTeamId = ($this->managingTeamId === $teamId) ? null : $teamId;
        $this->memberToAdd    = null;
    }

    public function addMember(): void
    {
        $this->validate(['memberToAdd' => 'required|exists:users,id']);

        $team = Team::findOrFail($this->managingTeamId);

        if (! $team->members()->where('user_id', $this->memberToAdd)->exists()) {
            $team->members()->attach($this->memberToAdd);
        }

        $this->memberToAdd = null;
    }

    public function removeMember(int $userId): void
    {
        Team::findOrFail($this->managingTeamId)->members()->detach($userId);
    }

    private function resetForm(): void
    {
        $this->name      = '';
        $this->projectId = null;
        $this->leadId    = null;
        $this->editingId = null;
        $this->resetValidation();
    }

    public function render()
    {
        $teams    = Team::with(['project', 'lead', 'members'])->latest()->get();
        $projects = Project::orderBy('name')->get();
        $leads    = User::where('role', 'team_lead')->orderBy('name')->get();

        $managingTeam   = $this->managingTeamId ? Team::with('members')->find($this->managingTeamId) : null;
        $availableUsers = User::where('role', 'member')->orderBy('name')->get();

        return view('livewire.admin.team-manager',
            compact('teams', 'projects', 'leads', 'managingTeam', 'availableUsers'));
    }
}
