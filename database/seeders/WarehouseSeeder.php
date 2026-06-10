<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            ['code' => '01',   'name' => 'Magazzino MP e imballaggi generico'],
            ['code' => '104',  'name' => 'Capannone 104 - imballaggi generico'],
            ['code' => '135',  'name' => 'Capannone 135 - imballaggi generico'],
            ['code' => '42',   'name' => 'Magazzino deposito pelatura'],
            ['code' => '43',   'name' => 'Imballaggi cooperativa'],
            ['code' => '35',   'name' => 'Deposito Antichi Sapori a Brolo'],
            ['code' => '161',  'name' => 'Deposito Grassia'],
            ['code' => '61',   'name' => 'Deposito Archimede'],
            ['code' => '64',   'name' => 'Deposito Belpasso - Arema - Bofrost'],
            ['code' => '51',   'name' => 'Magazzino c/deposito Antichi'],
            ['code' => '60',   'name' => 'Magazzino c/deposito Brolo'],
            ['code' => 'TERZ', 'name' => 'Mag. c/lavoro passivo'],
            ['code' => '06',   'name' => 'Lievitati'],
            ['code' => '52',   'name' => 'Magazzino bordo linee'],
            ['code' => 'CONF', 'name' => 'Magazzino Reparto Confezionamento'],
            ['code' => '14',   'name' => 'Magazzino Scarti'],
        ];

        $areas = [
            '01' => [
                'Tenda 1', 'Tenda 2', 'Hangar', 'Area Esterna', 'Area Scarico',
                'Manutenzione', 'Cella 1', 'Cella 2', 'Stoccaggio Materie Prime',
                'Carico Batch', 'Gherry', 'Impasto Lievitati', 'Cottura Lievitati',
                'Farcitura Lievitati', 'Spalamento e Confezionamento',
                'Produzione Pasta Pura', 'Area Spedizioni',
                'Invasettamento Creme FMT/SBG1', 'Invasettamento Creme SBG2',
                'Cioccolateria', 'Biscotti', 'Flowpack', 'Corridoio MP e SL',
                'Confezionamento Pistì', 'Cella Pistì', 'Confezionamento Vincente',
                'Corridoio o Imballi',
            ],
            '43' => [
                'Cella 1', 'Cella 2', 'Cella 3', 'Cella 4', 'Cella 5',
                'Cella 6', 'Cella 7', 'Cella 8', 'Cella 9', 'Cella 10',
                'Cella 11', 'Container 1', 'Container 2', 'Container 3',
                'Container 4', 'Container 5', 'Container 6', 'Area Esterna',
            ],
        ];

        foreach ($warehouses as $data) {
            $warehouse = Warehouse::firstOrCreate(
                ['code' => $data['code']],
                ['name' => $data['name'], 'active' => true]
            );

            if (isset($areas[$data['code']])) {
                foreach ($areas[$data['code']] as $i => $areaName) {
                    $code = $data['code'] . '-' . str_pad($i + 1, 2, '0', STR_PAD_LEFT);
                    Area::firstOrCreate(
                        ['warehouse_id' => $warehouse->id, 'name' => $areaName],
                        ['code' => $code, 'active' => true]
                    );
                }
            }
        }
    }
}
