<?php
declare(strict_types=1);

$rows = require __DIR__ . '/extract.php';

$yearlySnowfallByPlace = [];

/*
 * 1. Jährlichen Schneefall pro Ort berechnen
 */
foreach ($rows as $row) {

    if (
        !isset($row['place'], $row['date']) ||
        $row['snowfall'] === null
    ) {
        continue;
    }

    $place = $row['place'];
    $year = (int) substr($row['date'], 0, 4);

    if (!isset($yearlySnowfallByPlace[$place][$year])) {
        $yearlySnowfallByPlace[$place][$year] = 0.0;
    }

    $yearlySnowfallByPlace[$place][$year] += (float) $row['snowfall'];
}


/*
 * 2. Durchschnitt aller Orte pro Jahr berechnen
 */
$yearlyAverageSnowfall = [];

foreach ($yearlySnowfallByPlace as $place => $years) {

    foreach ($years as $year => $snowfall) {

        if (!isset($yearlyAverageSnowfall[$year])) {
            $yearlyAverageSnowfall[$year] = [
                'sum' => 0.0,
                'count' => 0,
            ];
        }

        $yearlyAverageSnowfall[$year]['sum'] += $snowfall;
        $yearlyAverageSnowfall[$year]['count']++;
    }
}


/*
 * 3. Jahre sortieren
 */
ksort($yearlyAverageSnowfall);


/*
 * 4. Ausgabe im Browser
 */
echo "<br>";
echo "========================================<br>";
echo "<strong>JÄHRLICHER DURCHSCHNITT SCHNEEFALL</strong><br>";
echo "========================================<br>";
echo "<br>";


foreach ($yearlyAverageSnowfall as $year => $values) {

    if ($values['count'] === 0) {
        continue;
    }

    $averageSnowfall = $values['sum'] / $values['count'];

    echo sprintf(
        "%d: %.1f cm",
        $year,
        $averageSnowfall
    );

    echo "<br>";
}