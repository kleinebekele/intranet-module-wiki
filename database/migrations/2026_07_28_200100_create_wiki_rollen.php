<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Die beiden Wiki-Rollen.
 *
 * Bewusst KEINE System-Rollen: Sie gehören dem Modul, nicht der Plattform,
 * und sollen mit `modules:uninstall --mit-daten` wieder verschwinden dürfen.
 *
 * Eine dritte Rolle "Wiki-Benutzer" gibt es absichtlich nicht – lesen darf,
 * wer den Menüpunkt sehen darf. Das regelt der Core bereits (Verwaltung ->
 * Module -> Wiki -> Rollen des Punktes "Übersicht" auf `user` stellen).
 */
return new class extends Migration
{
    private const ROLLEN = [
        'wiki-admin' => 'Wiki-Administrator',
        'wiki-moderator' => 'Wiki-Moderator',
    ];

    public function up(): void
    {
        foreach (self::ROLLEN as $id => $name) {
            if (DB::table('roles')->where('role_id', $id)->exists()) {
                continue; // Namen einer vorhandenen Rolle nicht überschreiben
            }

            DB::table('roles')->insert([
                'role_id' => $id,
                'name' => $name,
                'is_system' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // cascade räumt user_roles und die Abschnitts-Tags mit auf
        DB::table('roles')->whereIn('role_id', array_keys(self::ROLLEN))->delete();
    }
};
