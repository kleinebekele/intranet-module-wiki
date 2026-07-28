<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $seite->istHilfe() ? 'Hilfe' : 'Wiki' }} · {{ $seite->titel }}
        </h2>
    </x-slot>

    @include('wiki::partials.inhalt-stil')

    <div class="py-6">
        <div class="w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-lg px-4 py-3">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Kopfleiste: Herkunft, Werkzeuge --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-4 sm:p-6 flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-gray-500">
                    @if ($seite->kategorie)
                        <span class="inline-flex items-center gap-1">
                            <x-module-icon name="folder" class="text-base text-gray-400" />
                            {{ $seite->kategorie }}
                        </span>
                        <span class="mx-2 text-gray-300">|</span>
                    @endif
                    @if ($seite->autor)
                        {{ $seite->autor->name }},
                    @endif
                    zuletzt geändert am {{ $seite->updated_at?->format('d.m.Y H:i') }}
                    @if ($seite->ausDatei())
                        <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
                              title="Der Text stammt aus dem Paket {{ $seite->hilfe_quelle }} und wird mit ihm aktualisiert.">
                            aus {{ $seite->hilfe_quelle }}{{ $seite->angepasst ? ', hier angepasst' : '' }}
                        </span>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if ($darfBearbeiten)
                        {{-- Vorschau: Was sieht jemand mit genau dieser einen Rolle? --}}
                        <form method="GET" action="{{ route('module.wiki.show', $seite->slug) }}" class="flex items-center gap-2">
                            <label for="als" class="text-xs text-gray-500">Ansehen als</label>
                            <select id="als" name="als" onchange="this.form.submit()"
                                    class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">alles anzeigen</option>
                                @foreach ($alleRollen as $rolle)
                                    <option value="{{ $rolle->role_id }}" @selected($vorschauRolle === $rolle->role_id)>
                                        {{ $rolle->name }}
                                    </option>
                                @endforeach
                            </select>
                        </form>

                        <a href="{{ route('module.wiki.edit', $seite->slug) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <x-module-icon name="edit" class="text-base" />
                            Bearbeiten
                        </a>

                        <a href="{{ route('module.wiki.verlauf', $seite->slug) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            <x-module-icon name="history" class="text-base" />
                            Verlauf
                        </a>
                    @endif

                    @if ($darfVerwalten && $seite->ausDatei())
                        <form method="POST" action="{{ route('module.wiki.zuruecksetzen', $seite->slug) }}"
                              onsubmit="return confirm('Diese Seite auf den Stand des Pakets zurücksetzen? Örtliche Änderungen gehen dabei verloren.');">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                <x-module-icon name="back" class="text-base" />
                                Auf Paket-Stand
                            </button>
                        </form>
                    @endif

                    @if ($darfVerwalten)
                        <form method="POST" action="{{ route('module.wiki.destroy', $seite->slug) }}"
                              onsubmit="return confirm('Diese Seite endgültig löschen?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                                <x-module-icon name="trash" class="text-base" />
                                Löschen
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            @if ($vorschauRolle)
                <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg px-4 py-3">
                    Vorschau: Sie sehen die Seite so, wie sie jemand mit der Rolle
                    <strong>{{ $alleRollen->firstWhere('role_id', $vorschauRolle)?->name ?? $vorschauRolle }}</strong> sieht.
                    <a href="{{ route('module.wiki.show', $seite->slug) }}" class="underline">Zurück zur vollen Ansicht</a>
                </div>
            @endif

            {{-- Der eigentliche Text --}}
            <div class="bg-white shadow-sm sm:rounded-lg divide-y divide-gray-100">
                @php
                    $gezeigt = 0;
                @endphp
                @foreach ($seite->abschnitte as $abschnitt)
                    @php
                        // Kurzform @php(...) hier bewusst nicht: Sie verschluckt sich an
                        // verschachtelten Klammern und laesst den Rest der Datei als Rohtext stehen.
                        $sichtbar = $abschnitt->sichtbarFuer($rollen);
                    @endphp

                    @continue(! $sichtbar && ! $alleZeigen)

                    @php
                        $gezeigt++;
                    @endphp

                    <section class="px-4 sm:px-6 py-5 {{ $sichtbar ? '' : 'bg-gray-50' }}">
                        @if ($abschnitt->ueberschrift)
                            <h3 class="text-base font-semibold text-gray-800 mb-2">{{ $abschnitt->ueberschrift }}</h3>
                        @endif

                        @if ($darfBearbeiten && $abschnitt->istEingeschraenkt())
                            <div class="mb-2 flex flex-wrap items-center gap-1.5">
                                <x-module-icon name="tag" class="text-sm {{ $sichtbar ? 'text-gray-400' : 'text-amber-500' }}" />
                                @forelse ($abschnitt->rollen as $rolle)
                                    <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                                        {{ $rolle->name }}
                                    </span>
                                @empty
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800"
                                          title="Der Abschnitt war für Rollen gedacht, die es in dieser Instanz nicht gibt. Er bleibt deshalb verborgen.">
                                        keine passende Rolle in dieser Instanz
                                    </span>
                                @endforelse
                                @unless ($sichtbar)
                                    <span class="text-xs text-amber-700">– für Sie ausgeblendet</span>
                                @endunless
                            </div>
                        @endif

                        <div class="wiki-inhalt text-gray-700">
                            {!! $abschnitt->html() !!}
                        </div>
                    </section>
                @endforeach

                @if ($gezeigt === 0)
                    <div class="px-4 sm:px-6 py-8 text-center text-sm text-gray-500">
                        Für diese Rolle ist auf dieser Seite nichts freigegeben.
                    </div>
                @endif
            </div>

            <div>
                <a href="{{ route($seite->istHilfe() ? 'module.wiki.hilfe' : 'module.wiki.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                    <x-module-icon name="back" class="text-base" />
                    Zurück zur Übersicht
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
