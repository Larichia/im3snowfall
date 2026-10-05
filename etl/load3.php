<?php

$rawLocations = include __DIR__ . '/extract.php';

$sourceStartYear = 1985;
$sourceEndYear = 2025;
$winterMonths = [11, 12, 1, 2, 3, 4];
$offSeasonMonths = [5, 6, 7, 8, 9, 10];
$firstCompleteWinterStartYear = 1985;
$lastCompleteWinterStartYear = 2024;
$snowfallDayThresholdCm = 0.0;
$expectedPlaces = count($rawLocations);

function winterStartYear(int $year, int $month): int
{
    return $month >= 11 ? $year : $year - 1;
}

function winterLabel(int $startYear): string
{
    return $startYear . '/' . substr((string) ($startYear + 1), -2);
}

function expectedWinterDays(int $startYear): int
{
    $start = new DateTimeImmutable($startYear . '-11-01');
    $end = new DateTimeImmutable(($startYear + 1) . '-04-30');
    return (int) $start->diff($end)->format('%a') + 1;
}

$audit = [
    'input_rows' => 0,
    'outside_source_period' => 0,
    'invalid_date' => 0,
    'invalid_measurements' => 0,
    'duplicate_place_date' => 0,
    'winter_rows' => 0,
    'offseason_without_snow' => 0,
    'offseason_snow_days' => 0,
    'partial_winter_rows_not_aggregated' => 0,
    'incomplete_place_winter_seasons' => 0,
    'output_database_rows' => 0,
    'output_winter_place_rows' => 0,
    'output_winter_all_places_rows' => 0,
    'output_offseason_place_year_rows' => 0,
];

$databaseRows = [];
$winterDailyRows = [];
$offSeasonSnowDays = [];
$seen = [];

foreach ($rawLocations as $location) {
    $place = (string) ($location['place'] ?? '');
    $maslRaw = $location['metadata']['elevation'] ?? null;

    if ($place === '' || !is_numeric($maslRaw)) {
        throw new RuntimeException('Ort oder Höhenangabe fehlt im Extract.');
    }

    $masl = (int) round((float) $maslRaw);

    foreach (($location['rows'] ?? []) as $raw) {
        $audit['input_rows']++;

        $date = trim((string) ($raw['time'] ?? ''));
        $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $dateIsValid = $dateObject !== false && $dateObject->format('Y-m-d') === $date;

        if (!$dateIsValid) {
            $audit['invalid_date']++;
            continue;
        }

        $year = (int) $dateObject->format('Y');
        $month = (int) $dateObject->format('n');
        $day = (int) $dateObject->format('j');

        if ($year < $sourceStartYear || $year > $sourceEndYear) {
            $audit['outside_source_period']++;
            continue;
        }

        $snowfallRaw = $raw['snowfall_sum (cm)'] ?? null;
        $maxRaw = $raw['temperature_2m_max (°C)'] ?? null;
        $minRaw = $raw['temperature_2m_min (°C)'] ?? null;
        $avgRaw = $raw['temperature_2m_mean (°C)'] ?? null;

        if (
            !is_numeric($snowfallRaw) ||
            !is_numeric($maxRaw) ||
            !is_numeric($minRaw) ||
            !is_numeric($avgRaw)
        ) {
            $audit['invalid_measurements']++;
            continue;
        }

        $key = $place . '|' . $date;
        if (isset($seen[$key])) {
            $audit['duplicate_place_date']++;
            continue;
        }
        $seen[$key] = true;

        $snowfall = round((float) $snowfallRaw, 2);

        $row = [
            'place' => $place,
            'average_temperature' => round((float) $avgRaw, 1),
            'max_temperature' => round((float) $maxRaw, 1),
            'min_temperature' => round((float) $minRaw, 1),
            'date' => $date,
            'snowfall' => $snowfall,
            'masl' => $masl,
            'month' => $month,
            'year' => $year,
            'day' => $day,
        ];

        if (in_array($month, $winterMonths, true)) {
            $audit['winter_rows']++;
            $winterDailyRows[] = $row;
            $databaseRows[] = $row;
            continue;
        }

        // Mai bis Oktober: nur Tage mit gemessenem Schneefall > 0 cm behalten.
        if (in_array($month, $offSeasonMonths, true) && $snowfall > $snowfallDayThresholdCm) {
            $audit['offseason_snow_days']++;
            $offSeasonSnowDays[] = $row;
            $databaseRows[] = $row;
        } else {
            $audit['offseason_without_snow']++;
        }
    }
}

