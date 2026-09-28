<?php

namespace App\Models;

use App\Support\RichText;
use Database\Factories\ProjectDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A rich-text document attached to a project (documents in Freelo).
 */
class ProjectDocument extends Model
{
    /** @use HasFactory<ProjectDocumentFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $partial = false): array
    {
        return [
            'name' => ($partial ? 'sometimes|' : '').'required|string|max:255',
            'content' => 'nullable|string',
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * The content is rendered as HTML; see RichText for the conversion and sanitizing.
     */
    public function setContentAttribute(?string $value): void
    {
        $this->attributes['content'] = RichText::toHtml($value);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
