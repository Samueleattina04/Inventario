<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

class ArticleLookupService
{
    // ── Entry point ───────────────────────────────────────────────────────────

    public function lookup(string $scanned): array
    {
        $scanned = trim($scanned);

        // Access lots may embed expiry date: "{lot};dd/mm/yyyy"
        $accessExpiry = null;
        if (str_contains($scanned, ';')) {
            [$scanned, $rawDate] = explode(';', $scanned, 2);
            try {
                $accessExpiry = \Carbon\Carbon::createFromFormat('d/m/Y', trim($rawDate))->format('Y-m-d');
            } catch (Throwable) {}
        }

        // QR format:  {digits}{CodGestionale}#{lotId}  e.g. 241PISTACCSGUSCINTEROESTE-SL#102712
        if (str_contains($scanned, '#')) {
            $result = $this->lookupQr($scanned);
            return $this->mergeAccessExpiry($result, $accessExpiry);
        }

        // Barcode/manual format:  {id}.{rest}  or  {id}-{rest}
        [$id, $type] = $this->parseLot($scanned);

        if ($type !== 'invalid') {
            $result = $this->lookupEsolverByFullLot($scanned, $id);
            if ($result['found']) return $this->mergeAccessExpiry($result, $accessExpiry);

            $result = $this->lookupAccessById($scanned, $id, $type);
            if ($result['found']) return $this->mergeAccessExpiry($result, $accessExpiry);
        }

        // Fallback: treat input as a direct article code
        $result = $this->lookupByArticleCode($scanned);
        if ($result['found']) return $this->mergeAccessExpiry($result, $accessExpiry);

        return $this->notFound($scanned);
    }

    private function mergeAccessExpiry(array $result, ?string $accessExpiry): array
    {
        if ($accessExpiry !== null && empty($result['expiry_date'])) {
            $result['expiry_date'] = $accessExpiry;
        }
        return $result;
    }

    // ── QR lookup ─────────────────────────────────────────────────────────────

    private function lookupQr(string $scanned): array
    {
        [$articlePart, $lotPart] = explode('#', $scanned, 2);

        $codGestionale = ltrim($articlePart, '0123456789');
        $esolverLot    = strlen($lotPart) > 2 ? substr($lotPart, 2) : $lotPart;

        Log::info('QR lookup', ['raw' => $scanned, 'cod_gestionale' => $codGestionale, 'esolver_lot' => $esolverLot]);

        // 1. Esolver: cerca lotto + CodArt esatto, o lotto univoco
        $esolverCodArt = $this->lookupEsolverLot($esolverLot, $codGestionale);

        Log::info('QR Esolver result', ['esolver_cod_art' => $esolverCodArt]);

        // 2. Access: cerca descrizione e UM per CodGestionale
        $accessResult = $this->lookupAccessByCodGestionale($codGestionale, $scanned);

        // Dati articolo da ArtAnagrafica (validi anche se il lotto non è in MagProgrLotto)
        $esolverData = $this->getEsolverArticleData($esolverCodArt ?? $codGestionale);

        if ($esolverCodArt) {
            return [
                'found'        => true,
                'source'       => $accessResult['found'] ? 'sqlsrv+access' : 'sqlsrv',
                'article_code' => $esolverCodArt,
                'description'  => $esolverData['description'] ?: ($accessResult['description'] ?? ''),
                'um'           => $esolverData['um'] ?: ($accessResult['um'] ?? ''),
                'lot'          => $esolverLot,
                'lot_match'    => true,
                'expiry_date'  => $this->getEsolverLotExpiry($esolverCodArt, $esolverLot),
            ];
        }

        if ($accessResult['found']) {
            return array_merge($accessResult, [
                'lot'          => $esolverLot,
                'description'  => $esolverData['description'] ?: $accessResult['description'],
                'um'           => $esolverData['um'] ?: $accessResult['um'],
                'source'       => $esolverData['um'] ? 'sqlsrv+access' : 'access',
                'expiry_date'  => $esolverData['um'] ? $this->getEsolverLotExpiry($codGestionale, $esolverLot) : null,
            ]);
        }

        if ($esolverData['description'] || $esolverData['um']) {
            return [
                'found'        => true,
                'source'       => 'sqlsrv',
                'article_code' => $codGestionale,
                'description'  => $esolverData['description'],
                'um'           => $esolverData['um'],
                'lot'          => $esolverLot,
                'lot_match'    => false,
                'expiry_date'  => $this->getEsolverLotExpiry($codGestionale, $esolverLot),
            ];
        }

        return $this->notFound($scanned);
    }

