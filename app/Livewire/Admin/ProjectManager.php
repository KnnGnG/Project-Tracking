<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use App\Models\User;
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

    public bool $showForm    = false;
    public ?int $editingId   = null;
    public string $search    = '';

    public bool $confirmingDelete = false;
    public ?int $deleteId = null;

    protected function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'startDate'   => 'required|date',
            'endDate'     => 'required|date|after_or_equal:startDate',
            'status'      => 'required|in:active,on_hold,completed',
            'clientId'    => 'nullable|exists:users,id',
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

        if ($this->editingId) {
            Project::findOrFail($this->editingId)->update($payload);
            session()->flash('success', 'Project updated successfully.');
        } else {
            Project::create(array_merge($payload, ['created_by' => auth()->id()]));
            session()->flash('success', 'Project created successfully.');
        }

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

    private function resetForm(): void
    {
        $this->name        = '';
        $this->description = '';
        $this->startDate   = '';
        $this->endDate     = '';
        $this->status      = 'active';
        $this->clientId    = null;
        $this->editingId   = null;
        $this->resetValidation();
    }

    public function render()
    {
        $projects = Project::with(['client', 'teams', 'tasks'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->latest()
            ->get();

        $clients = User::where('role', 'client')->orderBy('name')->get();

        return view('livewire.admin.project-manager', compact('projects', 'clients'));
    }
}
