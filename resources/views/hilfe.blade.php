<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Hilfe und HowTos</h2>
    </x-slot>

    <div class="py-6">
        <div class="w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6">
                <p class="text-sm text-gray-500">
                    Anleitungen zu den Funktionen dieses Intranets. Was Sie hier sehen, richtet sich
                    nach Ihren Rollen – einzelne Absätze einer Anleitung können für bestimmte
                    Personengruppen bestimmt sein. Auf vielen Seiten führt der
                    <x-module-icon name="help" class="text-base text-gray-400" />-Knopf in der Kopfzeile direkt
                    zur passenden Anleitung.
                </p>
            </div>

            @forelse ($bereiche as $bereich => $seiten)
                <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="px-4 sm:px-6 py-3 border-b border-gray-200 bg-gray-50 flex items-center gap-2">
                        <x-module-icon name="book-content" class="text-base text-gray-400" />
                        <h3 class="text-sm font-semibold text-gray-700">{{ $bereich }}</h3>
                        <span class="text-xs text-gray-400">{{ $seiten->count() }}</span>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($seiten as $seite)
                            <li class="px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-2">
                                <a href="{{ route('module.wiki.show', $seite->slug) }}"
                                   class="font-medium text-indigo-700 hover:underline">
                                    {{ $seite->titel }}
                                </a>
                                <div class="flex items-center gap-2 text-xs text-gray-400">
                                    @if ($seite->hilfe_fuer_route)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700"
                                              title="Diese Anleitung hängt am Fragezeichen-Knopf der Route {{ $seite->hilfe_fuer_route }}">
                                            Kontexthilfe
                                        </span>
                                    @endif
                                    @if ($darfBearbeiten && $seite->angepasst)
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 font-medium text-amber-700"
                                              title="Wurde hier bearbeitet und wird beim nächsten Abgleich nicht überschrieben.">
                                            angepasst
                                        </span>
                                    @endif
                                    <span>{{ $seite->updated_at?->format('d.m.Y') }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <div class="bg-white shadow-sm sm:rounded-lg p-8 text-center text-sm text-gray-500">
                    Noch keine Hilfetexte vorhanden.
                    @if ($darfBearbeiten)
                        <br>
                        Die Anleitungen der installierten Pakete holt
                        <code class="bg-gray-100 rounded px-1 py-0.5">php artisan wiki:hilfe-sync</code> herein.
                    @endif
                </div>
            @endforelse

            <div>
                <a href="{{ route('module.wiki.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <x-module-icon name="back" class="text-base" />
                    Zum Wiki
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