    // ── Barcode / manual lookup ───────────────────────────────────────────────

    private function lookupEsolverByFullLot(string $fullLot, string $id): array
    {
        try {
            $row = DB::connection('articles_sqlsrv')
                ->table('MagProgrLotto')
                ->where('RifLottoAlfab', $fullLot)
                ->first();

            if ($row) {
                $codArt      = $row->CodArt ?? $id;
                $esolverData = $this->getEsolverArticleData($codArt);
                return [
                    'found'        => true,
                    'source'       => 'sqlsrv',
                    'article_code' => $codArt,
                    'description'  => $esolverData['description'],
                    'um'           => $esolverData['um'],
                    'lot'          => $fullLot,
                    'lot_match'    => true,
                    'expiry_date'  => $this->getEsolverLotExpiry($codArt, $fullLot),
                ];
            }
        } catch (Throwable $e) {
            Log::error('ArticleLookup Esolver error', ['lot' => $fullLot, 'error' => $e->getMessage()]);
        }

        return ['found' => false];
    }

    private function lookupAccessById(string $fullLot, string $id, string $type): array
    {
        try {
            $pdo = $this->accessPdo();

            if ($type === 'ingrediente') {
                $stmt = $pdo->prepare(
                    'SELECT [IDIngrediente],[Nome comerc Ingrediente],[CodGestionale],[UM]
                     FROM [T_INGREDIENTI] WHERE [IDIngrediente] = ?'
                );
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    return [
                        'found'        => true,
                        'source'       => 'access',
                        'article_code' => $row['CodGestionale'] ?? $id,
                        'description'  => $row['Nome comerc Ingrediente'] ?? '',
                        'um'           => $this->resolveUm($row['UM']),
                        'lot'          => $fullLot,
                        'lot_match'    => true,
                    ];
                }
            } elseif ($type === 'prodotto') {
                $stmt = $pdo->prepare(
                    'SELECT [IDProdottoAziendale],[Nome commerciale PA],[CodGestionale],[CodiceAziendale],[unimis]
                     FROM [T_PRODOTTI] WHERE [IDProdottoAziendale] = ?'
                );
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    return [
                        'found'        => true,
                        'source'       => 'access',
                        'article_code' => $row['CodGestionale'] ?: ($row['CodiceAziendale'] ?? $id),
                        'description'  => $row['Nome commerciale PA'] ?? '',
                        'um'           => $this->resolveUm($row['unimis']),
                        'lot'          => $fullLot,
                        'lot_match'    => true,
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::error('ArticleLookup Access error', ['lot' => $fullLot, 'id' => $id, 'error' => $e->getMessage()]);
        }

        return ['found' => false];
    }

    // ── QR helpers ────────────────────────────────────────────────────────────

    private function lookupAccessByCodGestionale(string $codGestionale, string $fullLot): array
    {
        try {
            $pdo = $this->accessPdo();

            $stmt = $pdo->prepare(
                'SELECT [IDIngrediente],[Nome comerc Ingrediente],[CodGestionale],[UM]
                 FROM [T_INGREDIENTI] WHERE [CodGestionale] = ?'
            );
            $stmt->execute([$codGestionale]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'found'        => true,
                    'source'       => 'access',
                    'article_code' => $row['CodGestionale'],
                    'description'  => $row['Nome comerc Ingrediente'] ?? '',
                    'um'           => $this->resolveUm($row['UM']),
                    'lot'          => $fullLot,
                    'lot_match'    => true,
                ];
            }

            $stmt = $pdo->prepare(
                'SELECT [IDProdottoAziendale],[Nome commerciale PA],[CodGestionale],[CodiceAziendale],[unimis]
                 FROM [T_PRODOTTI] WHERE [CodGestionale] = ?'
            );
            $stmt->execute([$codGestionale]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'found'        => true,
                    'source'       => 'access',
                    'article_code' => $row['CodGestionale'] ?: $row['CodiceAziendale'],
                    'description'  => $row['Nome commerciale PA'] ?? '',
                    'um'           => $this->resolveUm($row['unimis']),
                    'lot'          => $fullLot,
                    'lot_match'    => true,
                ];
            }
        } catch (Throwable $e) {
            Log::error('AccessLookupByCodGestionale error', ['cod' => $codGestionale, 'error' => $e->getMessage()]);
        }

