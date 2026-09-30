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

    $header = array_map(
        'trim',
        fgetcsv($handle, null, ',', '"', '')
    );

    $rows = [];

    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
        if ($row[0] === '') {
            continue; // leere Zeile überspringen
        }

        $rows[] = array_combine($header, $row);
    }

    fclose($handle);

    $rawLocations[] = [
        'place' => $place,
        'source' => $rows,
    ];
}

return $rawLocations;