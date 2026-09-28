<?php

header('Content-Type: text/plain; charset=utf-8');

$handle = fopen('data/valbella_ogd-smn_vab_d_historical.csv', 'r');

$header = array_map('trim', fgetcsv($handle, null, ',', '"', ''));

$snow = [];

while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
    if ($row[0] === '') {
        continue;
    }
    $snow[] = array_combine($header, $row);
}

fclose($handle);

for ($i = 0; $i < 5; $i++) {
    print_r($snow[$i]);
}

echo "blöd kloffa";