        return ['found' => false];
    }

    private function lookupEsolverLot(string $esolverLot, string $codGestionale = ''): ?string
    {
        try {
            // 1. Exact match: lot + CodArt (fastest, unambiguous)
            if ($codGestionale !== '') {
                $row = DB::connection('articles_sqlsrv')
                    ->table('MagProgrLotto')
                    ->where('RifLottoAlfab', $esolverLot)
                    ->where('CodArt', $codGestionale)
                    ->first();

                if ($row) {
                    return $row->CodArt;
                }
            }

            // 2. Lot-only: retrieve all CodArt for this lot
            $codArts = DB::connection('articles_sqlsrv')
                ->table('MagProgrLotto')
                ->where('RifLottoAlfab', $esolverLot)
                ->distinct()
                ->pluck('CodArt');

            if ($codArts->isEmpty()) {
                return null;
            }

            // Prefer the one that starts with CodGestionale (partial match)
            if ($codGestionale !== '') {
                $preferred = $codArts->first(fn($c) => str_starts_with($c, $codGestionale) || str_starts_with($codGestionale, $c));
                if ($preferred) {
                    return $preferred;
                }
            }

            // Unique result is always safe
            if ($codArts->count() === 1) {
                return $codArts->first();
            }

            // Multiple CodArt for same lot and no match with CodGestionale → ambiguous
            Log::warning('EsolverLotLookup ambiguous', ['lot' => $esolverLot, 'cod_gestionale' => $codGestionale, 'codarts' => $codArts->all()]);
            return null;
        } catch (Throwable $e) {
            Log::error('EsolverLotLookup error', ['lot' => $esolverLot, 'cod_gestionale' => $codGestionale, 'error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Esolver lot expiry ────────────────────────────────────────────────────

    private function getEsolverLotExpiry(string $codArt, string $rifLottoAlfa): ?string
    {
        if ($codArt === '' || $rifLottoAlfa === '') return null;
        try {
            $row = DB::connection('articles_sqlsrv')
                ->table('MagAnagrLotti')
                ->where('CodArt', $codArt)
                ->where('RifLottoAlfa', $rifLottoAlfa)
                ->select('DataScadenzaLotto')
                ->first();

            if ($row && $row->DataScadenzaLotto) {
                $date = \Carbon\Carbon::parse($row->DataScadenzaLotto);
                // 1800-01-01 = nessuna scadenza impostata in Esolver
                if ($date->year > 1800) {
                    return $date->format('Y-m-d');
                }
            }
        } catch (Throwable $e) {
            Log::error('EsolverLotExpiry error', ['cod' => $codArt, 'lot' => $rifLottoAlfa, 'error' => $e->getMessage()]);
        }
        return null;
    }

    // ── Esolver article master data ───────────────────────────────────────────

    private function getEsolverArticleData(string $codArt): array
    {
        try {
            $row = DB::connection('articles_sqlsrv')
                ->table('ArtAnagrafica')
                ->where('CodArt', $codArt)
                ->select('DesArt', 'DesEstesa', 'MagUm')
                ->first();

            if ($row) {
                return [
                    'description' => trim($row->DesArt ?? '') ?: trim($row->DesEstesa ?? ''),
                    'um'          => trim($row->MagUm ?? ''),
                ];
            }
        } catch (Throwable $e) {
            Log::error('EsolverArticleData error', ['cod' => $codArt, 'error' => $e->getMessage()]);
        }

        return ['description' => '', 'um' => ''];
    }

    // ── Article code direct lookup ────────────────────────────────────────────

    private function lookupByArticleCode(string $code): array
    {
        // 1. Esolver: cerca in ArtAnagrafica (anagrafica completa, include articoli senza lotti)
        try {
            $row = DB::connection('articles_sqlsrv')
                ->table('ArtAnagrafica')
                ->where('CodArt', $code)
                ->select('CodArt', 'DesArt', 'DesEstesa', 'MagUm')
                ->first();

            if ($row) {
                $description = trim($row->DesArt ?? '') ?: trim($row->DesEstesa ?? '');
                return [
                    'found'        => true,
                    'source'       => 'sqlsrv',
                    'article_code' => $row->CodArt,
                    'description'  => $description,
                    'um'           => trim($row->MagUm ?? ''),
                    'lot'          => '',
                    'lot_match'    => false,
                ];
            }
        } catch (Throwable $e) {
            Log::error('ArticleCodeLookup Esolver error', ['code' => $code, 'error' => $e->getMessage()]);
        }

        // 2. Access: fallback se non presente in Esolver
        $accessResult = $this->lookupAccessByCodGestionale($code, '');
        if ($accessResult['found']) {
            return array_merge($accessResult, ['lot' => '', 'lot_match' => false]);
        }

        return ['found' => false];
    }

    // ── Article search (not-found panel) ─────────────────────────────────────

    public function searchArticles(string $query): array
    {
        if (strlen($query) < 2) {
            return [];
        }

        $results = [];

        try {
            $pdo  = $this->accessPdo();
            $like = '%' . $query . '%';

            $stmt = $pdo->prepare(
                "SELECT [IDIngrediente],[Nome comerc Ingrediente],[CodGestionale],[UM]
                 FROM [T_INGREDIENTI]
                 WHERE [CodGestionale] LIKE ? OR [Nome comerc Ingrediente] LIKE ?
                 ORDER BY [CodGestionale]"
            );
            $stmt->execute([$like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $results[] = [
                    'code'        => $row['CodGestionale'] ?: (string) $row['IDIngrediente'],
                    'description' => $row['Nome comerc Ingrediente'] ?? '',
                    'um_label'    => $this->resolveUm($row['UM']),
                    'type'        => 'ingrediente',
                ];
            }

            $stmt = $pdo->prepare(
                "SELECT [IDProdottoAziendale],[Nome commerciale PA],[CodGestionale],[CodiceAziendale],[unimis]
                 FROM [T_PRODOTTI]
                 WHERE [CodGestionale] LIKE ? OR [CodiceAziendale] LIKE ? OR [Nome commerciale PA] LIKE ?
                 ORDER BY [CodiceAziendale]"
            );
            $stmt->execute([$like, $like, $like]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $code = $row['CodGestionale'] ?: ($row['CodiceAziendale'] ?: (string) $row['IDProdottoAziendale']);
                $results[] = [
                    'code'        => $code,
                    'description' => $row['Nome commerciale PA'] ?? '',
                    'um_label'    => $this->resolveUm($row['unimis']),
                    'type'        => 'prodotto',
                ];
            }
        } catch (Throwable $e) {
            Log::error('ArticleSearch error', ['q' => $query, 'error' => $e->getMessage()]);
        }

        return array_slice($results, 0, 40);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function parseLot(string $lot): array
    {
        if (str_contains($lot, '.')) {
            $parts = explode('.', $lot, 2);
            if ($parts[0] !== '' && ($parts[1] ?? '') !== '') {
                return [$parts[0], 'ingrediente'];
            }
            return [$lot, 'invalid'];
        }
        if (str_contains($lot, '-')) {
            $parts = explode('-', $lot, 2);
            if ($parts[0] !== '' && ($parts[1] ?? '') !== '') {
                return [$parts[0], 'prodotto'];
            }
            return [$lot, 'invalid'];
        }
        return [$lot, 'invalid'];
    }

    private function accessPdo(): PDO
    {
        $dsn      = config('database.access_odbc.dsn', '');
        $username = config('database.access_odbc.username', '') ?: null;
        $password = config('database.access_odbc.password', '') ?: null;

        $pdo = new PDO('odbc:' . $dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private function resolveUm(mixed $umId): string
    {
        return config('inventory.um_map', [])[(int) $umId] ?? 'PZ';
    }

    private function notFound(string $lot): array
    {
        return [
            'found'        => false,
            'source'       => 'not_found',
            'article_code' => '',
            'description'  => '',
            'um'           => '',
            'lot'          => $lot,
            'lot_match'    => false,
        ];
    }
}