$sortDaily = function (array $a, array $b): int {
    return [$a['date'], $a['place']] <=> [$b['date'], $b['place']];
};

usort($databaseRows, $sortDaily);
usort($winterDailyRows, $sortDaily);
usort($offSeasonSnowDays, $sortDaily);
$audit['output_database_rows'] = count($databaseRows);

// -----------------------------------------------------------------------------
// Winterwerte pro Ort und vollständiger Wintersaison.
// Nur 1985/86 bis 2024/25 werden für den 40-Jahres-Vergleich verwendet.
// -----------------------------------------------------------------------------
$winterBuckets = [];

foreach ($winterDailyRows as $row) {
    $startYear = winterStartYear($row['year'], $row['month']);

    if ($startYear < $firstCompleteWinterStartYear || $startYear > $lastCompleteWinterStartYear) {
        $audit['partial_winter_rows_not_aggregated']++;
        continue;
    }

    $key = $row['place'] . '|' . $startYear;

    if (!isset($winterBuckets[$key])) {
        $winterBuckets[$key] = [
            'place' => $row['place'],
            'winter_start_year' => $startYear,
            'winter_end_year' => $startYear + 1,
            'winter' => winterLabel($startYear),
            'masl' => $row['masl'],
            'measurement_days' => 0,
            'snowfall_sum' => 0.0,
            'temperature_sum' => 0.0,
            'max_temperature' => null,
            'min_temperature' => null,
        ];
    }

    $bucket =& $winterBuckets[$key];
    $bucket['measurement_days']++;
    $bucket['snowfall_sum'] += $row['snowfall'];
    $bucket['temperature_sum'] += $row['average_temperature'];

    if ($bucket['max_temperature'] === null || $row['max_temperature'] > $bucket['max_temperature']) {
        $bucket['max_temperature'] = $row['max_temperature'];
    }
    if ($bucket['min_temperature'] === null || $row['min_temperature'] < $bucket['min_temperature']) {
        $bucket['min_temperature'] = $row['min_temperature'];
    }
    unset($bucket);
}

$winterByPlace = [];
foreach ($winterBuckets as $bucket) {
    $expectedDays = expectedWinterDays($bucket['winter_start_year']);

    if ($bucket['measurement_days'] !== $expectedDays) {
        $audit['incomplete_place_winter_seasons']++;
        continue;
    }

    $winterByPlace[] = [
        'place' => $bucket['place'],
        'winter_start_year' => $bucket['winter_start_year'],
        'winter_end_year' => $bucket['winter_end_year'],
        'winter' => $bucket['winter'],
        'masl' => $bucket['masl'],
        'measurement_days' => $bucket['measurement_days'],
        'snowfall' => round($bucket['snowfall_sum'], 2),
        'average_temperature' => round($bucket['temperature_sum'] / $bucket['measurement_days'], 2),
        'max_temperature' => round((float) $bucket['max_temperature'], 1),
        'min_temperature' => round((float) $bucket['min_temperature'], 1),
    ];
}

usort($winterByPlace, function (array $a, array $b): int {
    return [$a['winter_start_year'], $a['place']] <=> [$b['winter_start_year'], $b['place']];
});
$audit['output_winter_place_rows'] = count($winterByPlace);

// -----------------------------------------------------------------------------
// Winterwerte über alle Orte.
// Nur Saisons mit allen sieben Orten kommen in den Vergleich.
// -----------------------------------------------------------------------------
$winterAllBuckets = [];
foreach ($winterByPlace as $row) {
    $key = $row['winter_start_year'];

    if (!isset($winterAllBuckets[$key])) {
        $winterAllBuckets[$key] = [
            'winter_start_year' => $row['winter_start_year'],
            'winter_end_year' => $row['winter_end_year'],
            'winter' => $row['winter'],
            'places_count' => 0,
            'total_snowfall_all_places' => 0.0,
            'temperature_weighted_sum' => 0.0,
            'temperature_days' => 0,
        ];
    }

    $winterAllBuckets[$key]['places_count']++;
    $winterAllBuckets[$key]['total_snowfall_all_places'] += $row['snowfall'];
    $winterAllBuckets[$key]['temperature_weighted_sum'] += $row['average_temperature'] * $row['measurement_days'];
    $winterAllBuckets[$key]['temperature_days'] += $row['measurement_days'];
}

