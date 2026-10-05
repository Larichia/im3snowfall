<?php

$rawLocations = include __DIR__ . '/etl/extract.php';

//Wintermonate: November bis April
$winterMonths = [11, 12, 1, 2, 3, 4];

$audit = [
    'input_daily_rows' => 0,
    'input_snow_depth_rows' => 0,
    'invalid_rows' => 0,
    'duplicates' => 0,
    'offseason_snow_days' => 0,
];

//fertige Winter-Tagesdaten
$transformedRows = [];
//Schneetage ausserhalb November–April
$offseasonSnowDays = [];
//bereits verwendete Kombinationen aus Ort und Datum
$usedPlaceDates = [];


// Alle Orte durchgehen
foreach ($rawLocations as $location) {
    $place = $location['place'];
    //Stündl. Schneehöhe zu Tagesdurchschnitt machen
    $snowDepthByDay = [];
    foreach ($location['snow_depth'] as $snowRow) {
        $audit['input_snow_depth_rows']++;
        $time = $snowRow['time'] ?? '';
        $snowDepthRaw = $snowRow['snow_depth (m)'] ?? null;
        // Ungültige Schneehöhen überspringen
        if ($time === '' || !is_numeric($snowDepthRaw)) {
            $audit['invalid_rows']++;
            continue;
        }
        // Aus z.B. "1985-01-01T13:00" wird "1985-01-01"
        $date = substr($time, 0, 10);

        if (!isset($snowDepthByDay[$date])) {
            $snowDepthByDay[$date] = [
                'sum' => 0,
                'count' => 0,
            ];
        }

        // Schneehöhen des Tages addieren
        $snowDepthByDay[$date]['sum'] += (float) $snowDepthRaw;

        // Anzahl Messungen des Tages zählen
        $snowDepthByDay[$date]['count']++;
    }


    //Tägliche Wetterdaten durchgehen
    foreach ($location['daily'] as $row) {
        $audit['input_daily_rows']++;
        // Datum auslesen
        $date = trim($row['time'] ?? '');
        // Prüfen, ob das Datum gültig ist
        $dateObject = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $date
        );
        if (
            $dateObject === false
            || $dateObject->format('Y-m-d') !== $date
        ) {
            $audit['invalid_rows']++;
            continue;
        }

        //Jahr, Monat und Tag ableiten
        $year = (int) $dateObject->format('Y');
        $month = (int) $dateObject->format('n');
        $day = (int) $dateObject->format('j');

        //Messwerte auslesen
        $snowfallRaw =
            $row['snowfall_sum (cm)'] ?? null;

        $averageTemperatureRaw =
            $row['temperature_2m_mean (°C)'] ?? null;

        $maxTemperatureRaw =
            $row['temperature_2m_max (°C)'] ?? null;

        $minTemperatureRaw =
            $row['temperature_2m_min (°C)'] ?? null;

        //Prüfen, ob die Messwerte Zahlen sind
        if (
            !is_numeric($snowfallRaw)
            || !is_numeric($averageTemperatureRaw)
            || !is_numeric($maxTemperatureRaw)
            || !is_numeric($minTemperatureRaw)
        ) {
            $audit['invalid_rows']++;
            continue;
        }

        //Werte in richtige Zahlentypen umwandeln
        $snowfall = (float) $snowfallRaw;
        $averageTemperature =
            (float) $averageTemperatureRaw;

        $maxTemperature =
            (float) $maxTemperatureRaw;

        $minTemperature =
            (float) $minTemperatureRaw;


        //Durchschnittl. Schneehöhe des Tages berechnen
        $averageSnowDepth = null;
        if (isset($snowDepthByDay[$date])) {
            $averageSnowDepth =
                $snowDepthByDay[$date]['sum']
                / $snowDepthByDay[$date]['count'];
            $averageSnowDepth = round($averageSnowDepth, 3);
        }

        //Doppelte Datensätze erkennen
        $key = $place . '-' . $date;
        if (isset($usedPlaceDates[$key])) {
            $audit['duplicates']++;
            continue;
        }
        $usedPlaceDates[$key] = true;


        //Tage ausserhalb des Winters separat speichern
        if (!in_array($month, $winterMonths, true)) {
            // Nur wenn tatsächlich Schnee gefallen ist
            if ($snowfall > 0) {
                $audit['offseason_snow_days']++;
                $offseasonSnowDays[] = [
                    'place' => $place,
                    'date' => $date,
                    'year' => $year,
                    'month' => $month,
                    'day' => $day,
                    'snowfall' => round($snowfall, 2),
                ];
            }
            // Nicht in die Winterdaten übernehmen
            continue;
        }


        //Saubere Winter-Zielstruktur erstellen
        $transformedRows[] = [
            'place' => $place,
            'date' => $date,
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'snowfall' => round($snowfall, 2),

            // Tagesdurchschnitt aus den stündlichen Werten
            'average_snow_depth' => $averageSnowDepth,

            'average_temperature' =>
                round($averageTemperature, 1),
            'max_temperature' =>
                round($maxTemperature, 1),
            'min_temperature' =>
                round($minTemperature, 1),
        ];
    }
}


