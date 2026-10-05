<?php

declare(strict_types=1);

set_time_limit(0);

require_once __DIR__ . '/../config.php';


/*
 * ---------------------------------------------------------
 * PDO
 * ---------------------------------------------------------
 */

$pdo = new PDO(
    $dsn,
    $username,
    $password,
    $options
);

$pdo->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);

$pdo->setAttribute(
    PDO::ATTR_EMULATE_PREPARES,
    false
);


/*
 * ---------------------------------------------------------
 * ETL starten
 * ---------------------------------------------------------
 */

$rows = require __DIR__ . '/transform.php';


/*
 * ---------------------------------------------------------
 * Locations vorbereiten
 * ---------------------------------------------------------
 *
 * Die Namen müssen exakt den CSV-Namen entsprechen.
 *
 * Die Höhenmeter können wir hier eintragen, sobald sie
 * definitiv feststehen.
 */

$locations = [
    'Andermatt' => null,
    'Arosa' => null,
    'Davos' => null,
    'Laax' => null,
    'Samnaun' => null,
    'Scuol' => null,
    'St.Moritz' => null,
];


/*
 * ---------------------------------------------------------
 * Locations laden/anlegen
 * ---------------------------------------------------------
 */

$findLocation = $pdo->prepare(
    'SELECT id FROM locations WHERE place = ?'
);

$insertLocation = $pdo->prepare(
    'INSERT INTO locations (place, masl)
     VALUES (?, ?)'
);

$locationIds = [];

foreach ($locations as $place => $masl) {

    $findLocation->execute([$place]);

    $id = $findLocation->fetchColumn();

    if ($id === false) {

        /*
         * Falls masl noch nicht bekannt ist,
         * temporär 0 verwenden.
         *
         * Wir können das später per UPDATE setzen.
         */
        $insertLocation->execute([
            $place,
            $masl ?? 0,
        ]);

        $id = $pdo->lastInsertId();
    }

    $locationIds[$place] = (int) $id;
}


echo "Locations vorbereitet:\n";

foreach ($locationIds as $place => $id) {
    echo "  {$place} -> ID {$id}\n";
}

echo "\n";


/*
 * ---------------------------------------------------------
 * Weather INSERT / UPDATE
 * ---------------------------------------------------------
 *
 * Dank UNIQUE(place_id, day):
 *
 * - neuer Tag -> INSERT
 * - bereits vorhandener Tag -> UPDATE
 */

$insertWeather = $pdo->prepare(
    'INSERT INTO weather_data
    (
        place_id,
        day,
        snowfall,
        snowdepth,
        average_temperature,
        min_temperature,
        max_temperature
    )
    VALUES
    (
        :place_id,
        :day,
        :snowfall,
        :snowdepth,
        :average_temperature,
        :min_temperature,
        :max_temperature
    )
    ON DUPLICATE KEY UPDATE
        snowfall = VALUES(snowfall),
        snowdepth = VALUES(snowdepth),
        average_temperature = VALUES(average_temperature),
        min_temperature = VALUES(min_temperature),
        max_temperature = VALUES(max_temperature)'
);


/*
 * ---------------------------------------------------------
 * Import
 * ---------------------------------------------------------
 */

$pdo->beginTransaction();

$count = 0;
$placeCounts = [];

try {

    foreach ($rows as $row) {

        $place = $row['place'];

        if (!isset($locationIds[$place])) {
            throw new RuntimeException(
                "Unbekannter Ort: {$place}"
            );
        }

        $placeId = $locationIds[$place];


        $insertWeather->execute([
            ':place_id' => $placeId,
            ':day' => $row['date'],

            ':snowfall' => $row['snowfall'],

            ':snowdepth' => $row['snowdepth'],

            ':average_temperature' =>
                $row['average_temperature'],

            ':min_temperature' =>
                $row['min_temperature'],

            ':max_temperature' =>
                $row['max_temperature'],
        ]);


        $count++;

        if (!isset($placeCounts[$place])) {
            $placeCounts[$place] = 0;
        }

        $placeCounts[$place]++;


        /*
         * Alle 1'000 Datensätze committen.
         */
        if ($count % 1000 === 0) {

            $pdo->commit();

            echo "Importiert: {$count} Datensätze\n";

            $pdo->beginTransaction();
        }
    }


    /*
     * Letzten Batch committen.
     */
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $e;
}


/*
 * ---------------------------------------------------------
 * Ergebnis
 * ---------------------------------------------------------
 */

echo "\n";
echo "========================================\n";
echo "IMPORT ABGESCHLOSSEN\n";
echo "========================================\n";
echo "Datensätze verarbeitet: {$count}\n";
echo "\n";

foreach ($placeCounts as $place => $placeCount) {
    echo "{$place}: {$placeCount}\n";
}


/*
 * ---------------------------------------------------------
 * DB-Kontrolle
 * ---------------------------------------------------------
 */

echo "\n";
echo "DB-Kontrolle:\n";

$totalRows = (int) $pdo
    ->query('SELECT COUNT(*) FROM weather_data')
    ->fetchColumn();

echo "weather_data gesamt: {$totalRows}\n";


$range = $pdo
    ->query(
        'SELECT MIN(day) AS min_day,
                MAX(day) AS max_day
         FROM weather_data'
    )
    ->fetch(PDO::FETCH_ASSOC);

echo "Erster Tag: {$range['min_day']}\n";
echo "Letzter Tag: {$range['max_day']}\n";


echo "\n";
echo "Import erfolgreich.\n";