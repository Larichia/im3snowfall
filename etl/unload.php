<?php

declare(strict_types=1);

/*
 * unload.php
 *
 * Liest die Winterdaten aus der Datenbank
 * und liefert sie als JSON an das Frontend.
 *
 * Datenvertrag:
 * - place
 * - day
 * - snow_depth
 * - snowfall
 * - min_temperature
 * - max_temperature
 * - masl
 * - temperature
 * - average_temperature
 */


/* =========================================================
 * 1. JSON-Header
 * ========================================================= */

header('Content-Type: application/json; charset=utf-8');


try {

    /* =====================================================
     * 2. Datenbankverbindung
     * ===================================================== */

    require __DIR__ . '/../config.php';


    /* =====================================================
     * 3. Optionalen place-Filter auslesen
     *
     * Beispiel:
     * unload.php?place=Andermatt
     * ===================================================== */

    $place = trim($_GET['place'] ?? '');


    /* =====================================================
     * 4. SQL-Abfrage
     *
     * c.name wird als "place" ausgegeben,
     * damit der Datenvertrag eingehalten wird.
     * ===================================================== */

    $sql = "
        SELECT
            c.name AS place,
            w.day,
            w.snow_depth,
            w.snowfall,
            w.min_temperature,
            w.max_temperature,
            w.masl,
            w.temperature,
            w.average_temperature
        FROM Andermatt_sedrun_disentis_1985_2025.csv AS w
        INNER JOIN cities AS c
            ON c.id = w.city_id
    ";


    /*
     * Wenn ?place=... angegeben wurde,
     * wird nur dieser Ort geladen.
     */

    $params = [];

    if ($place !== '') {

        $sql .= "
            WHERE c.name = :place
        ";

        $params['place'] = $place;
    }


    /* =====================================================
     * 5. Sortierung
     * ===================================================== */

    $sql .= "
        ORDER BY
            w.day ASC,
            c.name ASC
    ";


    /* =====================================================
     * 6. Prepared Statement ausführen
     * ===================================================== */

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);


    /* =====================================================
     * 7. Daten auslesen
     * ===================================================== */

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* =====================================================
     * 8. Datenvertrag + Datentypen
     * ===================================================== */

    $data = array_map(
        static function (array $row): array {

            return [
                'place' => (string) $row['place'],

                'day' => (string) $row['day'],

                'snow_depth' => $row['snow_depth'] !== null
                    ? (float) $row['snow_depth']
                    : null,

                'snowfall' => $row['snowfall'] !== null
                    ? (float) $row['snowfall']
                    : null,

                'min_temperature' => $row['min_temperature'] !== null
                    ? (float) $row['min_temperature']
                    : null,

                'max_temperature' => $row['max_temperature'] !== null
                    ? (float) $row['max_temperature']
                    : null,

                'masl' => $row['masl'] !== null
                    ? (int) $row['masl']
                    : null,

                'temperature' => $row['temperature'] !== null
                    ? (float) $row['temperature']
                    : null,

                'average_temperature' => $row['average_temperature'] !== null
                    ? (float) $row['average_temperature']
                    : null,
            ];
        },
        $rows
    );


    /* =====================================================
     * 9. JSON ausgeben
     * ===================================================== */

    echo json_encode(
        $data,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
    );


} catch (Throwable $e) {

    /* =====================================================
     * 10. Fehlerbehandlung
     * ===================================================== */

    http_response_code(500);

    error_log(
        'unload.php Fehler: ' . $e->getMessage()
    );

    echo json_encode(
        [
            'error' => 'Beim Laden der Daten ist ein Fehler aufgetreten.'
        ],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
    );
}