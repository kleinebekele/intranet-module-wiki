{{-- Hilfe im Fenster: Der „?"-Knopf des Cores lädt diese Fassung per fetch und
     setzt sie in sein Modal (Kopfzeile X-Hilfe-Modal). Kein Seitenrahmen, kein
     Rücksprung – man schließt das Fenster einfach. Wer bearbeiten darf, kommt
     über den Link unten auf die echte Seite, dort mit „Zurück zur Seite". --}}
@include('wiki::partials.inhalt-stil')

<div class="space-y-4">
    <h2 class="text-lg font-semibold text-gray-900">{{ $seite->titel }}</h2>

    @include('wiki::partials.abschnitte')

    @if ($darfBearbeiten)
        <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-3 text-sm">
            <span class="text-gray-500">zuletzt geändert am {{ $seite->updated_at?->format('d.m.Y H:i') }}</span>
            <a href="{{ $seiteUrl }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 font-medium text-gray-700 hover:bg-gray-50">
                <x-module-icon name="edit" class="text-base" />
                Im Wiki öffnen und bearbeiten
            </a>
        </div>
    @endif
</div>
