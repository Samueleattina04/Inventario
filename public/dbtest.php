<?php
// FILE DI TEST TEMPORANEO - ELIMINARE DOPO L'USO
echo '<pre>';

$accessDsn = 'Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=\\\\192.168.3.208\\omni\\OMNITRACK1.3_be.accdb;';
$qrSamples = ['102712', '105279'];

// ── 1. Prova varianti lotto su SQL Server ─────────────────────────────────
echo "=== TEST VARIANTI LOTTO SU ESOLVER ===\n";
try {
    $sqlPdo = new PDO("sqlsrv:Server=SERVER2019\\SISTEMI;Database=ESOLVER;TrustServerCertificate=1;Encrypt=0", 'ricercalotti', 'RicercaLotti2024!');

    foreach ($qrSamples as $lot) {
        $variants = [
            $lot,                            // 102712
            ltrim($lot, '10'),               // 2712 / 5279 (rimuovi '10' da sinistra)
            substr($lot, 2),                 // 2712 / 5279 (salta primi 2 chars)
            substr($lot, 1),                 // 02712 / 05279 (salta primo char)
            (string)(int)substr($lot, 2),    // 2712 / 5279 (int, niente zeri)
        ];
        $variants = array_unique($variants);

        echo "\nLotto QR: $lot\n";
        foreach ($variants as $v) {
            $s = $sqlPdo->prepare("SELECT TOP 3 CodArt, RifLottoAlfab FROM MagProgrLotto WHERE RifLottoAlfab = ?");
            $s->execute([$v]);
            $rows = $s->fetchAll(PDO::FETCH_ASSOC);
            echo "  RifLottoAlfab='$v' → " . (count($rows) ? print_r($rows, true) : "nessun risultato\n");
        }
    }

    // Mostra campione di RifLottoAlfab per capire il formato
    echo "\nCampione RifLottoAlfab (tutti i formati presenti):\n";
    $s = $sqlPdo->query("SELECT DISTINCT TOP 20 RifLottoAlfab, CodArt FROM MagProgrLotto ORDER BY RifLottoAlfab");
    print_r($s->fetchAll(PDO::FETCH_ASSOC));

} catch (Throwable $e) {
    echo "ERRORE SQL Server: " . $e->getMessage() . "\n";
}

// ── 2. Prova tabelle lotti in Access (senza MSysObjects) ──────────────────
echo "\n=== TEST TABELLE LOTTI IN ACCESS ===\n";
$possibleLotTables = [
    'T_LOTTI', 'T_Lotti', 'T_LOTTO', 'T_Lotto',
    'LOTTI', 'Lotti', 'MagLotti',
    'T_MOVIMENTI_MP', 'T_MOVIMENTI',
    'T_CARICHI_MP', 'T_CARICHI',
    'T_SCARICHI_MP', 'T_SCARICHI',
    'T_INVENTARIO', 'T_REGISTRO_LOTTI',
    'T_TRACCIABILITA', 'T_TRACCIAB',
];
try {
    $accPdo = new PDO('odbc:' . $accessDsn);
    $accPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    foreach ($possibleLotTables as $tbl) {
        try {
            $s = $accPdo->query("SELECT COUNT(*) FROM [$tbl]");
            $count = $s->fetchColumn();
            echo "  ESISTE: [$tbl] — $count righe\n";

            // Mostra colonne
            $s = $accPdo->query("SELECT TOP 1 * FROM [$tbl]");
            $cols = [];
            for ($i = 0; $i < $s->columnCount(); $i++) $cols[] = $s->getColumnMeta($i)['name'];
            echo "    Colonne: " . implode(', ', $cols) . "\n";

            // Cerca i nostri numeri lotto
            foreach ($cols as $col) {
                foreach ($qrSamples as $lid) {
                    try {
                        $q = $accPdo->prepare("SELECT TOP 1 * FROM [$tbl] WHERE [$col] = ?");
                        $q->execute([$lid]);
                        $row = $q->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            echo "    *** TROVATO [$col]='$lid':\n";
                            print_r($row);
                        }
                    } catch (Throwable) {}
                }
            }
        } catch (Throwable) {
            // tabella non esiste, skip
        }
    }
} catch (Throwable $e) {
    echo "ERRORE Access: " . $e->getMessage() . "\n";
}

// ── 3. Cerca articolo per testo QR in Access ──────────────────────────────
echo "\n=== CERCA ARTICOLO PER TESTO QR IN ACCESS ===\n";
$textParts = ['PISTACCSGU', 'PISTACCSGUSCINTEROESTE', 'USASGUSCROTT'];
try {
    $accPdo = new PDO('odbc:' . $accessDsn);
    foreach ($textParts as $q) {
        $like = '%' . $q . '%';
        $s = $accPdo->prepare("SELECT TOP 3 [IDIngrediente],[Nome comerc Ingrediente],[CodGestionale],[UM] FROM [T_INGREDIENTI] WHERE [CodGestionale] LIKE ? OR [Nome comerc Ingrediente] LIKE ?");
        $s->execute([$like, $like]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        echo "  '$q': " . (count($rows) ? print_r($rows, true) : "nessun risultato\n");
    }
} catch (Throwable $e) {
    echo "ERRORE Access: " . $e->getMessage() . "\n";
}

echo '</pre>';
