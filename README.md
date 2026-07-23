# Solarwetter für IP-Symcon

Native HTML-Kachel zur Darstellung einer stündlichen PV-Prognose in IP-Symcon ab Version 7.1.

## Funktionen

- erwarteter PV-Ertrag der nächsten 24 Stunden
- erwartete Leistungsspitze
- Solarqualität
- Balkendiagramm der nächsten zwölf Stunden
- konfigurierbare Anlagenleistung, Performance Ratio und Wechselrichtergrenze
- keine festen Symcon-Objekt-IDs

## Datenformat

Die ausgewählte JSON-Variable muss eine Liste stündlicher Datensätze mit mindestens `timestamp`, `solarRadiation` und optional `solarIndex` sowie `condition` enthalten.

## Darstellung

Für eine vollständige Darstellung wird eine Kachelgröße von mindestens 3 × 3 Feldern empfohlen.

## Lizenz

MIT
