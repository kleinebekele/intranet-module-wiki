<?php

namespace Intranet\Modules\Wiki\Models;

use App\Models\Module;
use App\Models\User;
use App\Modules\Support\Modulzugriff;
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

    /**
     * Darf der Benutzer das Modul benutzen, zu dem diese Seite gehört?
     *
     * Die Rollen-Tags der Abschnitte regeln nur, WER innerhalb eines Themas was
     * liest. Ob das Thema überhaupt für ihn da ist, entscheidet der Modulzugang:
     *  - Seite mit Ziel-Route: ginge ein Aufruf dieser Route durch (dieselbe
     *    Prüfung wie `@darfRoute`)? Routen außerhalb von `module.*` sind frei.
     *  - Sonst Hilfe aus einem Modul (`hilfe_quelle = modul:…`): sieht er
     *    mindestens einen Menüpunkt dieses Moduls?
     *  - Alles andere (Core-Hilfe, freie Beiträge): frei.
     *
     * Wiki-Bearbeiter umgehen das an den Aufrufstellen, nicht hier.
     */
    public function modulFreigegebenFuer(?User $user): bool
    {
        if (filled($this->hilfe_fuer_route)) {
            return $user !== null && app(Modulzugriff::class)->darfRoute($this->hilfe_fuer_route, $user);
        }

        if (is_string($this->hilfe_quelle) && str_starts_with($this->hilfe_quelle, 'modul:')) {
            return $user !== null && static::modulStufe(substr($this->hilfe_quelle, 6), $user);
        }

        return true;
    }

    /** Modulzugang je Benutzer und Modul – einmal je Anfrage nachgeschlagen. */
    private static function modulStufe(string $key, User $user): bool
    {
        $merker = 'wiki.modulzugang.'.$user->id.'.'.$key;
        $attribute = request()->attributes;

        if (! $attribute->has($merker)) {
            $modul = Module::query()->with('menuItems.roles')->where('key', $key)->first();
            $attribute->set($merker, $user->is_admin
                || ($modul !== null && $modul->is_enabled && $modul->stufeFuer($user) !== null));
        }

        return $attribute->get($merker);
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
     *
     * Die Adresse der aufrufenden Seite reist als `zurueck` mit, damit die
     * Hilfeseite einen Rücksprung anbieten kann. Gemerkt wird deshalb nur der
     * Slug, nicht die fertige URL.
     */
    public static function urlFuerRoute(string $routeName, ?User $user): ?string
    {
        if (! array_key_exists($routeName, static::$hilfeMemo)) {
            $seite = static::query()
                ->hilfe()
                ->where('hilfe_fuer_route', $routeName)
                ->first();

            static::$hilfeMemo[$routeName] = $seite?->slug;
        }

        $slug = static::$hilfeMemo[$routeName];

        if ($slug === null) {
            return null;
        }

        // Nur Pfad und Query: Hinter einem Proxy weichen Schema oder Host der
        // Anfrage von APP_URL ab, eine volle Adresse fiele dann in der Pruefung durch.
        return route('module.wiki.show', ['seite' => $slug, 'zurueck' => request()->getRequestUri()]);
    }

    /**
     * Den `zurueck`-Parameter absichern: Nur Adressen dieser Anwendung werden
     * angenommen, alles andere (fremde Hosts, `javascript:`, `//evil`) verfällt.
     */
    public static function sichereRuecksprungAdresse(?string $adresse): ?string
    {
        if ($adresse === null || $adresse === '') {
            return null;
        }

        $adresse = trim($adresse);

        // Steuerzeichen und Backslashes vorab verwerfen: Browser lassen Tab/
        // Zeilenumbruch mitten in der Adresse fallen und lesen "\" wie "/" -
        // aus "/\t/evil" oder "/\evil" wird sonst "//evil", also ein fremder Host.
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $adresse)) {
            return null;
        }

        // Pfad dieser Anwendung: genau ein fuehrender Schraegstrich.
        if (str_starts_with($adresse, '/')) {
            return str_starts_with($adresse, '//') ? null : $adresse;
        }

        // Volle Adressen: eigener Host genuegt, das Schema darf abweichen (Proxy).
        $host = parse_url($adresse, PHP_URL_HOST);
        $schema = parse_url($adresse, PHP_URL_SCHEME);
        $eigeneHosts = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            request()->getHost(),
        ]);

        if (in_array($schema, ['http', 'https'], true) && $host !== null && in_array($host, $eigeneHosts, true)) {
            return $adresse;
        }

        return null;
    }

    /** Nur fuer Tests: das Gedaechtnis der Kontexthilfe leeren. */
    public static function hilfeMemoLeeren(): void
    {
        static::$hilfeMemo = [];
    }
}
