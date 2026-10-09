{{-- Der Text einer Wiki-Seite, Abschnitt für Abschnitt nach Rollen gefiltert.
     Gebraucht von der Seite selbst und vom Hilfe-Fenster (modal.blade.php).
     Erwartet: $seite, $rollen, $alleZeigen, $darfBearbeiten. --}}
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
