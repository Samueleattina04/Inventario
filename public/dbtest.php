<?php
// FILE DI TEST TEMPORANEO - ELIMINARE DOPO L'USO
echo '<pre>';

$qrSamples = [
    '241PISTACCSGUSCINTEROESTE-SL#102712',
    '241USASGUSCROTT A#105279',
];

foreach ($qrSamples as $qr) {
    echo "══════════════════════════════════════\n";
    echo "QR: $qr\n";
    echo "══════════════════════════════════════\n";

    // Parse
    $parts      = explode('#', $qr, 2);
    $articlePart = $parts[0] ?? '';
    $lotPart     = $parts[1] ?? '';

    // Extract leading digits from article part
    preg_match('/^(\d+)/', $articlePart, $m);
    $leadingId   = $m[1] ?? '';
    $descPart    = ltrim($articlePart, '0123456789');

    echo "  Parte articolo : '$articlePart'\n";
    echo "  Parte lotto    : '$lotPart'\n";
    echo "  ID numerico    : '$leadingId'\n";
    echo "  Descrizione    : '$descPart'\n\n";

    // ── SQL SERVER ──────────────────────────────────────────────────────────
    echo "-- SQL Server --\n";
    try {
        $pdo = new PDO(
            "sqlsrv:Server=SERVER2019\\SISTEMI;Database=ESOLVER;TrustServerCertificate=1;Encrypt=0",
            'ricercalotti', 'RicercaLotti2024!'
        );

        // 1. Cerca lotto dopo #
        $st = $pdo->prepare("SELECT TOP 3 CodArt, RifLottoAlfab FROM MagProgrLotto WHERE RifLottoAlfab = ?");
        $st->execute([$lotPart]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        echo "  RifLottoAlfab='$lotPart'   → " . (count($rows) ? print_r($rows, true) : "nessun risultato\n");

        // 2. Cerca lotto intero
        $st = $pdo->prepare("SELECT TOP 3 CodArt, RifLottoAlfab FROM MagProgrLotto WHERE RifLottoAlfab = ?");
        $st->execute([$qr]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        echo "  RifLottoAlfab='$qr'   → " . (count($rows) ? print_r($rows, true) : "nessun risultato\n");

        // 3. Cerca CodArt con parte articolo
        $st = $pdo->prepare("SELECT TOP 3 CodArt, RifLottoAlfab FROM MagProgrLotto WHERE CodArt = ?");
        $st->execute([trim($articlePart)]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        echo "  CodArt='$articlePart' → " . (count($rows) ? print_r($rows, true) : "nessun risultato\n");

        // 4. Cerca CodArt con ID numerico
        if ($leadingId) {
            $st = $pdo->prepare("SELECT TOP 3 CodArt, RifLottoAlfab FROM MagProgrLotto WHERE CodArt LIKE ?");
            $st->execute([$leadingId . '%']);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            echo "  CodArt LIKE '$leadingId%'  → " . (count($rows) ? print_r($rows, true) : "nessun risultato\n");
        }

        // 5. Mostra prime righe MagProgrLotto per confronto
        $st = $pdo->query("SELECT TOP 5 CodArt, RifLottoAlfab FROM MagProgrLotto ORDER BY RifLottoAlfab DESC");
        echo "  Ultime righe MagProgrLotto:\n" . print_r($st->fetchAll(PDO::FETCH_ASSOC), true);

    } catch (Throwable $e) {
        echo "  ERRORE SQL Server: " . $e->getMessage() . "\n";
    }

    // ── ACCESS ──────────────────────────────────────────────────────────────
    echo "\n-- Access --\n";
    try {
        $dsn = 'Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=\\\\192.168.3.208\\omni\\OMNITRACK1.3_be.accdb;';
        $pdo = new PDO('odbc:' . $dsn);

        // 1. IDIngrediente = ID numerico
        if ($leadingId) {
            $st = $pdo->prepare("SELECT [IDIngrediente],[Nome comerc Ingrediente],[CodGestionale],[UM] FROM [T_INGREDIENTI] WHERE [IDIngrediente]=?");
            $st->execute([$leadingId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            echo "  T_INGREDIENTI IDIngrediente='$leadingId' → " . ($row ? print_r($row, true) : "nessun risultato\n");

            $st = $pdo->prepare("SELECT [IDProdottoAziendale],[Nome commerciale PA],[CodGestionale],[CodiceAziendale],[unimis] FROM [T_PRODOTTI] WHERE [IDProdottoAziendale]=?");
            $st->execute([$leadingId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            echo "  T_PRODOTTI IDProdottoAziendale='$leadingId' → " . ($row ? print_r($row, true) : "nessun risultato\n");
        }

        // 2. Cerca per descrizione
        if (strlen($descPart) >= 4) {
            $like = '%' . substr($descPart, 0, 10) . '%';
            $st = $pdo->prepare("SELECT TOP 3 [IDIngrediente],[Nome comerc Ingrediente],[CodGestionale] FROM [T_INGREDIENTI] WHERE [Nome comerc Ingrediente] LIKE ?");
            $st->execute([$like]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            echo "  T_INGREDIENTI desc LIKE '$like' → " . (count($rows) ? print_r($rows, true) : "nessun risultato\n");
        }

    } catch (Throwable $e) {
        echo "  ERRORE Access: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo '</pre>';
