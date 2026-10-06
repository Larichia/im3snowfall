<?php

declare(strict_types=1);

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
     * 2. SQL
     *
     * Zuerst:
     * Jahressumme des Schneefalls pro Ort berechnen.
     *
     * Beispiel:
     *
     * 1988 | Andermatt | 623.4
     * 1988 | Arosa     | 712.8
     * 1988 | Davos     | 487.2
     * ...
     *
     * Danach:
     * Durchschnitt dieser Jahressummen berechnen.
     * ===================================================== */

    $sql = "
        SELECT
            yearly.year,
            AVG(yearly.snowfall) AS average_snowfall,
            COUNT(*) AS place_count

        FROM (

            SELECT
                YEAR(w.day) AS year,
                l.place,
                SUM(w.snowfall) AS snowfall

            FROM weather_data AS w

            INNER JOIN locations AS l
                ON l.id = w.place_id

            WHERE w.snowfall IS NOT NULL

            GROUP BY
                YEAR(w.day),
                l.place

        ) AS yearly

        GROUP BY
            yearly.year

        ORDER BY
            yearly.year ASC
    ";


    /* =====================================================
     * 3. Query ausführen
     * ===================================================== */

    $stmt = $pdo->prepare($sql);

    $stmt->execute();


    /* =====================================================
     * 4. Daten holen
     * ===================================================== */

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /* =====================================================
     * 5. JSON-Daten vorbereiten
     * ===================================================== */

    $data = [];

    foreach ($rows as $row) {

        $data[] = [

            'year' =>
                (int) $row['year'],

            'average_snowfall' =>
                round(
                    (float) $row['average_snowfall'],
                    1
                ),

            'place_count' =>
                (int) $row['place_count'],
        ];
    }


    /* =====================================================
     * 6. JSON ausgeben
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
        'yearly.php Fehler: ' . $e->getMessage()
    );


    /*
     * Fehler als JSON zurückgeben.
     */
    echo json_encode(
        [
            'error' =>
                'Beim Berechnen der Jahresdaten ist ein Fehler aufgetreten.',

            'message' =>
                $e->getMessage(),
        ],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE
    );
}