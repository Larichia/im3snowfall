<?php

declare(strict_types=1);

/**
 * Liest CSV-Dateien zeilenweise ein.
 *
 * Der Extract liefert ausschliesslich Tageswerte.
 *
 * Die CSV-Dateien werden nicht komplett in Arrays geladen.
 */

$sources = [
    'Andermatt' => __DIR__ . '/../data/Andermatt_sedrun_disentis_1985_2025.csv',
    'Arosa' => __DIR__ . '/../data/Arosa_Lenzerheide_1985_2025.csv',
    'Davos' => __DIR__ . '/../data/Davos_Dorf_1985_2025.csv',
    'Laax' => __DIR__ . '/../data/Laax_Flims_1985_2025.csv',
    'Samnaun' => __DIR__ . '/../data/Samnaun_Ischgl_1985_2025.csv',
    'Scuol' => __DIR__ . '/../data/Scuol_1985_2025_2.csv',
    'St.Moritz' => __DIR__ . '/../data/St.Moritz_1985_2025.csv',
];


/**
 * Bereinigt eine CSV-Zeile.
 */
function cleanCsvRow(array $row): array
{
    return array_map(
        static function ($value) {
            if ($value === null) {
                return null;
            }

            return trim((string) $value);
        },
        $row
    );
}


/**
 * Prüft, ob es sich um den Header des Snowdepth-Blocks handelt.
 */
function isSnowDepthHeader(array $row): bool
{
    return in_array('time', $row, true)
        && in_array('snow_depth (m)', $row, true);
}


/**
 * Prüft, ob es sich um den Header des Daily-Blocks handelt.
 */
function isDailyHeader(array $row): bool
{
    return in_array('time', $row, true)
        && in_array('snowfall_sum (cm)', $row, true);
}


/**
 * Extractor als Generator.
 *
 * Liefert einen Datensatz pro Tag und Ort.
 */
