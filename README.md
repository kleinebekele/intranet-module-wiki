# Wiki-Modul

Wiki und Hilfe-System für die [modulare Intranet-Plattform](https://github.com/kleinebekele/intranet-core).

Zwei Dinge in einem Modul, weil sie dieselbe Bauart teilen:

- **Beiträge**, die im Haus geschrieben werden (Anlegen, Bearbeiten, Verlauf, Suche).
- **Anleitungen** zu den Funktionen des Intranets, die als Datei im jeweiligen Paket liegen
  und mit ihm zusammen aktualisiert werden.

Der Kern von beidem: Eine Seite besteht aus **Abschnitten**, und jeder Abschnitt trägt seine
eigenen **Rollen-Tags**. So steht in einer gemeinsamen Anleitung der Verwaltungsteil nur bei
denen, die ihn brauchen — und der Rest wird gar nicht erst ausgeliefert.

## Installation

```bash
composer require do1emu/module-wiki
php artisan migrate
php artisan modules:sync
php artisan wiki:hilfe-sync
```

Danach unter **Verwaltung → Module → Wiki** die Rollen je Unterpunkt setzen. Frisch
synchronisierte Menüpunkte sind zunächst nur für Administratoren sichtbar (der sichere
Standard des Cores). Für ein Wiki, das alle lesen dürfen, bekommen `Übersicht` und
`Hilfe und HowTos` die Rolle `user`; `Beitrag anlegen` bekommt `wiki-moderator` und
`wiki-admin`.

Voraussetzung: Core mit `App\Support\Hilfe` (für den Fragezeichen-Knopf in der Kopfzeile).
Fehlt die Klasse, fällt nur die Kontexthilfe weg.

## Rollen

Das Modul bringt zwei Rollen mit (keine System-Rollen — sie verschwinden mit
`modules:uninstall --mit-daten` wieder):

| Rolle | darf |
|---|---|
| `wiki-moderator` | Seiten anlegen und bearbeiten, Rollen-Tags vergeben, Verlauf sehen |
| `wiki-admin` | zusätzlich löschen und Datei-Anleitungen auf den Paket-Stand zurücksetzen |

Eine Rolle fürs **Lesen** gibt es bewusst nicht — das regelt der Core über die Rollen am
Menüpunkt.

## Sichtbarkeit eines Abschnitts

1. Keine Tags → für alle sichtbar.
2. Tags gesetzt → sichtbar, wenn der Benutzer **eine** davon hat.
3. Tags gefordert, aber keine davon existiert in dieser Instanz → **nicht** sichtbar.

Fall 3 ist der wichtige: Eine Anleitung aus einem Paket kann eine Rolle nennen, die es hier
nicht gibt (`teacher` in einer Firma). Ohne diese Regel würde ein für Lehrer gedachter Absatz
still für alle aufgehen, weil "keine Tags" ja "für alle" bedeutet.

Wer bearbeiten darf, sieht auf einer Seite **immer alles** — ausgeblendete Abschnitte grau
hinterlegt und mit ihren Rollen beschriftet. Was ein anderer wirklich sieht, zeigt
**Ansehen als** oben auf der Seite.

## Anleitungen als Datei

Jedes Paket darf `resources/hilfe/*.md` mitbringen, der Core selbst ebenso. Aufbau:

```markdown
---
titel: Mailvorlagen bearbeiten
route: admin.mailvorlagen.index
kategorie: Verwaltung
position: 13
rollen: admin
---

Einleitung ohne Überschrift (optional).

## Vorschau und Testmail
rollen: admin, wiki-admin

Markdown-Text des Abschnitts …
```

- `route` verknüpft die Seite mit dem Fragezeichen-Knopf: Auf genau dieser Route erscheint er
  in der Kopfzeile und führt hierher. Achtung: Nur Routen, die auch **gerendert** werden —
  eine Route, die bloß weiterleitet, zeigt nie einen Knopf.
- `rollen` gibt es auf **zwei Ebenen**:
  - **im Kopf** — Vorgabe für die ganze Datei. Der richtige Ort für eine reine
    Verwaltungs-Anleitung.
  - **unter einer Überschrift** — gilt für diesen Abschnitt und sticht die Vorgabe. Eine
    leere Zeile `rollen:` hebt die Vorgabe auf und macht den Abschnitt für alle sichtbar.

  Ohne die Kopf-Ebene müsste die Zeile unter *jeder* Überschrift stehen — und ein einziges
  vergessenes Vorkommen macht den Abschnitt lautlos öffentlich.
- `##`-Zeilen innerhalb eines Code-Blocks (```) sind Beispieltext, keine Überschriften.

`php artisan wiki:hilfe-sync` übernimmt die Dateien in die Datenbank. Nach dem Vorbild der
Mailvorlagen gilt: Der Standard liegt im Paket, die Instanz speichert nur die Abweichung. Eine
im Backend bearbeitete Seite ist `angepasst` und wird vom Abgleich nicht mehr angefasst;
`--erzwingen` setzt sie zurück, `--pruefen` zeigt nur, was passieren würde.

Verwaiste Seiten (Datei weg) werden gemeldet, aber **nie** automatisch gelöscht.

## Kontexthilfe im Core

Das Modul meldet sich beim Core an:

```php
Hilfe::anbieten(fn (string $route, ?User $user) => WikiSeite::urlFuerRoute($route, $user));
```

Der Core fragt bei jedem Seitenaufruf, ob es zur aktuellen Route eine Anleitung gibt. Ohne
Anbieter (Wiki nicht installiert) erscheint der Knopf gar nicht erst.

## Inhalte sind Markdown

Gespeichert wird die Markdown-Quelle, gerendert wird beim Anzeigen — mit verworfenem
Roh-HTML und ohne unsichere Links. Ein Format für Backend-Eingabe und Paket-Datei, und in
`git diff` bleibt eine Änderung lesbar.
