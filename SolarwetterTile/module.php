<?php

declare(strict_types=1);

class SolarwetterKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();
        $this->RegisterPropertyInteger('ForecastJSONID', 0);
        $this->RegisterPropertyInteger('SourceTimeID', 0);
        $this->RegisterPropertyInteger('ValidID', 0);
        $this->RegisterPropertyInteger('ErrorID', 0);
        $this->RegisterPropertyFloat('PVPeakPower', 10.53);
        $this->RegisterPropertyFloat('PerformanceRatio', 0.85);
        $this->RegisterPropertyFloat('InverterLimit', 8.0);
        $this->RegisterTimer('TileUpdate', 60000, 'SWK_UpdateTile($_IPS["TARGET"]);');
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $complete = true;
        foreach (['ForecastJSONID', 'SourceTimeID', 'ValidID', 'ErrorID'] as $property) {
            $id = $this->ReadPropertyInteger($property);
            if ($id <= 0 || !IPS_ObjectExists($id)) {
                $complete = false;
            }
        }
        $this->SetTimerInterval('TileUpdate', $complete ? 60000 : 0);
        $this->SetStatus($complete ? 102 : 104);
        $this->UpdateTile();
    }

    public function GetVisualizationTile()
    {
        return str_replace('__INITIAL_STATE__', $this->StateJSON(), file_get_contents(__DIR__ . '/module.html'));
    }

    public function UpdateTile()
    {
        $state = $this->GetState();
        $this->SetStatus($state['valid'] ? 102 : 201);
        $this->UpdateVisualizationValue((string) json_encode(
            $state,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        ));
    }

    private function ReadValue(string $property, $fallback)
    {
        $id = $this->ReadPropertyInteger($property);
        if ($id <= 0 || !IPS_ObjectExists($id)) {
            return $fallback;
        }
        try {
            return GetValue($id);
        } catch (Throwable $e) {
            return $fallback;
        }
    }

    private function GetState(): array
    {
        $raw = json_decode((string) $this->ReadValue('ForecastJSONID', '[]'), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        $pvPeak = max(0.1, $this->ReadPropertyFloat('PVPeakPower'));
        $ratio = max(0.1, min(1.0, $this->ReadPropertyFloat('PerformanceRatio')));
        $limit = max(0.1, $this->ReadPropertyFloat('InverterLimit'));
        $hours = [];
        foreach (array_slice($raw, 0, 24) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $radiation = max(0, (int) round((float) ($item['solarRadiation'] ?? 0)));
            $power = min($limit, $radiation / 1000 * $pvPeak * $ratio);
            $hours[] = [
                'timestamp'      => (int) ($item['timestamp'] ?? 0),
                'radiation'      => $radiation,
                'index'          => max(0, min(100, (int) ($item['solarIndex'] ?? 0))),
                'condition'      => (string) ($item['condition'] ?? ''),
                'expectedPower'  => round($power, 2),
                'expectedEnergy' => round($power, 2)
            ];
        }
        $source = (int) $this->ReadValue('SourceTimeID', 0);
        $energy = array_sum(array_column($hours, 'expectedEnergy'));
        return [
            'valid'      => (bool) $this->ReadValue('ValidID', false) && $source > 0 && time() - $source <= 3600,
            'sourceTime' => $source,
            'error'      => (string) $this->ReadValue('ErrorID', ''),
            'energy'     => round($energy, 2),
            'peak'       => $hours ? round(max(array_column($hours, 'expectedPower')), 2) : 0,
            'quality'    => $hours ? max(array_column($hours, 'index')) : 0,
            'hours'      => array_slice($hours, 0, 12)
        ];
    }

    private function StateJSON(): string
    {
        return (string) json_encode(
            $this->GetState(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        );
    }
}
