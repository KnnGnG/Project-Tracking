<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('User Management')]
class UserManager extends Component
{
    // ── Filters ───────────────────────────────────────────────────────────────
    #[Url(as: 'role')]
    public string $filterRole = '';
    public string $search     = '';

    // ── Create / Edit form ────────────────────────────────────────────────────
    public bool   $showForm   = false;
    public ?int   $editingId  = null;
    public string $name       = '';
    public string $email      = '';
    public string $role       = 'member';
    public string $password   = '';

    public bool $confirmingDelete = false;
    public ?int $deleteId = null;
    public string $deleteName = '';

    // ── Role change confirmation ───────────────────────────────────────────────
    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm  = true;
        $this->editingId = null;
    }

    public function openEdit(int $id): void
    {
        $user = User::findOrFail($id);

        $this->editingId = $id;
        $this->name      = $user->name;
        $this->email     = $user->email;
        $this->role      = $user->role;
        $this->password  = '';
        $this->showForm  = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function save(): void
    {
        $rules = [
            'name'  => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role'  => 'required|in:admin,client,team_lead,member',
        ];

        if (! $this->editingId) {
            $rules['password'] = 'required|string|min:8';
        } elseif ($this->password !== '') {
            $rules['password'] = 'string|min:8';
        }

        $data = $this->validate($rules);

        $payload = [
            'name'  => $data['name'],
            'email' => $data['email'],
            'role'  => $data['role'],
        ];

        if (! $this->editingId || $this->password !== '') {
            $payload['password'] = Hash::make($this->password);
        }

        if ($this->editingId) {
            User::findOrFail($this->editingId)->update($payload);
            session()->flash('success', 'User updated successfully.');
        } else {
            User::create($payload);
            session()->flash('success', 'User created successfully.');
        }

        $this->resetForm();
        $this->showForm = false;
    }

    /** Inline role change directly from the table. */
    public function changeRole(int $id, string $role): void
    {
        abort_unless(in_array($role, ['admin', 'client', 'team_lead', 'member'], true), 422);
        User::findOrFail($id)->update(['role' => $role]);
        session()->flash('success', 'Role updated.');
    }

    public function delete(int $id): void
    {
        abort_if($id === auth()->id(), 403, 'You cannot delete your own account.');
        User::findOrFail($id)->delete();
        session()->flash('success', 'User deleted.');
    }

    public function confirmDelete(int $id): void
    {
        abort_if($id === auth()->id(), 403, 'You cannot delete your own account.');

        $user = User::findOrFail($id);
        $this->deleteId = $user->id;
        $this->deleteName = $user->name;
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
        $this->deleteName = '';
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name      = '';
        $this->email     = '';
        $this->role      = 'member';
        $this->password  = '';
        $this->resetValidation();
    }

    public function render()
    {
        $users = User::withCount(['ledTeams', 'teams'])
            ->when($this->filterRole, fn ($q) => $q->where('role', $this->filterRole))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%");
            }))
            ->orderByRaw("FIELD(role, 'admin', 'team_lead', 'member', 'client')")
            ->orderBy('name')
            ->get();

        $roleCounts = [
            'all'       => User::count(),
            'admin'     => User::where('role', 'admin')->count(),
            'team_lead' => User::where('role', 'team_lead')->count(),
            'member'    => User::where('role', 'member')->count(),
            'client'    => User::where('role', 'client')->count(),
        ];

        return view('livewire.admin.user-manager', compact('users', 'roleCounts'));
    }
}
