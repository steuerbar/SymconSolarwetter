# Solarwetter für IP-Symcon

Eigenständiges IP-Symcon-Modul mit nativer HTML-Kachel zur Berechnung des erwarteten PV-Ertrags aus der Open-Meteo-Solarprognose.

## Voraussetzungen

- IP-Symcon ab Version 7.1
- Internetzugriff auf `api.open-meteo.com`
- keine zusätzlichen Skripte oder Datenvariablen erforderlich

## Konfiguration

- Breitengrad und Längengrad
- Dachneigung
- Open-Meteo-Azimut von -180° bis 180°; 0° entspricht Süden
- Zeitzone
- Abrufintervall, mindestens 300 Sekunden
- installierte PV-Leistung in kWp
- Performance Ratio
- Wechselrichtergrenze in kW

## Prognosezeiträume

- nächste 24 Stunden
- aktueller Kalendertag von 00:00 bis 24:00 Uhr
- Folgetag von 00:00 bis 24:00 Uhr

## Funktionen

- eigenständiger Abruf einer dreitägigen Open-Meteo-Prognose
- bis zu drei HTTP-Versuche pro Aktualisierung
- letzte gültige Prognose bleibt bei kurzen Ausfällen erhalten
- erwarteter PV-Ertrag, Leistungsspitze und Solarqualität
- kompaktes Balkendiagramm aller 24 Stunden des gewählten Zeitraums
- automatische Status- und Ergebnisvariablen
- keine festen Symcon-Objekt-IDs

## Berechnung

`Stundenenergie kWh = min(Wechselrichtergrenze; Globalstrahlung auf PV-Ebene / 1000 × kWp × Performance Ratio)`

Die Open-Meteo-Strahlungsprognose wird mit Dachneigung und Ausrichtung direkt für die PV-Ebene abgerufen.

## Darstellung

Für eine vollständige Darstellung wird eine Kachelgröße von mindestens 3 × 3 Feldern empfohlen.

## Datenschutz und externe Dienste

Das Modul überträgt Standortkoordinaten, Dachneigung, Ausrichtung und Zeitzone an Open-Meteo. Es werden keine Zugangsdaten benötigt.

## Lizenz

MIT
