<?php

namespace Intranet\Modules\Wiki\Models;

use App\Models\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Ein Absatz einer Wiki-Seite – die Einheit, an der die Rollen-Tags hängen.
 */
class WikiAbschnitt extends Model
{
    protected $table = 'wiki_abschnitte';

    protected $fillable = ['wiki_seite_id', 'position', 'ueberschrift', 'inhalt', 'rollen_gefordert'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'rollen_gefordert' => 'boolean',
        ];
    }

    public function seite(): BelongsTo
    {
        return $this->belongsTo(WikiSeite::class, 'wiki_seite_id');
    }

    /** Die Rollen-Tags. Keine Tags = für alle sichtbar (siehe sichtbarFuer). */
    public function rollen(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'wiki_abschnitt_rolle', 'wiki_abschnitt_id', 'role_id', 'id', 'role_id');
    }

    /**
     * Darf dieser Rollen-Satz den Abschnitt sehen?
     *
     *  - keine Tags            -> ja (sonst müsste man jeden Absatz taggen)
     *  - Tags gesetzt          -> ja, wenn EINE davon passt
     *  - Tags gefordert, aber
     *    keine davon existiert -> nein (Rolle stammt aus einer Paket-Datei und
     *                             gibt es in dieser Instanz nicht – der Absatz
     *                             war für jemand Bestimmten gedacht, also
     *                             bleibt er weg statt für alle aufzugehen)
     *
     * @param  Collection<int, string>  $rollenIds
     */
    public function sichtbarFuer(Collection $rollenIds): bool
    {
        $tags = $this->rollen->pluck('role_id');

        if ($tags->isEmpty()) {
            return ! $this->rollen_gefordert;
        }

        return $tags->intersect($rollenIds)->isNotEmpty();
    }

    /** Ist der Abschnitt überhaupt eingeschränkt? (Für die Kennzeichnung im Backend.) */
    public function istEingeschraenkt(): bool
    {
        return $this->rollen_gefordert || $this->rollen->isNotEmpty();
    }

    /**
     * Der Inhalt als HTML. Gespeichert wird Markdown – roher HTML-Code wird
     * beim Rendern verworfen, damit ein Wiki-Beitrag kein Einfallstor ist.
     */
    public function html(): string
    {
        return Str::markdown($this->inhalt ?? '', [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    /** Kurze Leseprobe für Suchtreffer. */
    public function auszug(int $zeichen = 180): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($this->html()))), $zeichen);
    }
}
