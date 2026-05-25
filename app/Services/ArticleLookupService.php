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

        // QR format:  {digits}{CodGestionale}#{lotId}  e.g. 241PISTACCSGUSCINTEROESTE-SL#102712
        if (str_contains($scanned, '#')) {
            return $this->lookupQr($scanned);
        }

        // Barcode/manual format:  {id}.{rest}  or  {id}-{rest}
        [$id, $type] = $this->parseLot($scanned);

        if ($type === 'invalid') {
            return $this->notFound($scanned);
        }

        $result = $this->lookupEsolverByFullLot($scanned, $id);
        if ($result['found']) {
            return $result;
        }

        $result = $this->lookupAccessById($scanned, $id, $type);
        if ($result['found']) {
            return $result;
        }

        return $this->notFound($scanned);
    }

    // ── QR lookup ─────────────────────────────────────────────────────────────

    private function lookupQr(string $scanned): array
    {
        [$articlePart, $lotPart] = explode('#', $scanned, 2);

        $codGestionale = ltrim($articlePart, '0123456789');
        $esolverLot    = strlen($lotPart) > 2 ? substr($lotPart, 2) : $lotPart;

        // 1. Esolver: cerca lotto + CodArt esatto, o lotto univoco
        $esolverCodArt = $this->lookupEsolverLot($esolverLot, $codGestionale);

        // 2. Access: cerca descrizione e UM per CodGestionale
        $accessResult = $this->lookupAccessByCodGestionale($codGestionale, $scanned);

        if ($esolverCodArt) {
            // Esolver trovato: usa CodArt di Esolver, descrizione da Access se disponibile
            return [
                'found'        => true,
                'source'       => $accessResult['found'] ? 'sqlsrv+access' : 'sqlsrv',
                'article_code' => $esolverCodArt,
                'description'  => $accessResult['description'] ?? '',
                'um'           => $accessResult['um'] ?? '',
                'lot'          => $scanned,
                'lot_match'    => true,
            ];
        }

        if ($accessResult['found']) {
            return $accessResult;
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
                return [
                    'found'        => true,
                    'source'       => 'sqlsrv',
                    'article_code' => $row->CodArt ?? $id,
                    'description'  => '',
                    'um'           => '',
                    'lot'          => $fullLot,
                    'lot_match'    => true,
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
            $db = DB::connection('articles_sqlsrv')->table('MagProgrLotto');

            // 1. Exact match: lot + CodArt
            if ($codGestionale !== '') {
                $row = (clone $db)
                    ->where('RifLottoAlfab', $esolverLot)
                    ->where('CodArt', $codGestionale)
                    ->first();

                if ($row) {
                    return $row->CodArt;
                }
            }

            // 2. Lot-only match — safe only if result is unambiguous
            $rows = (clone $db)
                ->where('RifLottoAlfab', $esolverLot)
                ->distinct()
                ->pluck('CodArt');

            if ($rows->count() === 1) {
                return $rows->first();
            }

            return null;
        } catch (Throwable $e) {
            Log::error('EsolverLotLookup error', ['lot' => $esolverLot, 'error' => $e->getMessage()]);
            return null;
        }
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
