<?php

namespace Tests\Feature;

use App\Livewire\Lead\LeadTaskManager;
use App\Livewire\Member\MemberDashboard;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LeadTaskAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_lead_can_attach_a_file_when_creating_a_task(): void
    {
        Storage::fake('local');

        $lead = User::factory()->create(['role' => 'team_lead']);
        $member = User::factory()->create(['role' => 'member']);
        $project = Project::create([
            'name' => 'Attachment Project',
            'description' => 'Test project',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'active',
            'created_by' => $lead->id,
        ]);
        $team = Team::create([
            'name' => 'Attachment Team',
            'project_id' => $project->id,
            'lead_id' => $lead->id,
        ]);
        $team->members()->attach($lead->id, ['role' => 'lead']);
        $team->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($lead)->withSession([
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'lead',
        ]);

        Livewire::test(LeadTaskManager::class)
            ->call('openCreate')
            ->set('title', 'Review attached brief')
            ->set('teamId', $team->id)
            ->set('assignedTo', [(string) $member->id])
            ->set('dueDate', '2026-07-20')
            ->set('newAttachments', [
                UploadedFile::fake()->create('project-brief.pdf', 120, 'application/pdf'),
            ])
            ->call('save')
            ->assertHasNoErrors();

        $task = Task::where('title', 'Review attached brief')->firstOrFail();
        $attachment = $task->attachments()->firstOrFail();

        $this->assertSame('project-brief.pdf', $attachment->original_name);
        $this->assertSame($lead->id, $attachment->uploaded_by);
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($member)->withSession([
            'active_project_id' => $project->id,
            'active_team_id' => $team->id,
            'active_project_role' => 'member',
        ]);

        Livewire::test(MemberDashboard::class)
            ->call('toggleExpand', $task->id)
            ->assertSee('project-brief.pdf');
    }
}
