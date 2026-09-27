<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\CreateTodoCommentTool;
use App\Mcp\Tools\GetProjectTool;
use App\Models\Todo;
use App\Models\TodoComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TodoCommentTest extends TestCase
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

    public function test_a_post_with_attachments_is_added_to_the_thread(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        $this->actingAs($user)
            ->post("/todos/{$todo->id}/comments", [
                'body' => '<p>Posílám <strong>podklady</strong>.</p>',
                'files' => [
                    UploadedFile::fake()->image('logo.png'),
                    UploadedFile::fake()->create('smlouva.pdf', 120, 'application/pdf'),
                ],
            ])
            ->assertSessionHasNoErrors();

        $comment = $todo->comments()->with('attachments')->firstOrFail();
        $this->assertSame($user->id, $comment->user_id);
        $this->assertSame('<p>Posílám <strong>podklady</strong>.</p>', $comment->body);
        $this->assertSame(['logo.png', 'smlouva.pdf'], $comment->attachments->pluck('original_name')->all());
        $this->assertTrue($comment->attachments[0]->is_image);

        foreach ($comment->attachments as $attachment) {
            Storage::disk(TodoComment::DISK)->assertExists($attachment->path);
            $this->assertStringStartsWith("todo-comments/{$todo->id}/", $attachment->path);
        }
    }

    public function test_an_empty_post_is_rejected(): void
    {
        $todo = Todo::factory()->create();

        $this->actingAs($this->manager())
            ->post("/todos/{$todo->id}/comments", ['body' => '<p><br></p>'])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, $todo->comments()->count());
    }

    public function test_the_body_is_sanitized(): void
    {
        $comment = TodoComment::factory()->create([
            'body' => '<p onclick="alert(1)">Ahoj<script>alert(2)</script> <a href="javascript:alert(3)">x</a> <a href="https://fondly.cz">web</a></p>',
        ]);

        $this->assertStringNotContainsString('script', $comment->body);
        $this->assertStringNotContainsString('onclick', $comment->body);
        $this->assertStringNotContainsString('javascript:', $comment->body);
        $this->assertStringContainsString('href="https://fondly.cz"', $comment->body);
    }

    public function test_plain_text_body_keeps_its_line_breaks(): void
    {
        $comment = TodoComment::factory()->create(['body' => "První řádek\ndruhý řádek"]);

        $this->assertStringContainsString('První řádek<br', $comment->body);
        $this->assertStringStartsWith('<p>', $comment->body);
    }

    public function test_the_author_edits_text_and_attachments(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        $this->actingAs($user)->post("/todos/{$todo->id}/comments", [
            'body' => '<p>Původní</p>',
            'files' => [UploadedFile::fake()->create('stary.txt', 1)],
        ]);

        $comment = $todo->comments()->firstOrFail();
        $old = $comment->attachments()->firstOrFail();

        $this->actingAs($user)
            ->post("/todo-comments/{$comment->id}", [
                '_method' => 'patch',
                'body' => '<p>Opravený</p>',
                'remove_attachment_ids' => [$old->id],
                'files' => [UploadedFile::fake()->create('novy.txt', 1)],
            ])
            ->assertSessionHasNoErrors();

        $comment->refresh();
        $this->assertSame('<p>Opravený</p>', $comment->body);
        $this->assertSame(['novy.txt'], $comment->attachments()->pluck('original_name')->all());
        Storage::disk(TodoComment::DISK)->assertMissing($old->path);
    }

    public function test_only_the_author_or_an_admin_may_change_a_post(): void
    {
        $comment = TodoComment::factory()->create(['user_id' => $this->manager()->id]);

        $this->actingAs($this->manager())
            ->patch("/todo-comments/{$comment->id}", ['body' => 'Cizí úprava'])
            ->assertForbidden();

        $this->actingAs($this->manager())
            ->delete("/todo-comments/{$comment->id}")
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->delete("/todo-comments/{$comment->id}")
            ->assertRedirect();

        $this->assertModelMissing($comment);
    }

    public function test_deleting_a_post_or_its_todo_removes_the_files(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        $this->actingAs($user)->post("/todos/{$todo->id}/comments", ['files' => [UploadedFile::fake()->create('a.txt', 1)]]);
        $this->actingAs($user)->post("/todos/{$todo->id}/comments", ['files' => [UploadedFile::fake()->create('b.txt', 1)]]);

        [$first, $second] = $todo->comments()->with('attachments')->get()->all();

        $this->actingAs($user)->delete("/todo-comments/{$first->id}");
        Storage::disk(TodoComment::DISK)->assertMissing($first->attachments[0]->path);
        Storage::disk(TodoComment::DISK)->assertExists($second->attachments[0]->path);

        $this->actingAs($user)->delete("/todos/{$todo->id}");
        Storage::disk(TodoComment::DISK)->assertMissing($second->attachments[0]->path);
    }

    public function test_deleting_a_project_removes_the_files_of_its_todos(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        $this->actingAs($user)->post("/todos/{$todo->id}/comments", ['files' => [UploadedFile::fake()->create('a.txt', 1)]]);
        $path = $todo->comments()->firstOrFail()->attachments()->firstOrFail()->path;

        $this->actingAs($user)
            ->post('/projects/bulk-delete', ['ids' => [$todo->todolist->project_id]])
            ->assertSessionHasNoErrors();

        Storage::disk(TodoComment::DISK)->assertMissing($path);
    }

    public function test_attachments_are_served_to_signed_in_users_only(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        $this->actingAs($user)->post("/todos/{$todo->id}/comments", ['files' => [UploadedFile::fake()->image('foto.jpg')]]);
        $attachment = $todo->comments()->firstOrFail()->attachments()->firstOrFail();

        $this->actingAs($user)
            ->get($attachment->url)
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        auth()->logout();

        $this->get($attachment->url)->assertRedirect();
    }

    public function test_the_project_page_includes_the_thread(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();
        TodoComment::factory()->create(['todo_id' => $todo->id, 'user_id' => $user->id, 'body' => '<p>Ahoj</p>']);

        $this->actingAs($user)
            ->get("/projects/{$todo->todolist->project_id}")
            ->assertInertia(fn ($page) => $page
                ->where('project.todolists.0.todos.0.comments.0.body', '<p>Ahoj</p>')
                ->where('project.todolists.0.todos.0.comments.0.user.name', $user->name)
            );
    }

    public function test_mcp_imports_a_post_with_original_date_author_and_file(): void
    {
        $todo = Todo::factory()->create();

        CrmServer::actingAs($this->manager())
            ->tool(CreateTodoCommentTool::class, [
                'todo_id' => $todo->id,
                'body' => 'Založeno, čekačka na **wedos**',
                'author_name' => 'Jan Pilař',
                'created_at' => '2024-11-29 13:21',
                'attachments' => [['name' => 'poznamka.txt', 'content_base64' => base64_encode('ahoj')]],
            ])
            ->assertOk()
            ->assertSee('comment_id');

        $comment = $todo->comments()->with('attachments')->firstOrFail();
        $this->assertNull($comment->user_id);
        $this->assertSame('Jan Pilař', $comment->author_name);
        $this->assertSame('2024-11-29 13:21', $comment->created_at->format('Y-m-d H:i'));
        $this->assertSame('<p>Založeno, čekačka na <strong>wedos</strong></p>', $comment->body);
        $this->assertSame('poznamka.txt', $comment->attachments[0]->original_name);
        $this->assertSame('ahoj', Storage::disk(TodoComment::DISK)->get($comment->attachments[0]->path));

        CrmServer::actingAs($this->manager())
            ->tool(GetProjectTool::class, ['id' => $todo->todolist->project_id])
            ->assertOk()
            ->assertSee('Jan Pilař')
            ->assertSee('poznamka.txt');
    }

    public function test_mcp_rejects_an_empty_post(): void
    {
        $todo = Todo::factory()->create();

        CrmServer::actingAs($this->manager())
            ->tool(CreateTodoCommentTool::class, ['todo_id' => $todo->id, 'body' => '  '])
            ->assertHasErrors();

        $this->assertSame(0, $todo->comments()->count());
    }
}
