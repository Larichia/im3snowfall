<?php

$sources = [
    'Andermatt' => __DIR__ . '/data/Andermatt_sedrun_disentis_1985_2025.csv',
    'Arosa' => __DIR__ . '/data/Arosa_Lenzerheide_1985_2025.csv',
    'Davos' => __DIR__ . '/data/Davos_Dorf_1985_2025.csv',
    'Laax' => __DIR__ . '/data/Laax_Flims_1985_2025.csv',
    'Samnaun' => __DIR__ . '/data/Samnaun_Ischgl_1985_2025.csv',
    'Scuol' => __DIR__ . '/data/Scuol_1985_2025_2.csv',
    'St.Moritz' => __DIR__ . '/data/St.Moritz_1985_2025.csv',
];

$rawLocations = [];

foreach ($sources as $place => $file) {
    $handle = fopen($file, 'r');
    //stündl. Schneehöhen speichern
    $snowDepthRows = [];

    //tägl. Wetterdaten speichern
    $dailyRows = [];


    //Stündl. snow_depth-Block suchen
    $snowDepthHeader = null;
    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {

        if (
            ($row[0] ?? '') === 'time'
            && in_array('snow_depth (m)', $row, true)
        ) {
            $snowDepthHeader = array_map('trim', $row);
            break;
        }
    }


    $dailyHeader = null;

    //Stündl. Schneehöhen einlesen
    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {

        if (($row[0] ?? '') === '') {
            continue;
        }

        // Wenn tägl. Wetterblock beginnt -> Einlesen der Schneehöhe stoppen
        if (
            ($row[0] ?? '') === 'time'
            && in_array('snowfall_sum (cm)', $row, true)
        ) {
            $dailyHeader = array_map('trim', $row);
            break;
        }

        if (count($row) !== count($snowDepthHeader)) {
            continue;
        }

        $snowDepthRows[] = array_combine(
            $snowDepthHeader,
            $row
        );
    }

    //Tägl. Wetterdaten einlesen
   while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {

        if (($row[0] ?? '') === '') {
            continue;
        }
        if (count($row) !== count($dailyHeader)) {
            continue;
        }
        $dailyRows[] = array_combine(
            $dailyHeader,
            $row
        );
    }

    fclose($handle);


    $rawLocations[] = [
        'place' => $place,
        'snow_depth' => $snowDepthRows,
        'daily' => $dailyRows,
    ];
}

return $rawLocations;