<?php

namespace App\Support;

use InvalidArgumentException;

class TtvTypes
{
    public static function all(): array
    {
        return [
            'suhu' => ['label' => 'Suhu', 'column' => 'suhu_tubuh', 'table' => 'satu_sehat_observationttvsuhu', 'code' => '8310-5', 'display' => 'Body temperature', 'unit' => 'degree Celsius', 'unit_code' => 'Cel'],
            'respirasi' => ['label' => 'Respirasi', 'column' => 'respirasi', 'table' => 'satu_sehat_observationttvrespirasi', 'code' => '9279-1', 'display' => 'Respiratory rate', 'unit' => 'breaths/minute', 'unit_code' => '/min'],
            'nadi' => ['label' => 'Nadi', 'column' => 'nadi', 'table' => 'satu_sehat_observationttvnadi', 'code' => '8867-4', 'display' => 'Heart rate', 'unit' => 'breaths/minute', 'unit_code' => '/min'],
            'spo2' => ['label' => 'SpO2', 'column' => 'spo2', 'table' => 'satu_sehat_observationttvspo2', 'code' => '59408-5', 'display' => 'Oxygen saturation', 'unit' => 'percent saturation', 'unit_code' => '%'],
            'gcs' => ['label' => 'GCS', 'column' => 'gcs', 'table' => 'satu_sehat_observationttvgcs', 'code' => '9269-2', 'display' => 'Glasgow coma score total', 'unit' => null, 'unit_code' => '{score}'],
            'kesadaran' => ['label' => 'Kesadaran', 'column' => 'kesadaran', 'table' => 'satu_sehat_observationttvkesadaran', 'code' => '1104441000000107', 'display' => 'ACVPU (Alert Confusion Voice Pain Unresponsive) scale score', 'system' => 'http://snomed.info/sct', 'concept' => true],
            'tensi' => ['label' => 'Tensi', 'column' => 'tensi', 'table' => 'satu_sehat_observationttvtensi', 'code' => '35094-2', 'display' => 'Blood pressure panel', 'blood_pressure' => true],
            'tinggi' => ['label' => 'Tinggi Badan', 'column' => 'tinggi', 'table' => 'satu_sehat_observationttvtb', 'code' => '8302-2', 'display' => 'Body height', 'unit' => 'centimeter', 'unit_code' => 'cm'],
            'berat' => ['label' => 'Berat Badan', 'column' => 'berat', 'table' => 'satu_sehat_observationttvbb', 'code' => '29463-7', 'display' => 'Body Weight', 'unit' => 'kilogram', 'unit_code' => 'kg'],
            'lingkar_perut' => ['label' => 'Lingkar Perut', 'column' => 'lingkar_perut', 'table' => 'satu_sehat_observationttvlp', 'code' => '8280-0', 'display' => 'Waist Circumference at umbilicus by Tape measure', 'unit' => 'centimeter', 'unit_code' => 'cm'],
        ];
    }

    public static function get(string $type): array
    {
        return self::all()[$type] ?? throw new InvalidArgumentException('Jenis TTV tidak valid.');
    }
}