function extractRows(array $sources): Generator
{
    foreach ($sources as $place => $file) {

        if (!is_file($file)) {
            throw new RuntimeException(
                "CSV-Datei nicht gefunden: {$file}"
            );
        }

        echo "Extract: {$place}\n";

        $handle = fopen($file, 'r');

        if ($handle === false) {
            throw new RuntimeException(
                "CSV-Datei konnte nicht geöffnet werden: {$file}"
            );
        }

        try {

            /*
             * ---------------------------------------------------------
             * 1. Snowdepth-Header suchen
             * ---------------------------------------------------------
             */

            $snowDepthHeader = null;

            while (($row = fgetcsv(
                    $handle,
                    0,
                    ',',
                    '"',
                    ''
                )) !== false) {

                $row = cleanCsvRow($row);

                if (isSnowDepthHeader($row)) {
                    $snowDepthHeader = $row;
                    break;
                }
            }

            if ($snowDepthHeader === null) {
                throw new RuntimeException(
                    "Kein Snowdepth-Header gefunden für {$place}"
                );
            }

            $timeIndex = array_search(
                'time',
                $snowDepthHeader,
                true
            );

            $snowDepthIndex = array_search(
                'snow_depth (m)',
                $snowDepthHeader,
                true
            );

            if (
                $timeIndex === false ||
                $snowDepthIndex === false
            ) {
                throw new RuntimeException(
                    "Snowdepth-Spalten fehlen für {$place}"
                );
            }


            /*
             * ---------------------------------------------------------
             * 2. Stundenwerte der Schneehöhe zu Tageswerten
             * ---------------------------------------------------------
             */

            $snowDepthByDay = [];

            while (($row = fgetcsv(
                    $handle,
                    0,
                    ',',
                    '"',
                    ''
                )) !== false) {

                $row = cleanCsvRow($row);

                /*
                 * Sobald der Daily-Header gefunden wird,
                 * beginnt der nächste Block.
                 */
                if (isDailyHeader($row)) {
                    break;
                }

                if (
                    !isset(
                        $row[$timeIndex],
                        $row[$snowDepthIndex]
                    )
                ) {
                    continue;
                }

                $timestamp = $row[$timeIndex];
                $snowDepthRaw = $row[$snowDepthIndex];

                if (
                    $timestamp === '' ||
                    $snowDepthRaw === ''
                ) {
                    continue;
                }

                $date = substr($timestamp, 0, 10);

                if (!preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $date
                )) {
                    continue;
                }

                /*
                 * Snowdepth ist in Metern.
                 * Umrechnung in Zentimeter.
                 */
                $snowDepthCm =
                    (float) $snowDepthRaw * 100;

                if (!isset($snowDepthByDay[$date])) {
                    $snowDepthByDay[$date] = [
                        'sum' => 0.0,
                        'count' => 0,
                    ];
                }

                $snowDepthByDay[$date]['sum']
                    += $snowDepthCm;

                $snowDepthByDay[$date]['count']++;
            }


            /*
             * ---------------------------------------------------------
             * 3. Daily-Block prüfen
             * ---------------------------------------------------------
             */

            if (
                !isset($row) ||
                !isDailyHeader($row)
            ) {
                throw new RuntimeException(
                    "Kein Daily-Block gefunden für {$place}"
                );
            }

            $dailyHeader = $row;


            /*
             * ---------------------------------------------------------
             * 4. Daily-Spalten suchen
             * ---------------------------------------------------------
             */

            $dailyTimeIndex = array_search(
                'time',
                $dailyHeader,
                true
            );

            $snowfallIndex = array_search(
                'snowfall_sum (cm)',
                $dailyHeader,
                true
            );

            $averageTemperatureIndex = array_search(
                'temperature_2m_mean (°C)',
                $dailyHeader,
                true
            );

            $maxTemperatureIndex = array_search(
                'temperature_2m_max (°C)',
                $dailyHeader,
                true
            );

            $minTemperatureIndex = array_search(
                'temperature_2m_min (°C)',
                $dailyHeader,
                true
            );

            if (
                $dailyTimeIndex === false ||
                $snowfallIndex === false ||
                $averageTemperatureIndex === false ||
                $maxTemperatureIndex === false ||
                $minTemperatureIndex === false
            ) {
                throw new RuntimeException(
                    "Eine oder mehrere Daily-Spalten fehlen für {$place}"
                );
            }


            /*
             * ---------------------------------------------------------
             * 5. Daily-Werte zurückgeben
             * ---------------------------------------------------------
             */

            while (($row = fgetcsv(
                    $handle,
                    0,
                    ',',
                    '"',
                    ''
                )) !== false) {

                $row = cleanCsvRow($row);

                if (!isset($row[$dailyTimeIndex])) {
                    continue;
                }

                $date = substr(
                    $row[$dailyTimeIndex],
                    0,
                    10
                );

                if (!preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $date
                )) {
                    continue;
                }

                $snowfall =
                    $row[$snowfallIndex] ?? null;

                $averageTemperature =
                    $row[$averageTemperatureIndex] ?? null;

                $minTemperature =
                    $row[$minTemperatureIndex] ?? null;

                $maxTemperature =
                    $row[$maxTemperatureIndex] ?? null;


                /*
                 * -----------------------------------------------------
                 * Tagesdurchschnitt der Schneehöhe
                 * -----------------------------------------------------
                 */

                $averageSnowDepth = null;

                if (
                    isset($snowDepthByDay[$date]) &&
                    $snowDepthByDay[$date]['count'] > 0
                ) {
                    $averageSnowDepth =
                        $snowDepthByDay[$date]['sum']
                        / $snowDepthByDay[$date]['count'];

                    $averageSnowDepth =
                        round($averageSnowDepth, 1);
                }


                /*
                 * -----------------------------------------------------
                 * Tagesdatensatz
                 * -----------------------------------------------------
                 */

                yield [
                    'place' => $place,
                    'date' => $date,

                    'snowfall' => (
                    $snowfall !== null &&
                    $snowfall !== ''
                        ? round(
                        (float) $snowfall,
                        1
                    )
                        : null
                    ),

                    'average_snow_depth' =>
                        $averageSnowDepth,

                    'average_temperature' => (
                    $averageTemperature !== null &&
                    $averageTemperature !== ''
                        ? round(
                        (float) $averageTemperature,
                        1
                    )
                        : null
                    ),

                    'min_temperature' => (
                    $minTemperature !== null &&
                    $minTemperature !== ''
                        ? round(
                        (float) $minTemperature,
                        1
                    )
                        : null
                    ),

                    'max_temperature' => (
                    $maxTemperature !== null &&
                    $maxTemperature !== ''
                        ? round(
                        (float) $maxTemperature,
                        1
                    )
                        : null
                    ),
                ];
            }

        } finally {
            fclose($handle);
        }

        echo "Extract abgeschlossen: {$place}\n";
    }
}


return extractRows($sources);