// Anzahl fertiger Winter-Tageszeilen
$audit['output_rows'] = count($transformedRows);


//Pro Ort und Jahr zusammenfassen
$byPlaceAndYear = [];

foreach ($transformedRows as $row) {
    $key = $row['place'] . '-' . $row['year'];
    if (!isset($byPlaceAndYear[$key])) {

        $byPlaceAndYear[$key] = [
            'place' => $row['place'],
            'year' => $row['year'],

            'snowfall_sum' => 0,

            'snow_depth_sum' => 0,
            'snow_depth_count' => 0,

            'temperature_sum' => 0,
            'temperature_count' => 0,
        ];
    }

    // Schneefall dieses Ortes im Jahr
    $byPlaceAndYear[$key]['snowfall_sum']
        += $row['snowfall'];

    // Schneehöhe nur verwenden, wenn Wert vorhanden ist
    if ($row['average_snow_depth'] !== null) {
        $byPlaceAndYear[$key]['snow_depth_sum']
            += $row['average_snow_depth'];
        $byPlaceAndYear[$key]['snow_depth_count']++;
    }

    // Temperatur
    $byPlaceAndYear[$key]['temperature_sum']
        += $row['average_temperature'];
    $byPlaceAndYear[$key]['temperature_count']++;
}


//Jahreswerte pro Ort erstellen
$yearlyByPlace = [];

foreach ($byPlaceAndYear as $row) {
    $yearlyByPlace[] = [
        'place' => $row['place'],
        'year' => $row['year'],
        // gesamte Schneemenge des Ortes/Jahr
        'snowfall' => round(
            $row['snowfall_sum'],
            2
        ),

        // durchschnittl. Schneehöhe
        'average_snow_depth' =>
            $row['snow_depth_count'] > 0
                ? round(
                $row['snow_depth_sum']
                / $row['snow_depth_count'],
                3
            )
                : null,

        // durchschnittl. Temperatur
        'average_temperature' => round(
            $row['temperature_sum']
            / $row['temperature_count'],
            2
        ),
    ];
}


//Durchschnitt aller Orte pro Jahr berechnen
$byYear = [];

foreach ($yearlyByPlace as $row) {
    $year = $row['year'];
    if (!isset($byYear[$year])) {
        $byYear[$year] = [
            'year' => $year,

            'snowfall_sum' => 0,

            'snow_depth_sum' => 0,
            'snow_depth_count' => 0,

            'temperature_sum' => 0,

            'places_count' => 0,
        ];
    }

    $byYear[$year]['snowfall_sum']
        += $row['snowfall'];

    $byYear[$year]['temperature_sum']
        += $row['average_temperature'];

    $byYear[$year]['places_count']++;

    if ($row['average_snow_depth'] !== null) {
        $byYear[$year]['snow_depth_sum']
            += $row['average_snow_depth'];
        $byYear[$year]['snow_depth_count']++;
    }
}


//Werte für Diagramme

$yearlyAllPlaces = [];

foreach ($byYear as $row) {
    $yearlyAllPlaces[] = [
        'year' => $row['year'],
        // durchschnittl. Schneemenge aller Orte
        'average_snowfall_all_places' => round(
            $row['snowfall_sum']
            / $row['places_count'],
            2
        ),

        // durchschnittl. Schneehöhe aller Orte
        'average_snow_depth_all_places' =>
            $row['snow_depth_count'] > 0
                ? round(
                $row['snow_depth_sum']
                / $row['snow_depth_count'],
                3
            )
                : null,

        // durchschnittl. Temperatur aller Orte
        'average_temperature_all_places' => round(
            $row['temperature_sum']
            / $row['places_count'],
            2
        ),
    ];
}


//Sortieren
usort(
    $yearlyByPlace,
    function ($a, $b) {
        return [$a['year'], $a['place']]
            <=>
            [$b['year'], $b['place']];
    }
);
usort(
    $yearlyAllPlaces,
    function ($a, $b) {
        return $a['year'] <=> $b['year'];
    }
);

print_r($audit);
print_r($transformedRows);

return [
    'question' =>
        'Wie haben sich Schneefall, Schneehöhe und Temperatur '
        . 'in den Wintermonaten November bis April '
        . 'zwischen 1985 und 2025 verändert?',

    // Tagesdaten November bis April
    'data' => $transformedRows,

    // Werte pro Ort und Jahr
    'yearly_by_place' => $yearlyByPlace,

    // Durchschnitt aller Orte pro Jahr
    'yearly_all_places' => $yearlyAllPlaces,

    // Schneefalltage ausserhalb November bis April
    'offseason_snow_days' => $offseasonSnowDays,

    // Kontrolle des Transforms
    'audit' => $audit,
];