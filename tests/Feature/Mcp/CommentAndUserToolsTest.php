<?php

namespace Tests\Feature\Mcp;

use App\Enums\UserRole;
use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\CreateTodoCommentTool;
use App\Mcp\Tools\CreateUserTool;
use App\Mcp\Tools\DeleteTodoCommentTool;
use App\Mcp\Tools\UpdateTodoCommentTool;
use App\Models\Todo;
use App\Models\TodoComment;
use App\Models\User;
use App\Support\RemoteFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommentAndUserToolsTest extends TestCase
{
    use RefreshDatabase;

    // Smallest valid PNG (1×1 px).
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(TodoComment::DISK);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::MANAGER]);
    }

    public function test_a_comment_takes_several_attachments_including_images_and_data_urls(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        CrmServer::actingAs($user)
            ->tool(CreateTodoCommentTool::class, [
                'todo_id' => $todo->id,
                'body' => 'Posílám **podklady**.',
                'attachments' => [
                    ['name' => 'logo.png', 'content_base64' => self::PNG],
                    ['name' => 'nahled.png', 'content_base64' => 'data:image/png;base64,'.self::PNG],
                    ['name' => 'poznamky.txt', 'content_base64' => base64_encode('text')],
                ],
            ])
            ->assertOk()
            ->assertSee('příloh: 3');

        $comment = $todo->comments()->with('attachments')->firstOrFail();
        $this->assertSame($user->id, $comment->user_id);
        $this->assertSame([true, true, false], $comment->attachments->pluck('is_image')->all());
        $this->assertSame('image/png', $comment->attachments[1]->mime_type);
    }

    /**
     * Resolves every host to the given IP instead of asking DNS.
     */
    private function fakeDns(string $ip): void
    {
        $this->app->instance(RemoteFile::class, new class($ip) extends RemoteFile
        {
            public function __construct(private string $ip) {}

            protected function resolve(string $host): array
            {
                return [$this->ip];
            }
        });
    }

    public function test_an_attachment_is_downloaded_from_a_url_following_redirects(): void
    {
        $this->fakeDns('93.184.216.34');
        Http::fake([
            'https://files.example.com/d/abc' => Http::response('', 302, ['Location' => 'https://cdn.example.com/x']),
            'https://cdn.example.com/x' => Http::response(base64_decode(self::PNG), 200, [
                'Content-Disposition' => 'attachment; filename="screenshot.png"',
            ]),
        ]);
        $todo = Todo::factory()->create();

        CrmServer::actingAs($this->manager())
            ->tool(CreateTodoCommentTool::class, [
                'todo_id' => $todo->id,
                'attachments' => [
                    ['url' => 'https://files.example.com/d/abc'],
                    ['url' => 'https://cdn.example.com/x', 'name' => 'vlastni.png'],
                ],
            ])
            ->assertOk();

        $attachments = $todo->comments()->firstOrFail()->attachments;
        $this->assertEqualsCanonicalizing(['screenshot.png', 'vlastni.png'], $attachments->pluck('original_name')->all());
        $this->assertTrue($attachments->every->is_image);
    }

    public function test_a_url_attachment_must_be_public_https(): void
    {
        $this->fakeDns('10.0.0.5');
        Http::fake();
        $todo = Todo::factory()->create();

        foreach (['https://intranet.example.com/a.png', 'http://example.com/a.png', 'https://127.0.0.1/a.png'] as $url) {
            CrmServer::actingAs($this->manager())
                ->tool(CreateTodoCommentTool::class, [
                    'todo_id' => $todo->id,
                    'attachments' => [['url' => $url]],
                ])
                ->assertHasErrors(['nepodařilo stáhnout']);
        }

        Http::assertNothingSent();
        $this->assertSame(0, $todo->comments()->count());
    }

    public function test_an_invalid_attachment_is_rejected_without_creating_the_comment(): void
    {
        $todo = Todo::factory()->create();

        CrmServer::actingAs($this->manager())
            ->tool(CreateTodoCommentTool::class, [
                'todo_id' => $todo->id,
                'body' => 'Text',
                'attachments' => [['name' => 'x.png', 'content_base64' => '***']],
            ])
            ->assertHasErrors(['x.png']);

        $this->assertSame(0, $todo->comments()->count());
    }

    public function test_the_author_updates_text_and_attachments(): void
    {
        $user = $this->manager();
        $todo = Todo::factory()->create();

        CrmServer::actingAs($user)->tool(CreateTodoCommentTool::class, [
            'todo_id' => $todo->id,
            'body' => 'Původní',
            'attachments' => [['name' => 'stary.txt', 'content_base64' => base64_encode('a')]],
        ]);

        $comment = $todo->comments()->with('attachments')->firstOrFail();
        $old = $comment->attachments->first();

        CrmServer::actingAs($user)
            ->tool(UpdateTodoCommentTool::class, [
                'id' => $comment->id,
                'body' => 'Opravený',
                'remove_attachment_ids' => [$old->id],
                'attachments' => [['name' => 'novy.png', 'content_base64' => self::PNG]],
            ])
            ->assertOk();

        $comment->refresh();
        $this->assertSame('<p>Opravený</p>', $comment->body);
        $this->assertSame(['novy.png'], $comment->attachments()->pluck('original_name')->all());
        Storage::disk(TodoComment::DISK)->assertMissing($old->path);
    }

    public function test_only_the_author_or_an_admin_may_update_or_delete_a_comment(): void
    {
        $comment = TodoComment::factory()->create(['user_id' => $this->manager()->id]);

        CrmServer::actingAs($this->manager())
            ->tool(UpdateTodoCommentTool::class, ['id' => $comment->id, 'body' => 'Cizí'])
            ->assertHasErrors(['jen jeho autor']);

        CrmServer::actingAs($this->manager())
            ->tool(DeleteTodoCommentTool::class, ['id' => $comment->id])
            ->assertHasErrors(['jen jeho autor']);

        CrmServer::actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->tool(DeleteTodoCommentTool::class, ['id' => $comment->id])
            ->assertOk();

        $this->assertModelMissing($comment);
    }

    public function test_a_manager_creates_a_user_without_crm_access(): void
    {
        CrmServer::actingAs($this->manager())
            ->tool(CreateUserTool::class, [
                'name' => 'Karel Nakládal',
                'email' => 'karel@example.com',
                'company' => 'Fondly',
            ])
            ->assertOk()
            ->assertSee('bez přístupu do CRM');

        $user = User::where('email', 'karel@example.com')->firstOrFail();
        $this->assertSame('Karel Nakládal', $user->name);
        $this->assertNull($user->role);
    }

    public function test_only_an_admin_may_grant_a_role(): void
    {
        CrmServer::actingAs($this->manager())
            ->tool(CreateUserTool::class, ['name' => 'Jan', 'email' => 'jan@example.com', 'role' => 'admin'])
            ->assertHasErrors(['pouze administrátor']);

        $this->assertDatabaseMissing('users', ['email' => 'jan@example.com']);

        CrmServer::actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->tool(CreateUserTool::class, ['name' => 'Jan', 'email' => 'jan@example.com', 'role' => 'manager'])
            ->assertOk();

        $this->assertSame(UserRole::MANAGER, User::where('email', 'jan@example.com')->firstOrFail()->role);
    }

    public function test_a_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'karel@example.com']);

        CrmServer::actingAs($this->manager())
            ->tool(CreateUserTool::class, ['name' => 'Karel', 'email' => 'karel@example.com'])
            ->assertHasErrors(['už v CRM existuje']);
    }
}
