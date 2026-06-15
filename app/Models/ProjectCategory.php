<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectCategory extends Model
{
    protected $fillable = ['parent_id', 'name'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function fullLabel(): string
    {
        return $this->parent ? "{$this->parent->name} › {$this->name}" : $this->name;
    }

    /**
     * Selectable options for dropdowns: top-level categories with no
     * children are selectable directly; categories with children are
     * not selectable themselves — only their children are, prefixed
     * with the parent name.
     */
    public static function selectOptions(): array
    {
        return self::with('children')->whereNull('parent_id')->orderBy('name')->get()
            ->flatMap(fn ($top) => $top->children->isEmpty()
                ? [['id' => $top->id, 'label' => $top->name, 'search' => strtolower($top->name)]]
                : $top->children->map(fn ($c) => [
                    'id' => $c->id,
                    'label' => "{$top->name} › {$c->name}",
                    'search' => strtolower("{$top->name} {$c->name}"),
                ]))
            ->values()->all();
    }
}
