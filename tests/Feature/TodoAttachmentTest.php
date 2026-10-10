<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\CreateTodoTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\UpdateTodoTool;
use App\Models\Todo;
use App\Models\TodoComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TodoAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(TodoComment::DISK);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    public function test_files_are_attached_together_with_the_description(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        $this->actingAs($user)
            ->post("/todos/{$todo->id}", [
                '_method' => 'patch',
                'description' => '<p>Viz návrh.</p>',
                'files' => [
                    UploadedFile::fake()->image('navrh.png'),
                    UploadedFile::fake()->create('zadani.pdf', 120, 'application/pdf'),
                ],
            ])
            ->assertSessionHasNoErrors();

        $todo->refresh();
        $this->assertSame('<p>Viz návrh.</p>', $todo->description);
        $this->assertSame(['navrh.png', 'zadani.pdf'], $todo->attachments->pluck('original_name')->all());
        $this->assertTrue($todo->attachments[0]->is_image);
        $this->assertTrue($todo->attachments[1]->is_pdf);

        foreach ($todo->attachments as $attachment) {
            Storage::disk(TodoComment::DISK)->assertExists($attachment->path);
            $this->assertStringStartsWith("todo-comments/{$todo->id}/", $attachment->path);
        }
    }

    public function test_attachments_can_be_removed(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        $this->actingAs($user)->post("/todos/{$todo->id}", ['_method' => 'patch', 'files' => [UploadedFile::fake()->image('stary.png')]]);
        $old = $todo->attachments()->firstOrFail();

        $this->actingAs($user)
            ->post("/todos/{$todo->id}", [
                '_method' => 'patch',
                'remove_attachment_ids' => [$old->id],
                'files' => [UploadedFile::fake()->image('novy.png')],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['novy.png'], $todo->attachments()->pluck('original_name')->all());
        Storage::disk(TodoComment::DISK)->assertMissing($old->path);
    }

    public function test_deleting_the_todo_removes_its_files(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        $this->actingAs($user)->post("/todos/{$todo->id}", ['_method' => 'patch', 'files' => [UploadedFile::fake()->image('a.png')]]);
        $path = $todo->attachments()->firstOrFail()->path;

        $this->actingAs($user)->delete("/todos/{$todo->id}");

        Storage::disk(TodoComment::DISK)->assertMissing($path);
    }

    public function test_pdfs_open_inline_unless_a_download_is_asked_for(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        $this->actingAs($user)->post("/todos/{$todo->id}", [
            '_method' => 'patch',
            'files' => [
                UploadedFile::fake()->create('zadani.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('data.zip', 10, 'application/zip'),
            ],
        ]);
        [$pdf, $zip] = $todo->attachments()->get()->all();

        $inline = $this->actingAs($user)->get($pdf->url)->assertOk();
        $this->assertStringStartsWith('inline', $inline->headers->get('Content-Disposition'));
        // The browser's PDF viewer does not run in a sandboxed document.
        $this->assertFalse($inline->headers->has('Content-Security-Policy'));

        $download = $this->actingAs($user)->get($pdf->url.'?download=1')->assertOk();
        $this->assertStringStartsWith('attachment', $download->headers->get('Content-Disposition'));

        $this->actingAs($user)->get($zip->url)
            ->assertOk()
            ->assertHeader('Content-Security-Policy', 'sandbox')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        auth()->logout();

        $this->get($pdf->url)->assertRedirect();
    }

    public function test_the_todo_detail_page_includes_the_attachments(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        $this->actingAs($user)->post("/todos/{$todo->id}", ['_method' => 'patch', 'files' => [UploadedFile::fake()->image('a.png')]]);

        $this->actingAs($user)
            ->get("/todos/{$todo->id}")
            ->assertInertia(fn ($page) => $page
                ->component('Todos/Show')
                ->where('todo.attachments.0.original_name', 'a.png')
                ->where('todo.attachments.0.is_image', true)
                ->missing('todo.attachments.0.path')
                ->etc());
    }

    public function test_mcp_creates_a_todo_with_description_attachments(): void
    {
        $existing = Todo::factory()->create();

        CrmServer::actingAs($this->manager())
            ->tool(CreateTodoTool::class, [
                'todolist_id' => $existing->todolist_id,
                'name' => 'Nový banner',
                'description' => 'Podle **návrhu**.',
                'attachments' => [['name' => 'navrh.txt', 'content_base64' => base64_encode('ahoj'), 'caption' => 'Návrh']],
            ])
            ->assertOk()
            ->assertSee('příloh: 1');

        $todo = Todo::where('name', 'Nový banner')->firstOrFail();
        $attachment = $todo->attachments()->firstOrFail();
        $this->assertSame('navrh.txt', $attachment->original_name);
        $this->assertSame('Návrh', $attachment->caption);
        $this->assertStringStartsWith("todo-comments/{$todo->id}/", $attachment->path);
        $this->assertSame('ahoj', Storage::disk(TodoComment::DISK)->get($attachment->path));
    }

    public function test_mcp_adds_and_removes_description_attachments(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create(['name' => 'Banner']);

        CrmServer::actingAs($user)
            ->tool(UpdateTodoTool::class, [
                'id' => $todo->id,
                'attachments' => [['name' => 'stary.txt', 'content_base64' => base64_encode('a')]],
            ])
            ->assertOk();

        $old = $todo->attachments()->firstOrFail();

        CrmServer::actingAs($user)
            ->tool(GetProjectTool::class, ['id' => $todo->todolist->project_id])
            ->assertOk()
            ->assertSee('stary.txt');

        CrmServer::actingAs($user)
            ->tool(UpdateTodoTool::class, [
                'id' => $todo->id,
                'remove_attachment_ids' => [$old->id],
                'attachments' => [['name' => 'novy.txt', 'content_base64' => base64_encode('b')]],
            ])
            ->assertOk();

        $this->assertSame(['novy.txt'], $todo->attachments()->pluck('original_name')->all());
        $this->assertSame('Banner', $todo->refresh()->name);
        Storage::disk(TodoComment::DISK)->assertMissing($old->path);
    }

    public function test_mcp_rejects_an_invalid_attachment_without_changing_the_todo(): void
    {
        $todo = Todo::factory()->create(['name' => 'Banner']);

        CrmServer::actingAs($this->manager())
            ->tool(UpdateTodoTool::class, [
                'id' => $todo->id,
                'name' => 'Jiný název',
                'attachments' => [['name' => 'x.txt', 'content_base64' => '***']],
            ])
            ->assertHasErrors();

        $this->assertSame('Banner', $todo->refresh()->name);
        $this->assertSame(0, $todo->attachments()->count());
    }
}
