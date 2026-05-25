<?php
// FILE DI TEST TEMPORANEO - ELIMINARE DOPO L'USO
echo '<pre>';

$qrSamples = [
    '241BLENDSELEZ#103246-5926280',
    '241PISTACCSGUSCINTEROESTE-SL#102712',
    '241USASGUSCROTT A#105279',
];

try {
    $sqlPdo = new PDO("sqlsrv:Server=SERVER2019\\SISTEMI;Database=ESOLVER;TrustServerCertificate=1;Encrypt=0", 'ricercalotti', 'RicercaLotti2024!');
} catch (Throwable $e) {
    die("ERRORE SQL Server: " . $e->getMessage());
}

foreach ($qrSamples as $qr) {
    echo "══════════════════════════════════════\n";
    echo "QR: $qr\n";

    [$articlePart, $lotPart] = explode('#', $qr, 2);
    $codGestionale = ltrim($articlePart, '0123456789');

    // Genera varianti del lotto da testare su Esolver
    $variants = array_unique([
        $lotPart,                              // 103246-5926280
        substr($lotPart, 2),                   // 3246-5926280
        ltrim($lotPart, '0123456789'),         // -5926280
        substr($lotPart, 2, strpos($lotPart, '-', 2) - 2 ?: null), // 3246
        (string)(int)substr($lotPart, 2),      // 3246 (solo prima parte numerica)
        trim($lotPart),
        ' ' . substr($lotPart, 2),             // con spazio iniziale (alcuni lotti Esolver)
    ]);

    echo "  CodGestionale: '$codGestionale'\n";
    echo "  LotPart originale: '$lotPart'\n\n";

    foreach ($variants as $v) {
        if ($v === '' || $v === '-') continue;
        $s = $sqlPdo->prepare("SELECT TOP 2 CodArt, RifLottoAlfab FROM MagProgrLotto WHERE RifLottoAlfab = ?");
        $s->execute([$v]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)) {
            echo "  *** TROVATO RifLottoAlfab='$v':\n";
            print_r($rows);
        } else {
            echo "  RifLottoAlfab='$v' → nessun risultato\n";
        }
    }

    // Cerca anche per CodArt simile alla descrizione
    echo "\n  Cerca CodArt LIKE '$codGestionale%':\n";
    $s = $sqlPdo->prepare("SELECT TOP 3 CodArt, RifLottoAlfab FROM MagProgrLotto WHERE CodArt LIKE ?");
    $s->execute([$codGestionale . '%']);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    if ($rows) print_r($rows); else echo "  nessun risultato\n";

    echo "\n";
}

// Mostra formato reale dei lotti in Esolver (ultimi inseriti)
echo "=== CAMPIONE LOTTI ESOLVER (ordinati per ID desc) ===\n";
$s = $sqlPdo->query("SELECT TOP 10 CodArt, RifLottoAlfab FROM MagProgrLotto WHERE RifLottoAlfab <> '' ORDER BY CodArt");
print_r($s->fetchAll(PDO::FETCH_ASSOC));

echo '</pre>';
