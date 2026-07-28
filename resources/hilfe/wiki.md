---
titel: Das Wiki benutzen
route: module.wiki.index
kategorie: Wiki
position: 1
---

Im Wiki stehen zwei Sorten Text: **Beiträge**, die hier im Haus geschrieben werden, und
**Anleitungen** zu den Funktionen des Intranets. Gelesen werden beide gleich.

## Suchen

Das Suchfeld oben durchsucht Titel **und** Inhalt. Gefunden wird nur, was Sie auch lesen
dürfen – Absätze, die für andere Rollen bestimmt sind, tauchen weder im Text noch in den
Suchtreffern auf.

## Warum Ihre Anleitung kürzer sein kann als die eines Kollegen

Jeder Absatz einer Seite kann auf bestimmte Rollen begrenzt werden. In einer gemeinsamen
Anleitung steht der Verwaltungsteil dann nur bei denen, die ihn brauchen. Sie sehen nicht,
dass etwas fehlt – und das ist Absicht: Der Text wird gar nicht erst ausgeliefert.

## Anleitungen finden Sie auch unterwegs

Auf vielen Seiten des Intranets steht oben rechts ein Fragezeichen. Es erscheint nur, wenn
es zu genau dieser Seite eine Anleitung gibt, und führt direkt dorthin.

## Beiträge schreiben

rollen: wiki-moderator, wiki-admin

Sie dürfen Seiten anlegen und bearbeiten. Eine Seite besteht aus **Abschnitten** – jeder
Abschnitt hat eine optionale Überschrift, einen Text und seine eigenen Rollen-Tags.

Der Text ist Markdown:

```
## Zwischenüberschrift
**fett**, *kursiv*, `Code`
- Aufzählung
[Beschriftung](https://adresse)
```

HTML wird beim Anzeigen verworfen – ein Wiki-Beitrag soll keine Tür für fremden Code sein.

## Rollen-Tags richtig setzen

rollen: wiki-moderator, wiki-admin

Die Regel ist kurz:

- **Keine Rolle angehakt** heißt: für alle sichtbar. Sie müssen also nicht jeden Absatz
  taggen, sondern nur die, die wirklich begrenzt gehören.
- **Mehrere Rollen angehakt** heißt: Wer **eine** davon hat, sieht den Absatz.

Prüfen Sie das Ergebnis mit **Ansehen als** oben auf der Seite. Solange Sie selbst
bearbeiten dürfen, sehen Sie nämlich immer alles – ausgeblendete Abschnitte sind nur grau
hinterlegt und mit ihren Rollen beschriftet. Die Vorschau zeigt die Seite so, wie sie
wirklich bei jemandem ankommt.

## Verlauf

rollen: wiki-moderator, wiki-admin

Bei jedem Speichern wandert der vorherige Stand in den Verlauf, mit Datum und Name. Der
oberste Eintrag ist also die Fassung direkt vor der aktuellen.

## Anleitungen aus den Paketen

rollen: wiki-admin

Anleitungen zu Modulen liegen als Datei im jeweiligen Paket und werden mit ihm zusammen
aktualisiert. Sie kommen mit

```
php artisan wiki:hilfe-sync
```

ins Wiki – das gehört in den Deploy-Ablauf, direkt hinter `modules:sync`.

In der Datei stehen die Rollen-Tags an zwei möglichen Stellen: im Kopf gelten sie für die
ganze Anleitung, unter einer Überschrift nur für diesen Abschnitt. Eine reine
Verwaltungs-Anleitung bekommt die Angabe deshalb **einmal oben** – die Zeile unter jeder
Überschrift zu wiederholen ist der sichere Weg, eine zu vergessen.

Bearbeiten Sie eine solche Seite hier im Backend, gilt sie ab dann als **angepasst** und der
Abgleich fasst sie nicht mehr an. Ihre Arbeit wird also nicht vom nächsten Update
überschrieben. Über **Auf Paket-Stand** holen Sie bewusst wieder die Fassung aus dem Paket.

Verschwindet die Datei zu einer Seite, wird das beim Abgleich gemeldet, aber nichts
gelöscht – es könnte inzwischen jemand daran gearbeitet haben.
