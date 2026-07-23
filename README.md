# Solarwetter für IP-Symcon

Native HTML-Kachel zur Darstellung einer stündlichen PV-Prognose in IP-Symcon ab Version 7.1.

## Funktionen

- erwarteter PV-Ertrag der nächsten 24 Stunden
- erwartete Leistungsspitze
- Solarqualität
- Balkendiagramm der nächsten zwölf Stunden
- konfigurierbare Anlagenleistung, Performance Ratio und Wechselrichtergrenze
- keine festen Symcon-Objekt-IDs
- automatische Ergebnisvariablen für PV-Ertrag, Leistungsspitze, Solarqualität, Datenstatus, Aktualisierungszeit und Fehler
- wählbarer Prognosezeitraum: nächste 24 Stunden oder kompletter Folgetag von 00:00 bis 24:00

## Datenformat

Die ausgewählte JSON-Variable muss eine Liste stündlicher Datensätze mit mindestens `timestamp`, `solarRadiation` und optional `solarIndex` sowie `condition` enthalten.

## Darstellung

Für eine vollständige Darstellung wird eine Kachelgröße von mindestens 3 × 3 Feldern empfohlen.

## Lizenz

MIT
