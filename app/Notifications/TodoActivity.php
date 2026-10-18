<?php

namespace App\Notifications;

use App\Models\Todo;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person about something that happened on a todo: it was handed to them, commented on or completed.
 */
class TodoActivity extends Notification implements ShouldQueue
{
    use Queueable;

    public const ASSIGNED = 'assigned';

    public const COMMENTED = 'commented';

    public const COMPLETED = 'completed';

    public function __construct(
        public Todo $todo,
        public User $actor,
        public string $type,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->message().': '.$this->todo->name)
            ->greeting('Dobrý den,')
            ->line($this->message().'.')
            ->line('Úkol: '.$this->todo->name)
            ->action('Zobrazit úkol', route('todos.show', $this->todo));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'todo_id' => $this->todo->id,
            'todo_name' => $this->todo->name,
            'actor_name' => $this->actor->name,
            'message' => $this->message(),
            'url' => route('todos.show', $this->todo, absolute: false),
        ];
    }

    private function message(): string
    {
        return match ($this->type) {
            self::ASSIGNED => "{$this->actor->name} vám přiřadil(a) úkol",
            self::COMMENTED => "{$this->actor->name} přidal(a) komentář k úkolu",
            self::COMPLETED => "{$this->actor->name} dokončil(a) úkol",
        };
    }
}
