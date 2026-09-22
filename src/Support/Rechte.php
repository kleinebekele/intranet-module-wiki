<?php

namespace Intranet\Modules\Wiki\Support;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Wer darf was im Wiki.
 *
 * Lesen darf jeder, der den Menüpunkt sehen darf – das entscheidet der Core
 * (EnsureModuleAccess) und wird hier nicht noch einmal nachgebaut. Hier steht
 * nur, was darüber hinausgeht.
 *
 * Wichtig: Die Schreibrouten haben keinen eigenen Menüpunkt, für sie greift im
 * Core die milde Auffangregel ("darf irgendeinen Punkt des Moduls sehen").
 * Deshalb MUSS jede schreibende Route hier zusätzlich prüfen.
 */
class Rechte
{
    public const ADMIN = 'wiki-admin';

    public const MODERATOR = 'wiki-moderator';

    /** Seiten anlegen, bearbeiten, Rollen-Tags vergeben. */
    public static function darfBearbeiten(?User $user): bool
    {
        return static::hatRolle($user, [self::ADMIN, self::MODERATOR]);
    }

    /** Löschen und Datei-Hilfeseiten auf den Paket-Stand zurücksetzen. */
    public static function darfVerwalten(?User $user): bool
    {
        return static::hatRolle($user, [self::ADMIN]);
    }

    /**
     * Die Rollen-Kennungen eines Benutzers – die Grundlage jeder
     * Abschnitts-Sichtbarkeit.
     *
     * @return Collection<int, string>
     */
    public static function rollenIds(?User $user): Collection
    {
        $ids = $user?->roles->pluck('role_id') ?? collect();

        // Rollen eines deaktivierten Moduls gelten nicht (neuerer Core); ein
        // älterer Core kennt die Unterscheidung noch nicht.
        if (method_exists(Role::class, 'inaktiveSchluessel')) {
            $ids = $ids->diff(Role::inaktiveSchluessel())->values();
        }

        // Administrator ist im Core ein Merkmal am Benutzer (is_admin), keine
        // zugewiesene Rolle. Abschnitte mit "rollen: admin" waren deshalb selbst
        // für Administratoren ausgeblendet.
        if ($user?->is_admin && ! $ids->contains('admin')) {
            $ids = $ids->push('admin')->values();
        }

        return $ids;
    }

    /** @param  string[]  $rollen */
    private static function hatRolle(?User $user, array $rollen): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return self::rollenIds($user)->intersect($rollen)->isNotEmpty();
    }
}
