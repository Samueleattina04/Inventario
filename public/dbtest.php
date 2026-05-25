<?php
// FILE DI TEST TEMPORANEO - ELIMINARE DOPO L'USO
echo '<pre>';

$accessDsn = 'Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=\\\\192.168.3.208\\omni\\OMNITRACK1.3_be.accdb;';

try {
    $pdo = new PDO('odbc:' . $accessDsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ── 1. Elenca tutte le tabelle del database Access ──────────────────────
    echo "=== TABELLE IN ACCESS ===\n";
    $stmt = $pdo->query("SELECT MSysObjects.Name FROM MSysObjects WHERE MSysObjects.Type=1 AND MSysObjects.Flags=0 ORDER BY MSysObjects.Name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) echo "  $t\n";
    echo "\n";

    // ── 2. Cerca il numero lotto (102712) nelle tabelle più probabili ───────
    $lotIds = ['102712', '105279'];
    $probableTables = ['T_LOTTI', 'T_Lotti', 'Lotti', 'MagLotti', 'T_MOVIMENTI', 'T_SCARICHI', 'T_CARICHI', 'T_INVENTARIO'];

    echo "=== CERCA LOTTI NELLE TABELLE ===\n";
    foreach ($probableTables as $tbl) {
        if (!in_array($tbl, $tables)) continue;
        try {
            $cols = $pdo->query("SELECT * FROM [$tbl] WHERE 1=0");
            $colNames = [];
            for ($i = 0; $i < $cols->columnCount(); $i++) {
                $colNames[] = $cols->getColumnMeta($i)['name'];
            }
            echo "  [$tbl] colonne: " . implode(', ', $colNames) . "\n";

            foreach ($lotIds as $lid) {
                foreach ($colNames as $col) {
                    try {
                        $s = $pdo->prepare("SELECT TOP 1 * FROM [$tbl] WHERE [$col] = ?");
                        $s->execute([$lid]);
                        $row = $s->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            echo "  TROVATO in [$tbl].[$col] = '$lid':\n";
                            print_r($row);
                        }
                    } catch (Throwable) {}
                }
            }
        } catch (Throwable $e) {
            echo "  Errore [$tbl]: " . $e->getMessage() . "\n";
        }
    }

    // ── 3. Cerca articolo per parte testuale del QR ─────────────────────────
    echo "\n=== CERCA ARTICOLO PER TESTO QR ===\n";
    $searches = [
        'PISTACCSGUSCINTEROESTE',
        'PISTACCSGU',
        'USASGUSCROTT',
    ];
    foreach ($searches as $q) {
        $like = '%' . $q . '%';
        $s = $pdo->prepare("SELECT TOP 3 [IDIngrediente],[Nome comerc Ingrediente],[CodGestionale],[UM] FROM [T_INGREDIENTI] WHERE [CodGestionale] LIKE ? OR [Nome comerc Ingrediente] LIKE ?");
        $s->execute([$like, $like]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        echo "  Cerca '$q':\n";
        if ($rows) print_r($rows); else echo "    nessun risultato\n";
    }

    // ── 4. Verifica CodGestionale su SQL Server ─────────────────────────────
    echo "\n=== VERIFICA CodGestionale SU ESOLVER ===\n";
    $codGest = ['MANSGUSPA', 'USASGUSCROTT-A-CIII'];
    try {
        $sqlPdo = new PDO("sqlsrv:Server=SERVER2019\\SISTEMI;Database=ESOLVER;TrustServerCertificate=1;Encrypt=0", 'ricercalotti', 'RicercaLotti2024!');
        foreach ($codGest as $cod) {
            $s = $sqlPdo->prepare("SELECT TOP 3 CodArt, RifLottoAlfab FROM MagProgrLotto WHERE CodArt = ?");
            $s->execute([$cod]);
            $rows = $s->fetchAll(PDO::FETCH_ASSOC);
            echo "  CodArt='$cod': " . (count($rows) ? print_r($rows, true) : "nessun risultato\n");

            $s = $sqlPdo->prepare("SELECT TOP 1 CodArt FROM MagProgrArticoli WHERE CodArt = ?");
            $s->execute([$cod]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            echo "  MagProgrArticoli CodArt='$cod': " . ($row ? print_r($row, true) : "nessun risultato\n");
        }
    } catch (Throwable $e) {
        echo "  Errore SQL Server: " . $e->getMessage() . "\n";
    }

} catch (Throwable $e) {
    echo "ERRORE: " . $e->getMessage() . "\n";
}

echo '</pre>';
