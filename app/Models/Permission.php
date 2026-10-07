<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permission `module.action`, à deux voies (D23) : `manifest` (déclarée dans config/modules/*.php, gérée par
 * permissions:sync) ou `custom` (créée par le superadmin depuis l'interface, jamais touchée par la synchronisation).
 *
 * @property string $name
 * @property string $source
 */
class Permission extends SpatiePermission
{
    use LogsActivity;

    public const SOURCE_MANIFEST = 'manifest';

    public const SOURCE_CUSTOM = 'custom';

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_CUSTOM);
    }

    public function scopeManifest(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_MANIFEST);
    }

    public function isCustom(): bool
    {
        return $this->source === self::SOURCE_CUSTOM;
    }

    public function module(): string
    {
        return Str::before($this->name, '.');
    }

    public function activityModule(): string
    {
        return 'permissions';
    }

    public function activityNoun(): string
    {
        return 'Permission';
    }
}
