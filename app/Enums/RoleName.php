<?php

namespace App\Enums;

/**
 * Les rôles de la V1 (cahier §6, CLAUDE.md §4.2). Un utilisateur a exactement un rôle.
 * Niveaux et plafond de rôle vivent dans config/role_hierarchy.php.
 */
enum RoleName: string
{
    case Superadmin = 'superadmin';
    case Administrateur = 'administrateur';
    case Magasinier = 'magasinier';

    public function label(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Administrateur => 'Administrateur technique',
            self::Magasinier => 'Magasinier / Comptable matière',
        };
    }

    public function level(): int
    {
        return (int) config("role_hierarchy.levels.{$this->value}", 0);
    }

    /**
     * Rôles que ce rôle peut créer et gérer (plafond de rôle).
     *
     * @return list<self>
     */
    public function manages(): array
    {
        return array_map(self::from(...), config("role_hierarchy.manages.{$this->value}", []));
    }

    public function canManage(self $target): bool
    {
        return in_array($target, $this->manages(), true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
