<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Board;
use App\Models\BoardTemplate;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    public function test_admin_can_create_template_with_work_groups_and_task_statuses(): void
    {
        $admin = User::factory()->admin()->create();
        $new = Status::where('key', 'new')->firstOrFail();
        $done = Status::where('key', 'done')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.template.store'), [
                'name' => 'Phát triển sản phẩm',
                'icon' => 'fa-code',
                'description' => 'Nhóm task theo chuyên môn.',
                'position' => 2,
                'columns' => ['Backend', 'Frontend', 'QA'],
                'status_ids' => [$new->id, $done->id],
                'labels' => [['name' => 'Gấp', 'color' => '#e5484d']],
            ])
            ->assertRedirect(route('admin.template.index'));

        $template = BoardTemplate::where('name', 'Phát triển sản phẩm')->firstOrFail();
        $this->assertSame(['Backend', 'Frontend', 'QA'], $template->columns);
        $this->assertSame([$new->id, $done->id], $template->status_ids);
    }

    public function test_template_rejects_duplicate_work_groups_case_insensitively(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.template.create'))
            ->post(route('admin.template.store'), [
                'name' => 'Template lỗi',
                'columns' => ['Backend', 'backend'],
                'labels' => [],
            ])
            ->assertRedirect(route('admin.template.create'))
            ->assertSessionHasErrors('columns');

        $this->assertDatabaseMissing('board_templates', ['name' => 'Template lỗi']);
    }

    public function test_creating_a_board_uses_template_groups_without_turning_statuses_into_columns(): void
    {
        $owner = User::factory()->create();
        $new = Status::where('key', 'new')->firstOrFail();
        $template = BoardTemplate::create([
            'name' => 'Theo đội',
            'icon' => 'fa-users',
            'columns' => ['Backend', 'QA'],
            'status_ids' => [$new->id],
            'labels' => [],
        ]);

        $this->actingAs($owner)
            ->post(route('boards.store'), ['name' => 'Bảng sản phẩm', 'template_id' => $template->id])
            ->assertRedirect(route('user.dashboard'));

        $board = Board::where('name', 'Bảng sản phẩm')->firstOrFail();
        $this->assertSame(['Backend', 'QA'], $board->columns()->pluck('name')->all());
        $this->assertSame([$new->id], $board->statuses()->pluck('statuses.id')->all());
    }
}
