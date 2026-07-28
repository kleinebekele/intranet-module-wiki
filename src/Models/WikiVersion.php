<?php

namespace Intranet\Modules\Wiki\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein eingefrorener früherer Stand einer Seite.
 *
 * Geschrieben wird VOR dem Speichern der neuen Fassung – der Verlauf zeigt
 * also, was ersetzt wurde. Nur `created_at`, ein Verlaufseintrag ändert sich nie.
 */
class WikiVersion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'wiki_versionen';

    protected $fillable = ['wiki_seite_id', 'user_id', 'titel', 'abschnitte'];

    protected function casts(): array
    {
        return ['abschnitte' => 'array'];
    }

    public function seite(): BelongsTo
    {
        return $this->belongsTo(WikiSeite::class, 'wiki_seite_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Den aktuellen Stand einer Seite wegsichern.
     * Wird nur aufgerufen, wenn die Seite schon Abschnitte hat.
     */
    public static function sichern(WikiSeite $seite, ?User $user): ?self
    {
        $abschnitte = $seite->abschnitte()->with('rollen')->get();

        if ($abschnitte->isEmpty()) {
            return null;
        }

        return static::create([
            'wiki_seite_id' => $seite->id,
            'user_id' => $user?->id,
            'titel' => $seite->titel,
            'abschnitte' => $abschnitte->map(fn (WikiAbschnitt $a) => [
                'ueberschrift' => $a->ueberschrift,
                'inhalt' => $a->inhalt,
                'rollen' => $a->rollen->pluck('role_id')->all(),
            ])->all(),
        ]);
    }
}
