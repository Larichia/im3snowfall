<?php

$rawLocations = include __DIR__ . '/etl/extract.php';

$winterMonths = [11, 12, 1, 2, 3, 4];

$audit = [
    'input_days' => 0,
    'outside_winter' => 0,
    'invalid_measurements' => 0,
    'incomplete_winters' => 0,
    'output_rows' => 0,
];

$byPlaceAndYear = [];

foreach ($rawLocations as $location) {
    $place = $location['place'];

    $dailyRows = $location['source']['daily'] ?? [];
    $hourlyRows = $location['source']['hourly'] ?? [];

    /*
     * Tägliche Schneefallwerte verarbeiten
     */
    foreach ($dailyRows as $row) {
        $audit['input_days']++;

        $date = $row['time'] ?? null;
        $snowfall = $row['snowfall_sum (cm)'] ?? null;

        if ($date === null || !is_numeric($snowfall)) {
            $audit['invalid_measurements']++;
            continue;
        }

        $month = (int) substr($date, 5, 2);
        $year = (int) substr($date, 0, 4);

        /*
         * Ausserhalb November bis April:
         * nur zählen, wenn snowfall > 0.
         */
        if (!in_array($month, $winterMonths, true)) {
            if ((float) $snowfall > 0) {
                $audit['outside_winter']++;
            }

            continue;
        }

        /*
         * November und Dezember gehören zum Winter,
         * der in diesem Kalenderjahr beginnt.
         *
         * Januar bis April gehören zum Winter,
         * der im Vorjahr begonnen hat.
         */
        if ($month === 11 || $month === 12) {
            $winterYear = $year;
        } else {
            $winterYear = $year - 1;
        }

        $key = $place . '-' . $winterYear;

        if (!isset($byPlaceAndYear[$key])) {
            $byPlaceAndYear[$key] = [
                'place' => $place,
                'year' => $winterYear,
                'measurement_days' => 0,
                'snowfall_sum_cm' => 0.0,
            ];
        }

        $byPlaceAndYear[$key]['measurement_days']++;
        $byPlaceAndYear[$key]['snowfall_sum_cm'] += (float) $snowfall;
    }

    /*
     * Stündliche Schneehöhe prüfen.
     *
     * Sie wird nicht für measurement_days verwendet.
     * Sie dient nur dazu, Schnee ausserhalb der
     * Wintermonate zu erkennen.
     */
    $outsideWinterSnowDepthDays = [];

    foreach ($hourlyRows as $row) {
        $date = $row['time'] ?? null;
        $snowDepth = $row['snow_depth (m)'] ?? null;

        if ($date === null) {
            continue;
        }

        $month = (int) substr($date, 5, 2);

        if (in_array($month, $winterMonths, true)) {
            continue;
        }

        if (!is_numeric($snowDepth)) {
            continue;
        }

        if ((float) $snowDepth > 0) {
            /*
             * Nur einmal pro Tag zählen,
             * obwohl snow_depth stündlich vorliegt.
             */
            $day = substr($date, 0, 10);
            $outsideWinterSnowDepthDays[$day] = true;
        }
    }

    $audit['outside_winter'] += count($outsideWinterSnowDepthDays);
}

$transformedRows = [];

foreach ($byPlaceAndYear as $winter) {
    /*
     * November bis April:
     *
     * normal:     181 Tage
     * Schaltjahr: 182 Tage
     *
     * Der Februar liegt im Folgejahr.
     */
    $followingYear = $winter['year'] + 1;

    $isLeapYear =
        ($followingYear % 400 === 0)
        || ($followingYear % 4 === 0 && $followingYear % 100 !== 0);

    $expectedDaysPerWinter = $isLeapYear ? 182 : 181;

    if ($winter['measurement_days'] !== $expectedDaysPerWinter) {
        $audit['incomplete_winters']++;
        continue;
    }

    $winter['snowfall_sum_cm'] = round(
        $winter['snowfall_sum_cm'],
        1
    );

    $transformedRows[] = $winter;
}

usort($transformedRows, function (array $a, array $b): int {
    return [$a['year'], $a['place']]
        <=> [$b['year'], $b['place']];
});

$audit['output_rows'] = count($transformedRows);

return [
    'question' => 'Wie hat sich die Schneefallmenge pro Winter verändert?',

    'rules' => [
        'months' => $winterMonths,
        'winter_start' => 'November',
        'winter_end' => 'April',
    ],

    'data' => $transformedRows,

    'audit' => $audit,
];