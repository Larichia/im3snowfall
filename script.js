// ==========================================================
// GRAFIK 1: SCHNEEFALL PRO ORT
// ==========================================================


// ----------------------------------------------------------
// 1. Canvas aus dem HTML holen
//
// Gehört zu diesem HTML:
// <canvas id="verlauf"></canvas>
// ----------------------------------------------------------

const ctx = document.getElementById("verlauf");


// ----------------------------------------------------------
// 2. Erste Grafik erstellen
//
// Diese Grafik zeigt den jährlichen Schneefall
// für einen ausgewählten Ort.
// ----------------------------------------------------------

let snowChart = new Chart(ctx, {

    // Balkendiagramm
    type: "bar",

    data: {

        // Die Jahreszahlen werden später eingefügt
        labels: [],

        datasets: [{
            label: "Schneefall",

            // Die Schneemengen werden später eingefügt
            data: [],

            borderWidth: 1
        }]
    },

    options: {

        // Grafik passt sich der Bildschirmgröße an
        responsive: true,

        scales: {

            // X-Achse = Jahre
            x: {
                title: {
                    display: true,
                    text: "Jahr"
                }
            },

            // Y-Achse = Schneemenge
            y: {
                beginAtZero: true,

                title: {
                    display: true,
                    text: "Schneemenge"
                }
            }
        }
    }
});


// ----------------------------------------------------------
// 3. Daten für einen bestimmten Ort laden
//
// Diese Funktion wird durch eure Buttons aufgerufen:
//
// onclick="loadSnowData('Davos')"
// ----------------------------------------------------------

async function loadSnowData(place) {

    try {

        // PHP-Datei aufrufen.
        // Der ausgewählte Ort wird an PHP übergeben.
        //
        // Beispiel:
        // etl/unload.php?place=Davos

        const response = await fetch(
            "etl/unload.php?place=" + encodeURIComponent(place)
        );


        // Prüfen, ob die Anfrage funktioniert hat
        if (!response.ok) {
            throw new Error("Fehler beim Laden der Daten");
        }


        // JSON von PHP in JavaScript-Daten umwandeln
        const data = await response.json();


        // --------------------------------------------------
        // 4. Schneefall pro Jahr berechnen
        // --------------------------------------------------

        const snowfallPerYear = {};


        // Jede Datenbankzeile durchgehen
        data.forEach(row => {

            // Aus:
            // 2020-01-15
            //
            // wird:
            // 2020

            const year = row.day.substring(0, 4);


            // Nur Werte verwenden, bei denen snowfall
            // tatsächlich vorhanden ist
            if (row.snowfall !== null) {


                // Falls das Jahr noch nicht existiert,
                // starten wir bei 0
                if (snowfallPerYear[year] === undefined) {
                    snowfallPerYear[year] = 0;
                }


                // Schneefall zu diesem Jahr addieren
                snowfallPerYear[year] += row.snowfall;
            }
        });


        // --------------------------------------------------
        // 5. Daten für Chart.js vorbereiten
        // --------------------------------------------------


        // Jahreszahlen holen und sortieren
        const years = Object.keys(snowfallPerYear).sort();


        // Für jedes Jahr die Schneemenge holen
        const snowfall = years.map(year => {
            return snowfallPerYear[year];
        });


        // --------------------------------------------------
        // 6. Grafik aktualisieren
        // --------------------------------------------------


        // X-Achse
        snowChart.data.labels = years;


        // Y-Werte
        snowChart.data.datasets[0].data = snowfall;


        // Titel der Datenreihe ändern
        // z.B. "Schneefall – Davos"
        snowChart.data.datasets[0].label =
            "Schneefall – " + place;


        // Chart neu zeichnen
        snowChart.update();


    } catch (error) {

        // Fehler in der Browser-Konsole anzeigen
        console.error(error);
    }
}


// ----------------------------------------------------------
// 7. Startwert
//
// Wenn die Webseite geöffnet wird,
// wird automatisch Andermatt angezeigt.
// ----------------------------------------------------------

loadSnowData("Andermatt");



// ==========================================================
// ENDE GRAFIK 1
// ==========================================================
//
// Ab hier kommt die zweite Grafik.
//
// ==========================================================





// ==========================================================
// GRAFIK 2: DURCHSCHNITTLICHER SCHNEEFALL GRAUBÜNDEN
// ==========================================================


// ----------------------------------------------------------
// 1. Zweites Canvas aus dem HTML holen
//
// Gehört zu:
// <canvas id="grDurchschnitt"></canvas>
// ----------------------------------------------------------

const grCtx = document.getElementById("grDurchschnitt");


// ----------------------------------------------------------
// 2. Daten für ganz Graubünden laden
// ----------------------------------------------------------

async function loadGraubuendenAverage() {

    try {

        // yearly.php liefert bereits:
        //
        // year
        // average_snowfall
        // place_count

        const response = await fetch("etl/yearly.php");


        // Prüfen, ob PHP erfolgreich war
        if (!response.ok) {
            throw new Error(
                "Fehler beim Laden der Graubünden-Daten"
            );
        }


        // JSON einlesen
        const data = await response.json();


        // Zum Testen in der Konsole anzeigen
        console.log("Graubünden Daten:", data);


        // --------------------------------------------------
        // 3. Daten aus dem JSON herausnehmen
        // --------------------------------------------------


        // Alle Jahreszahlen
        const years = data.map(row => row.year);


        // Durchschnittliche Schneemenge pro Jahr
        const snowfall = data.map(
            row => row.average_snowfall
        );


        // --------------------------------------------------
        // 4. Liniendiagramm erstellen
        // --------------------------------------------------

        new Chart(grCtx, {

            // Diesmal ein Liniendiagramm
            type: "line",

            data: {

                // X-Achse = Jahre
                labels: years,

                datasets: [{

                    label: "Ø Schneefall Graubünden",

                    // Y-Werte
                    data: snowfall,

                    borderWidth: 2,

                    // Linie etwas abrunden
                    tension: 0.2
                }]
            },

            options: {

                responsive: true,

                scales: {

                    // X-Achse
                    x: {
                        title: {
                            display: true,
                            text: "Jahr"
                        }
                    },

                    // Y-Achse
                    y: {
                        beginAtZero: true,

                        title: {
                            display: true,
                            text: "Ø jährliche Schneefallsumme"
                        }
                    }
                }
            }
        });


    } catch (error) {

        console.error(
            "Fehler beim Laden des GR-Durchschnitts:",
            error
        );
    }
}


// ----------------------------------------------------------
// 5. Zweite Grafik beim Start laden
// ----------------------------------------------------------

loadGraubuendenAverage();


// ==========================================================
// ENDE GRAFIK 2
// ==========================================================