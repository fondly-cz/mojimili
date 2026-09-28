<?php

namespace App\Models;

use Database\Factories\LabelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A todo label shared across all projects (task labels in Freelo).
 */
class Label extends Model
{
    /** @use HasFactory<LabelFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['pivot'];

    public const DEFAULT_COLOR = '#77787a';

    /**
     * Validation rules shared by the web UI and the MCP tools.
     *
     * @return array<string, mixed>
     */
    public static function rules(?Label $label = null): array
    {
        return [
            'name' => 'required|string|max:100|unique:labels,name'.($label ? ','.$label->id : ''),
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * Finds labels by name and creates the missing ones, e.g. for labels sent through MCP.
     *
     * @param  iterable<string>  $names
     * @return list<int>
     */
    public static function idsForNames(iterable $names): array
    {
        return collect($names)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique()
            ->map(fn ($name) => static::firstOrCreate(['name' => $name], ['color' => self::DEFAULT_COLOR])->id)
            ->values()
            ->all();
    }

    /**
     * @return BelongsToMany<Todo, $this>
     */
    public function todos(): BelongsToMany
    {
        return $this->belongsToMany(Todo::class);
    }
}
