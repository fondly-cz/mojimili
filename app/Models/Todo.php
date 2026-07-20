<?php

namespace App\Models;

use Database\Factories\TodoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Todo extends Model
{
    /** @use HasFactory<TodoFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_done' => 'boolean',
        'completed_at' => 'datetime',
        'due_date' => 'date',
        'days' => 'integer',
        'parent_id' => 'integer',
    ];

    /**
     * @return BelongsTo<Todolist, $this>
     */
    public function todolist(): BelongsTo
    {
        return $this->belongsTo(Todolist::class);
    }

    /**
     * @return BelongsTo<CalculationItem, $this>
     */
    public function calculationItem(): BelongsTo
    {
        return $this->belongsTo(CalculationItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * @return BelongsTo<Todo, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Todo::class, 'parent_id');
    }

    /**
     * @return HasMany<Todo, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Todo::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }
}