$winterAllPlaces = [];
foreach ($winterAllBuckets as $bucket) {
    if ($bucket['places_count'] !== $expectedPlaces) {
        continue;
    }

    $winterAllPlaces[] = [
        'winter_start_year' => $bucket['winter_start_year'],
        'winter_end_year' => $bucket['winter_end_year'],
        'winter' => $bucket['winter'],
        'places_count' => $bucket['places_count'],
        'total_snowfall_all_places' => round($bucket['total_snowfall_all_places'], 2),
        'average_snowfall_per_place' => round($bucket['total_snowfall_all_places'] / $bucket['places_count'], 2),
        'average_temperature' => round($bucket['temperature_weighted_sum'] / $bucket['temperature_days'], 2),
    ];
}

usort($winterAllPlaces, fn(array $a, array $b): int => $a['winter_start_year'] <=> $b['winter_start_year']);
$audit['output_winter_all_places_rows'] = count($winterAllPlaces);

// -----------------------------------------------------------------------------
// Schneefall ausserhalb des Winters (Mai–Oktober), separat nach Ort/Jahr.
// Hier geht es um Tage mit snowfall > 0 cm, nicht um alle Sommertage.
// -----------------------------------------------------------------------------
$offSeasonBuckets = [];
foreach ($offSeasonSnowDays as $row) {
    $key = $row['place'] . '|' . $row['year'];

    if (!isset($offSeasonBuckets[$key])) {
        $offSeasonBuckets[$key] = [
            'place' => $row['place'],
            'year' => $row['year'],
            'masl' => $row['masl'],
            'snow_days' => 0,
            'snowfall_sum' => 0.0,
        ];
    }

    $offSeasonBuckets[$key]['snow_days']++;
    $offSeasonBuckets[$key]['snowfall_sum'] += $row['snowfall'];
}

$offSeasonByPlaceYear = [];
foreach ($offSeasonBuckets as $bucket) {
    $offSeasonByPlaceYear[] = [
        'place' => $bucket['place'],
        'year' => $bucket['year'],
        'masl' => $bucket['masl'],
        'snow_days' => $bucket['snow_days'],
        'snowfall' => round($bucket['snowfall_sum'], 2),
    ];
}

usort($offSeasonByPlaceYear, function (array $a, array $b): int {
    return [$a['year'], $a['place']] <=> [$b['year'], $b['place']];
});
$audit['output_offseason_place_year_rows'] = count($offSeasonByPlaceYear);

return [
    'question' => 'Wie hat sich die Schneemenge an sieben Orten über 40 vollständige Winter von 1985/86 bis 2024/25 verändert und wie steht sie im Verhältnis zur Temperatur?',
    'unit_of_analysis' => 'Eine Zeile in data entspricht einem relevanten Ort-Tag: November bis April oder einem Schneefalltag von Mai bis Oktober.',
    'rules' => [
        'source_period' => '1985-01-01 bis 2025-12-31',
        'winter_months' => $winterMonths,
        'winter_definition' => 'November bis April; die Saison wird nach dem Startjahr benannt, z. B. 1985/86.',
        'complete_winter_range' => '1985/86 bis 2024/25 = 40 vollständige Winter',
        'offseason_months' => $offSeasonMonths,
        'offseason_snow_day' => 'Mai bis Oktober und snowfall > 0 cm',
        'snowfall_definition' => 'snowfall_sum (cm) = täglicher Neuschnee in Zentimetern',
        'winter_snowfall_by_place' => 'Summe der täglichen snowfall-Werte von November bis April je Ort und Wintersaison',
        'winter_average_all_places' => 'Mittelwert der sieben Wintersummen je Wintersaison',
        'winter_temperature_all_places' => 'Mittelwert der täglichen average_temperature-Werte aller Orte im Winter',
        'duplicate_key' => 'place + date',
    ],
    'data' => $databaseRows,
    'winter_daily' => $winterDailyRows,
    'offseason_snow_days' => $offSeasonSnowDays,
    'winter_by_place' => $winterByPlace,
    'winter_all_places' => $winterAllPlaces,
    'offseason_by_place_year' => $offSeasonByPlaceYear,
    'audit' => $audit,
];
