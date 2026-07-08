<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use App\Models\Task;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app', ['lockMainScroll' => true])]
#[Title('Task oversight')]
class TaskAssigner extends Component
{
    use WithPagination;

    /** @var int Tasks per page (oversight list) */
    public int $perPage = 20;

    public string $filterStatus = '';

    public ?int $filterProject = null;

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFilterProject(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $allowed = [15, 20, 50, 100];
        $n       = (int) $this->perPage;
        $this->perPage = in_array($n, $allowed, true) ? $n : 20;
        $this->resetPage();
    }

    public function render()
    {
        $tasks = Task::with(['project', 'team', 'assignee', 'assignees', 'creator', 'memberProgress.user'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterProject, fn ($q) => $q->where('project_id', $this->filterProject))
            ->latest('created_at')
            ->latest('id')
            ->paginate($this->perPage);

        $projects = Project::orderBy('name')->get();

        return view('livewire.admin.task-assigner', compact('tasks', 'projects'));
    }
}
