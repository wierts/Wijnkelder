<?php
// Eenmalig: vult de marktprijs (indicatie winkelprijs NL/EU per fles, opgezocht sept 2026).
// Alleen wijnen zonder marktprijs worden gevuld, dus veilig om nogmaals te openen.
// Open na het live zetten één keer: https://wiertsema.net/wijnkelder/api/marktprijs-seed.php
require __DIR__ . '/db.php';

const PRICES = [
    2 => [35.00, 'der-weinmakler.de, jg. 2022'],
    3 => [22.95, 'NL-webshop, jg. 2021'],
    4 => [25.95, 'NL-webshop, jg. 2023'],
    5 => [9.95, 'Vivino NL, recente jg.'],
    6 => [18.00, 'Duitse webshop'],
    7 => [27.90, 'Duitse webshop'],
    8 => [35.95, 'Vivino NL, oudere jg.'],
    9 => [43.00, 'Vivino NL, jg. 2022'],
    10 => [16.90, 'Franse webshop, jg. 2023'],
    11 => [25.00, 'Duitse webshops (€22–29)'],
    12 => [30.00, 'Vivino NL, jg. 2022'],
    13 => [229.00, 'vin-malin.fr, jg. 2020'],
    14 => [59.20, 'Franse webshop, jg. 2023'],
    15 => [50.00, 'schatting (geen winkelprijs gevonden)'],
    16 => [60.00, 'NL/DE webshops (€46–63)'],
    17 => [68.00, 'Vivino NL, jg. 2025'],
    18 => [9.00, 'schatting (geen winkelprijs gevonden)'],
    19 => [41.05, 'Vivino NL, jg. 2024'],
    20 => [18.83, 'Vivino NL, jg. 2020'],
    21 => [27.95, 'NL-webshop, jg. 2024'],
    22 => [24.86, 'Vivino NL, jg. 2018'],
    23 => [19.50, 'NL-webshop, jg. 2024'],
    24 => [28.90, 'NL-webshop'],
    25 => [22.00, 'schatting (Sarazinière VV magnum €52)'],
    26 => [15.00, 'Duitse webshops (€12,50–16,49)'],
    27 => [18.00, 'schatting (geen EU-winkelprijs gevonden)'],
    28 => [62.04, 'Vivino NL, jg. 2023'],
    29 => [40.00, 'webshop, jg. 2020'],
    30 => [110.00, 'NL/FR webshops (€107–145)'],
    31 => [28.50, 'lesbonsplansduvin.com, jg. 2023'],
    32 => [15.20, 'twil.fr'],
    33 => [24.00, 'domaine d\'Eole, jg. 2024'],
    34 => [19.80, 'Vivino NL, jg. 2024'],
    35 => [51.90, 'Vivino NL, \'Le Chardonnay\' jg. 2023'],
    36 => [17.50, 'Wijnbeurs (kist €104,99 / 6)'],
    37 => [10.50, 'NL-webshop, jg. 2024'],
    38 => [19.50, 'webshop'],
    39 => [65.90, 'Vivino NL, jg. 2023'],
    40 => [8.50, 'Spaanse webshop'],
    41 => [16.00, 'webshop'],
    42 => [30.00, 'domaine d\'Eole, jg. 2021'],
    43 => [17.90, 'webshop'],
    44 => [23.00, 'Vivino NL (€21–24)'],
    45 => [23.00, 'Vivino NL, jg. 2015'],
    46 => [30.00, 'schatting (geen winkelprijs gevonden)'],
    47 => [20.00, 'La Botega di Carilius'],
    48 => [12.00, 'La Botega di Carilius'],
    49 => [16.00, 'twil.fr'],
];

$stmt = $pdo->prepare('UPDATE wines SET market_price = ?, market_note = ? WHERE id = ? AND market_price IS NULL');
$n = 0;
foreach (PRICES as $id => [$price, $note]) {
    $stmt->execute([$price, 'Sept 2026 · ' . $note, $id]);
    $n += $stmt->rowCount();
}
echo json_encode(['bijgewerkt' => $n, 'totaal' => count(PRICES)]);
