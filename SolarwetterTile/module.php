<?php

declare(strict_types=1);

class SolarwetterKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Legacy properties remain registered so existing instances can update without data loss.
        $this->RegisterPropertyInteger('ForecastJSONID', 0);
        $this->RegisterPropertyInteger('SourceTimeID', 0);
        $this->RegisterPropertyInteger('ValidID', 0);
        $this->RegisterPropertyInteger('ErrorID', 0);

        $this->RegisterPropertyFloat('Latitude', 53.277255);
        $this->RegisterPropertyFloat('Longitude', 7.960581);
        $this->RegisterPropertyFloat('RoofTilt', 42.0);
        $this->RegisterPropertyFloat('RoofAzimuth', 45.0);
        $this->RegisterPropertyString('Timezone', 'Europe/Berlin');
        $this->RegisterPropertyInteger('UpdateInterval', 900);
        $this->RegisterPropertyBoolean('UseTomorrow', false);
        $this->RegisterPropertyBoolean('CalendarDayTomorrow', true);
        $this->RegisterPropertyFloat('PVPeakPower', 10.53);
        $this->RegisterPropertyFloat('PerformanceRatio', 0.85);
        $this->RegisterPropertyFloat('InverterLimit', 8.0);

        $this->RegisterAttributeInteger('LastSuccess', 0);

        $this->RegisterVariableFloat('ForecastEnergy', 'PV-Ertrag gewählter Zeitraum', '', 10);
        $this->RegisterVariableFloat('ForecastPeak', 'Erwartete Leistungsspitze', '', 20);
        $this->RegisterVariableInteger('SolarQuality', 'Solarqualität', '~Intensity.100', 30);
        $this->RegisterVariableBoolean('DataValid', 'Prognosedaten gültig', '~Switch', 40);
        $this->RegisterVariableInteger('LastUpdate', 'Letzte Aktualisierung', '~UnixTimestamp', 50);
        $this->RegisterVariableString('LastError', 'Fehlermeldung', '', 60);
        $this->RegisterVariableString('ForecastJSON', 'Open-Meteo Prognose JSON', '', 70);

        $this->RegisterTimer('DataUpdate', 0, 'SWK_UpdateData($_IPS["TARGET"]);');
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $interval = max(300, $this->ReadPropertyInteger('UpdateInterval'));
        $this->SetTimerInterval('DataUpdate', $interval * 1000);
        $this->SetStatus(102);

        if ($this->GetValue('ForecastJSON') === '') {
            $this->UpdateData();
        } else {
            $this->UpdateTile();
        }
    }

    public function GetVisualizationTile()
    {
        return str_replace('__INITIAL_STATE__', $this->StateJSON(), file_get_contents(__DIR__ . '/module.html'));
    }

    public function UpdateData()
    {
        $lastError = '';
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $forecast = $this->FetchForecast();
                $this->SetValue('ForecastJSON', (string) json_encode(
                    $forecast,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ));
                $this->WriteAttributeInteger('LastSuccess', time());
                $this->SetValue('LastError', '');
                $this->SetStatus(102);
                $this->UpdateTile();
                return;
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                $this->SendDebug('Open-Meteo Versuch ' . $attempt, $lastError, 0);
                if ($attempt < 3) {
                    IPS_Sleep($attempt * 1000);
                }
            }
        }

        $this->SetValue('LastError', $lastError);
        $fresh = $this->WriteValidity();
        $this->SetStatus($fresh ? 102 : 201);
        $this->UpdateTile();
    }

    public function UpdateTile()
    {
        $state = $this->GetState();
        $this->SetValue('ForecastEnergy', (float) $state['energy']);
        $this->SetValue('ForecastPeak', (float) $state['peak']);
        $this->SetValue('SolarQuality', (int) $state['quality']);
        $this->SetValue('DataValid', (bool) $state['valid']);
        $this->SetValue('LastUpdate', time());
        $this->SetStatus($state['valid'] ? 102 : 201);
        $this->UpdateVisualizationValue((string) json_encode(
            $state,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        ));
    }

    private function FetchForecast(): array
    {
        $latitude = $this->ReadPropertyFloat('Latitude');
        $longitude = $this->ReadPropertyFloat('Longitude');
        $tilt = max(0.0, min(90.0, $this->ReadPropertyFloat('RoofTilt')));
        $azimuth = max(-180.0, min(180.0, $this->ReadPropertyFloat('RoofAzimuth')));
        $timezone = $this->ValidTimezone();
        $hourly = implode(',', [
            'temperature_2m',
            'weather_code',
            'is_day',
            'cloud_cover',
            'precipitation_probability',
            'precipitation',
            'shortwave_radiation',
            'direct_radiation',
            'diffuse_radiation',
            'global_tilted_irradiance'
        ]);
        $url = 'https://api.open-meteo.com/v1/forecast'
            . '?latitude=' . rawurlencode((string) $latitude)
            . '&longitude=' . rawurlencode((string) $longitude)
            . '&hourly=' . rawurlencode($hourly)
            . '&tilt=' . rawurlencode((string) $tilt)
            . '&azimuth=' . rawurlencode((string) $azimuth)
            . '&forecast_days=3'
            . '&timezone=' . rawurlencode($timezone);

        $raw = $this->HttpGet($url);
        $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $hourlyData = $json['hourly'] ?? null;
        if (!is_array($hourlyData) || !is_array($hourlyData['time'] ?? null)) {
            throw new RuntimeException('Open-Meteo liefert keine stündliche Prognose');
        }

        $result = [];
        $zone = new DateTimeZone($timezone);
        foreach ($hourlyData['time'] as $index => $timeText) {
            $date = new DateTimeImmutable((string) $timeText, $zone);
            $radiation = max(0.0, (float) ($hourlyData['global_tilted_irradiance'][$index] ?? 0));
            $code = (int) ($hourlyData['weather_code'][$index] ?? -1);
            $result[] = [
                'time'           => $date->format(DateTimeInterface::ATOM),
                'timestamp'      => $date->getTimestamp(),
                'temperature'    => (float) ($hourlyData['temperature_2m'][$index] ?? 0),
                'weatherCode'    => $code,
                'isDay'          => ((int) ($hourlyData['is_day'][$index] ?? 0)) === 1,
                'cloudCover'     => (int) ($hourlyData['cloud_cover'][$index] ?? 0),
                'precipitation'  => (float) ($hourlyData['precipitation'][$index] ?? 0),
                'condition'      => $this->WeatherLabel($code),
                'solarRadiation' => round($radiation, 1),
                'solarIndex'     => min(100, (int) round($radiation / 8))
            ];
        }
        if (count($result) < 48) {
            throw new RuntimeException('Open-Meteo liefert weniger als 48 Prognosestunden');
        }
        return $result;
    }

    private function HttpGet(string $url): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('HTTP-Verbindung konnte nicht initialisiert werden');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_USERAGENT => 'IP-Symcon-Solarwetter/2.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: de']
        ]);
        $data = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($data === false || trim((string) $data) === '' || $status < 200 || $status >= 300) {
            throw new RuntimeException('Open-Meteo HTTP ' . $status . ($error !== '' ? ': ' . $error : ''));
        }
        return (string) $data;
    }

    private function GetState(): array
    {
        $raw = json_decode((string) $this->GetValue('ForecastJSON'), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $timezone = $this->ValidTimezone();
        $zone = new DateTimeZone($timezone);
        $now = new DateTimeImmutable('now', $zone);
        $periodLabel = 'Nächste 24 Stunden';

        if ($this->ReadPropertyBoolean('UseTomorrow')) {
            $tomorrow = $this->ReadPropertyBoolean('CalendarDayTomorrow');
            $start = $now->setTime(0, 0)->modify($tomorrow ? '+1 day' : 'today');
            $end = $start->modify('+1 day');
            $raw = array_values(array_filter($raw, static function ($item) use ($start, $end): bool {
                $timestamp = is_array($item) ? (int) ($item['timestamp'] ?? 0) : 0;
                return $timestamp >= $start->getTimestamp() && $timestamp < $end->getTimestamp();
            }));
            $periodLabel = $tomorrow ? 'Morgen · 00–24 Uhr' : 'Heute · 00–24 Uhr';
        } else {
            $start = $now->setTime((int) $now->format('H'), 0);
            $raw = array_values(array_filter($raw, static function ($item) use ($start): bool {
                return is_array($item) && (int) ($item['timestamp'] ?? 0) >= $start->getTimestamp();
            }));
            $raw = array_slice($raw, 0, 24);
        }

        $pvPeak = max(0.1, $this->ReadPropertyFloat('PVPeakPower'));
        $ratio = max(0.1, min(1.0, $this->ReadPropertyFloat('PerformanceRatio')));
        $limit = max(0.1, $this->ReadPropertyFloat('InverterLimit'));
        $hours = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $radiation = max(0, (float) ($item['solarRadiation'] ?? 0));
            $power = min($limit, $radiation / 1000 * $pvPeak * $ratio);
            $hours[] = [
                'timestamp'      => (int) ($item['timestamp'] ?? 0),
                'radiation'      => round($radiation),
                'index'          => max(0, min(100, (int) ($item['solarIndex'] ?? 0))),
                'condition'      => (string) ($item['condition'] ?? ''),
                'expectedPower'  => round($power, 2),
                'expectedEnergy' => round($power, 2)
            ];
        }

        $lastSuccess = $this->ReadAttributeInteger('LastSuccess');
        $valid = $lastSuccess > 0 && time() - $lastSuccess <= 3600 && count($hours) > 0;
        $energy = array_sum(array_column($hours, 'expectedEnergy'));
        return [
            'valid'       => $valid,
            'sourceTime'  => $lastSuccess,
            'error'       => (string) $this->GetValue('LastError'),
            'energy'      => round($energy, 2),
            'peak'        => $hours ? round(max(array_column($hours, 'expectedPower')), 2) : 0,
            'quality'     => $hours ? max(array_column($hours, 'index')) : 0,
            'periodLabel' => $periodLabel,
            'hours'       => array_slice($hours, 0, 24)
        ];
    }

    private function WriteValidity(): bool
    {
        $valid = $this->ReadAttributeInteger('LastSuccess') > 0
            && time() - $this->ReadAttributeInteger('LastSuccess') <= 3600;
        $this->SetValue('DataValid', $valid);
        return $valid;
    }

    private function ValidTimezone(): string
    {
        $timezone = trim($this->ReadPropertyString('Timezone'));
        try {
            new DateTimeZone($timezone);
            return $timezone;
        } catch (Throwable $e) {
            return 'Europe/Berlin';
        }
    }

    private function WeatherLabel(int $code): string
    {
        $labels = [
            0 => 'Klar', 1 => 'Überwiegend klar', 2 => 'Teilweise bewölkt', 3 => 'Bedeckt',
            45 => 'Nebel', 48 => 'Reifnebel', 51 => 'Leichter Nieselregen', 53 => 'Nieselregen',
            55 => 'Starker Nieselregen', 61 => 'Leichter Regen', 63 => 'Regen', 65 => 'Starker Regen',
            71 => 'Leichter Schneefall', 73 => 'Schneefall', 75 => 'Starker Schneefall',
            80 => 'Leichte Regenschauer', 81 => 'Regenschauer', 82 => 'Starke Regenschauer',
            85 => 'Leichte Schneeschauer', 86 => 'Schneeschauer', 95 => 'Gewitter',
            96 => 'Gewitter mit Hagel', 99 => 'Starkes Gewitter mit Hagel'
        ];
        return $labels[$code] ?? ('WMO-Code ' . $code);
    }

    private function StateJSON(): string
    {
        return (string) json_encode(
            $this->GetState(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }
}
