<?php

ini_set('display_errors', '1');

ini_set('display_startup_errors', '1');

error_reporting(E_ALL);

$sources = [
    'Andermatt' => __DIR__ . '/../data/Andermatt_sedrun_disentis_1985_2025.csv',
    'Arosa' => __DIR__ . '/../data/Arosa_Lenzerheide_1985_2025.csv',
    'Davos' => __DIR__ . '/../data/Davos_Dorf_1985_2025.csv',
    'Laax' => __DIR__ . '/../data/Laax_Flims_1985_2025.csv',
    'Samnaun' => __DIR__ . '/../data/Samnaun_Ischgl_1985_2025.csv',
    'Scuol' => __DIR__ . '/../data/Scuol_1985_2025_2.csv',
    'St.Moritz' => __DIR__ . '/../data/St.Moritz_1985_2025.csv',
];

$rawLocations = [];

foreach ($sources as $place => $file) {

    if (!is_file($file)) {
        die("Datei nicht gefunden: $file");
    }

    $handle = fopen($file, 'r');

    if ($handle === false) {
        die("Datei konnte nicht geöffnet werden: $file");
    }

    $snowDepthRows = [];
    $dailyRows = [];

    $snowDepthHeader = null;
    $dailyHeader = null;


    // =========================================================
    // Header für Schneehöhe suchen
    // =========================================================

    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {

        // BOM und Leerzeichen entfernen
        $row = array_map(
            fn($value) => trim($value, "\xEF\xBB\xBF \t\n\r\0\x0B"),
            $row
        );

        if (
            ($row[0] ?? '') === 'time'
            && in_array('snow_depth (m)', $row, true)
        ) {
            $snowDepthHeader = $row;
            break;
        }
    }

    if ($snowDepthHeader === null) {
        fclose($handle);
        die("Kein snow_depth-Header gefunden in: $file");
    }


    // =========================================================
    // Stündliche Schneehöhe einlesen
    // =========================================================

    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {

        $row = array_map('trim', $row);

        if (($row[0] ?? '') === '') {
            continue;
        }

        // Beginn des täglichen Wetterblocks
        if (
            ($row[0] ?? '') === 'time'
            && in_array('snowfall_sum (cm)', $row, true)
        ) {
            $dailyHeader = $row;
            break;
        }

        if (count($row) !== count($snowDepthHeader)) {
            continue;
        }

        $combined = array_combine(
            $snowDepthHeader,
            $row
        );

        if ($combined !== false) {
            $snowDepthRows[] = $combined;
        }
    }


    if ($dailyHeader === null) {
        fclose($handle);
        die("Kein daily-Header gefunden in: $file");
    }


    // =========================================================
    // Tägliche Wetterdaten einlesen
    // =========================================================

    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {

        $row = array_map('trim', $row);

        if (($row[0] ?? '') === '') {
            continue;
        }

        if (count($row) !== count($dailyHeader)) {
            continue;
        }

        $combined = array_combine(
            $dailyHeader,
            $row
        );

        if ($combined !== false) {
            $dailyRows[] = $combined;
        }
    }

    fclose($handle);


    // =========================================================
    // Location speichern
    // =========================================================

    $rawLocations[] = [
        'place' => $place,
        'snow_depth' => $snowDepthRows,
        'daily' => $dailyRows,
    ];
}

return $rawLocations;