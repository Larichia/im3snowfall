const ctx = document.getElementById("verlauf");

let snowChart = new Chart(ctx, {
    type: "bar",

    data: {
        labels: [],
        datasets: [{
            label: "Schneefall",
            data: [],
            borderWidth: 1
        }]
    },

    options: {
        responsive: true,

        scales: {
            x: {
                title: {
                    display: true,
                    text: "Jahr"
                }
            },

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


async function loadSnowData(place) {

    try {

        // Daten von PHP laden
        const response = await fetch(
            "etl/unload.php?place=" + encodeURIComponent(place)
        );

        if (!response.ok) {
            throw new Error("Fehler beim Laden der Daten");
        }

        const data = await response.json();

        // Schneefall pro Jahr speichern
        const snowfallPerYear = {};

        data.forEach(row => {

            // z.B. "2020-01-15" -> "2020"
            const year = row.day.substring(0, 4);

            // null-Werte ignorieren
            if (row.snowfall !== null) {

                if (snowfallPerYear[year] === undefined) {
                    snowfallPerYear[year] = 0;
                }

                snowfallPerYear[year] += row.snowfall;
            }
        });


        // Jahre sortieren
        const years = Object.keys(snowfallPerYear).sort();

        // Schneemengen passend zu den Jahren
        const snowfall = years.map(year => {
            return snowfallPerYear[year];
        });


        // Diagramm aktualisieren
        snowChart.data.labels = years;

        snowChart.data.datasets[0].data = snowfall;

        snowChart.data.datasets[0].label =
            "Schneefall – " + place;

        snowChart.update();

    } catch (error) {

        console.error(error);

    }
}


// Beim Start direkt Andermatt anzeigen
loadSnowData("Andermatt");