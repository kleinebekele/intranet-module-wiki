<?php

namespace Intranet\Modules\Wiki\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Eine Wiki-Seite: ein freier Beitrag oder ein Hilfetext.
 *
 * Der Unterschied ist nur die Herkunft, nicht die Bauart – beide bestehen aus
 * Abschnitten mit Rollen-Tags, beide werden gleich gelesen und durchsucht.
 */
class WikiSeite extends Model
{
    public const ART_BEITRAG = 'beitrag';

    public const ART_HILFE = 'hilfe';

    protected $table = 'wiki_seiten';

    /**
     * Route => Hilfe-Adresse, gemerkt fuer die Dauer einer Anfrage.
     * Der Core fragt einmal pro Seitenaufruf; ohne Gedaechtnis waere das bei
     * mehrfacher Auswertung im Layout eine Abfrage zu viel.
     *
     * @var array<string, string|null>
     */
    private static array $hilfeMemo = [];

    protected $fillable = [
        'titel', 'slug', 'kategorie', 'art', 'hilfe_fuer_route',
        'hilfe_quelle', 'hilfe_datei', 'angepasst', 'user_id', 'position',
    ];

    protected function casts(): array
    {
        return [
            'angepasst' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function abschnitte(): HasMany
    {
        return $this->hasMany(WikiAbschnitt::class, 'wiki_seite_id')->orderBy('position');
    }

    public function versionen(): HasMany
    {
        return $this->hasMany(WikiVersion::class, 'wiki_seite_id')->latest('created_at');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeBeitraege(Builder $query): Builder
    {
        return $query->where('art', self::ART_BEITRAG);
    }

    public function scopeHilfe(Builder $query): Builder
    {
        return $query->where('art', self::ART_HILFE);
    }

    public function istHilfe(): bool
    {
        return $this->art === self::ART_HILFE;
    }

    /** Stammt diese Seite aus einer Paket-Datei (und wird von dort gepflegt)? */
    public function ausDatei(): bool
    {
        return $this->hilfe_quelle !== null;
    }

    /**
     * Die Abschnitte, die dieser Rollen-Satz sehen darf.
     *
     * @param  Collection<int, string>  $rollenIds
     * @return Collection<int, WikiAbschnitt>
     */
    public function sichtbareAbschnitte(Collection $rollenIds): Collection
    {
        return $this->abschnitte->filter(fn (WikiAbschnitt $a) => $a->sichtbarFuer($rollenIds))->values();
    }

    /**
     * Eine eindeutige Adresse aus dem Titel. Kollidiert sie, wird gezählt
     * (howto, howto-2, howto-3 ...) – die eigene Zeile zählt nicht mit.
     */
    public static function freierSlug(string $titel, ?int $ausser = null): string
    {
        $basis = Str::slug($titel) ?: 'seite';
        $slug = $basis;
        $nummer = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($ausser, fn (Builder $q) => $q->whereKeyNot($ausser))
            ->exists()) {
            $slug = $basis.'-'.(++$nummer);
        }

        return $slug;
    }

    /**
     * Antwort auf die Frage des Cores: Gibt es zu dieser Route eine Hilfe?
     *
     * Bewusst schlank gehalten – das läuft bei jedem Seitenaufruf. Gefunden
     * wird nur, was der Benutzer auch lesen darf; sonst zeigte der "?"-Knopf
     * auf eine 403.
     */
    public static function urlFuerRoute(string $routeName, ?User $user): ?string
    {
        if (! array_key_exists($routeName, static::$hilfeMemo)) {
            $seite = static::query()
                ->hilfe()
                ->where('hilfe_fuer_route', $routeName)
                ->first();

            static::$hilfeMemo[$routeName] = $seite ? route('module.wiki.show', $seite->slug) : null;
        }

        return static::$hilfeMemo[$routeName];
    }

    /** Nur fuer Tests: das Gedaechtnis der Kontexthilfe leeren. */
    public static function hilfeMemoLeeren(): void
    {
        static::$hilfeMemo = [];
    }
}
