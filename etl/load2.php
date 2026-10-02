<?php
header('Content-type: text/plain; charset=utf-8');
// require __DIR__ . '/../config.php';
require_once __DIR__ . '/../config.php';

$result = include __DIR__ . '/transform.php';
$rows = $result['data'];
echo 'Der Transform performt' . count($rows) . " Zeilen.\n\n";

try {
    $pdo = new PDO($dsn, $username, $password, $options);
    echo "Verbindung steht.\n\n";
} catch (PDOException $e) {
    exit('Verbindung verkackt:' . $e->getMessage() . "\n");
}
$findPlace = $pdo->prepare('SELECT id FROM place WHERE name = ?');
$insertPlace = $pdo->prepare('INSERT INTO place (name) VALUES (?)');

$placeIds = [];

foreach ($rows as $row) {
    $place = $row['place'];

    if (isset($placeIds[$place])) {
        continue;
    }

    $findPlace->execute([$place]);
    $id = $findPlace->fetchColumn();

    if ($id === false) {
        $insertPlace->execute([$place]);

        $id = $pdo->lastInsertId();
    }

    $placeIds[$place] = (int) $id;
}

echo 'Orte in der Datenbank: ' . implode(', ', array_keys($placeIds)) . ".\n\n";
$deleted = $pdo->exec('DELETE FROM weather_data');
echo $deleted . " alte Zeilen gelöscht.\n\n";

$insertSnowfall = $pdo->prepare(
    'INSERT INTO weather_data (place_id, day, snowfall, snowdepth, average_temperature, min_temperature, max_temperature)
     VALUES (:place_id, :day, :snowfall, :snowdepth, :average_temperature, :min_temperature, :max_temperature)'
);

foreach ($rows as $row) {
    $insertSnowfall->execute([
        'place_id' => $placeIds [$row['place']],
        'day' => $row['day'],
        'snowfall' => $row['snowfall'],
        'snowdepth' => $row['snowdepth'],
        'average_temperature' => $row['average_temperature'],
        'min_temperature' => $row['min_temperature'],
        'max_temperature' => $row['max_temperature'],
    ]);
}

echo count($rows) . " Zeilen geschrieben.\n\n";

$total = $pdo->query('SELECT COUNT(*) FROM weather_data')->fetchColumn();
echo "In weather_data stehen jetzt {$total} Zeilen.\n\n";

$lastWinters = $pdo->prepare(
    'SELECT day, min_temperature, max_temperature
     FROM weather_data
     WHERE place_id = ?
     ORDER BY year DESC
     LIMIT 3'
);

foreach ($placeIds as $place => $placeId) {
    echo $place . ":\n";

    $lastWinters->execute([$placeId]);

    foreach ($lastWinters->fetchAll() as $winter) {
        echo '  ' . $winter['day'] . "\t"
            . $winter['min_temperature'] . " °C\n "
            . $winter['max_temperature'] . " °C\n";
    }
}