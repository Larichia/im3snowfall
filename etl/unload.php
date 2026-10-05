<?php

declare(strict_types=1);

/*
 * unload.php
 *
 * API-Endpunkt für das Frontend.
 *
 * Unterstützte Filter:
 *
 *   unload.php
 *   unload.php?year=2020
 *   unload.php?place=Andermatt
 *   unload.php?year=2020&place=Andermatt
 *
 * Datenbank:
 *   locations
 *   weather_data
 */

header('Content-Type: application/json; charset=utf-8');


try {

    /* =====================================================
     * 1. Datenbankverbindung
     * ===================================================== */

    require __DIR__ . '/../config.php';

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


    /* =====================================================
     * 2. Filter
     * ===================================================== */

    $place = trim($_GET['place'] ?? '');
    $yearInput = trim($_GET['year'] ?? '');

    $year = null;


    /* =====================================================
     * 3. Jahr validieren
     * ===================================================== */

    if ($yearInput !== '') {

        if (!preg_match('/^\d{4}$/', $yearInput)) {

            http_response_code(400);

            echo json_encode(
                [
                    'error' => 'Ungültiges Jahr. Beispiel: 2020'
                ],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
            );

            exit;
        }

        $year = (int) $yearInput;
    }


    /* =====================================================
     * 4. SQL
     * ===================================================== */

    $sql = "
        SELECT
            l.place,
            l.masl,
            w.day,
            w.snowdepth,
            w.snowfall,
            w.temperature,
            w.average_temperature,
            w.min_temperature,
            w.max_temperature

        FROM weather_data AS w

        INNER JOIN locations AS l
            ON l.id = w.place_id
    ";


    /* =====================================================
     * 5. Filter zusammenbauen
     * ===================================================== */

    $where = [];
    $params = [];


    /*
     * Ort
     *
     * ?place=Andermatt
     */
    if ($place !== '') {

        $where[] = 'l.place = :place';

        $params[':place'] = $place;
    }


    /*
     * Jahr
     *
     * ?year=2020
     */
    if ($year !== null) {

        /*
         * Wir verwenden einen Datumsbereich statt
         * YEAR(w.day), damit der Index auf day
         * besser genutzt werden kann.
         */

        $where[] = 'w.day >= :year_start';
        $where[] = 'w.day < :year_end';

        $params[':year_start'] = $year . '-01-01';
        $params[':year_end'] = ($year + 1) . '-01-01';
    }


    if (count($where) > 0) {

        $sql .= "\nWHERE " . implode(
                "\nAND ",
                $where
            );
    }


    /* =====================================================
     * 6. Sortierung
     * ===================================================== */

    $sql .= "
        ORDER BY
            w.day ASC,
            l.place ASC
    ";


    /* =====================================================
     * 7. Query ausführen
     * ===================================================== */

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);


    /* =====================================================
     * 8. Daten holen
     * ===================================================== */

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* =====================================================
     * 9. API-Datenformat
     * ===================================================== */

    $data = [];

    foreach ($rows as $row) {

        $data[] = [

            'place' => (string) $row['place'],

            'day' => (string) $row['day'],

            /*
             * DB:
             * snowdepth
             *
             * API:
             * snow_depth
             */
            'snow_depth' =>
                $row['snowdepth'] !== null
                    ? (float) $row['snowdepth']
                    : null,

            'snowfall' =>
                $row['snowfall'] !== null
                    ? (float) $row['snowfall']
                    : null,

            'min_temperature' =>
                $row['min_temperature'] !== null
                    ? (float) $row['min_temperature']
                    : null,

            'max_temperature' =>
                $row['max_temperature'] !== null
                    ? (float) $row['max_temperature']
                    : null,

            'masl' =>
                $row['masl'] !== null
                    ? (int) $row['masl']
                    : null,

            'temperature' =>
                $row['temperature'] !== null
                    ? (float) $row['temperature']
                    : null,

            'average_temperature' =>
                $row['average_temperature'] !== null
                    ? (float) $row['average_temperature']
                    : null,
        ];
    }


    /* =====================================================
     * 10. JSON
     * ===================================================== */

    echo json_encode(
        $data,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
    );


} catch (Throwable $e) {

    http_response_code(500);

    /*
     * Fehler ins Server-Log schreiben.
     */
    error_log(
        'unload.php Fehler: ' . $e->getMessage()
    );

    /*
     * Während der Entwicklung geben wir die eigentliche
     * Fehlermeldung zurück.
     *
     * Später können wir das wieder auf eine neutrale
     * Fehlermeldung ändern.
     */
    echo json_encode(
        [
            'error' => 'Beim Laden der Daten ist ein Fehler aufgetreten.',
            'message' => $e->getMessage(),
        ],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
    );
}