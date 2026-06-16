<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Resolves Excel / JSON "Project" column values to existing Project records.
 *
 * Uses canonical name normalization (whitespace aliases) and exact
 * case-insensitive matching only — no fuzzy substring matching that could
 * link "AMOMC-OR" to "AMOMC Project" incorrectly.
 */
class DailyTransactionProjectResolver
{
    /** @var array<string, string> */
    public const NAME_ALIASES = [
        'admin -logistics' => 'Admin - Logistics',
        'admin- logistics' => 'Admin - Logistics',
        'admin-  logistics' => 'Admin - Logistics',
    ];

    /** @var array<string, int>|null */
    private static ?array $projectMap = null;

    public static function canonicalName(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);

        if ($trimmed === '') {
            return null;
        }

        $key = Str::lower(preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed);

        return self::NAME_ALIASES[$key] ?? $trimmed;
    }

    public static function normalizeKey(?string $name): ?string
    {
        $canonical = self::canonicalName($name);

        if ($canonical === null) {
            return null;
        }

        return Str::lower($canonical);
    }

    /**
     * @return array<string, int> lowercase project name => id
     */
    public static function projectMap(bool $refresh = false): array
    {
        if (self::$projectMap !== null && ! $refresh) {
            return self::$projectMap;
        }

        self::$projectMap = Project::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Project $p) => [self::normalizeKey($p->name) => $p->id])
            ->filter(fn ($id, $key) => $key !== null && $key !== '')
            ->toArray();

        return self::$projectMap;
    }

    public static function resolveId(?string $rawName): ?int
    {
        $key = self::normalizeKey($rawName);

        if ($key === null) {
            return null;
        }

        return self::projectMap()[$key] ?? null;
    }

    public static function forgetCache(): void
    {
        self::$projectMap = null;
    }
}
