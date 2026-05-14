<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Users
        User::create([
            'name'     => 'Amministratore',
            'username' => 'admin',
            'password' => Hash::make('admin123'),
            'role'     => 'admin',
            'active'   => true,
        ]);

        User::create([
            'name'     => 'Mario Rossi',
            'username' => 'operatore1',
            'password' => Hash::make('op123'),
            'role'     => 'operator',
            'active'   => true,
        ]);

        User::create([
            'name'     => 'Lucia Bianchi',
            'username' => 'operatore2',
            'password' => Hash::make('op123'),
            'role'     => 'operator',
            'active'   => true,
        ]);

        // Warehouses & Areas
        $magA = Warehouse::create(['name' => 'Magazzino A', 'code' => 'MAG-A', 'active' => true]);
        Area::create(['warehouse_id' => $magA->id, 'name' => 'Area 1',           'code' => 'A1',   'has_weight_calculator' => false, 'active' => true]);
        Area::create(['warehouse_id' => $magA->id, 'name' => 'Area 2',           'code' => 'A2',   'has_weight_calculator' => false, 'active' => true]);
        Area::create(['warehouse_id' => $magA->id, 'name' => 'Area Produzione',  'code' => 'PROD', 'has_weight_calculator' => true,  'active' => true]);

        $magB = Warehouse::create(['name' => 'Magazzino B', 'code' => 'MAG-B', 'active' => true]);
        Area::create(['warehouse_id' => $magB->id, 'name' => 'Scaffale Nord', 'code' => 'SN', 'has_weight_calculator' => false, 'active' => true]);
        Area::create(['warehouse_id' => $magB->id, 'name' => 'Scaffale Sud',  'code' => 'SS', 'has_weight_calculator' => false, 'active' => true]);

        $magExt = Warehouse::create(['name' => 'Magazzino Esterno', 'code' => 'MAG-EXT', 'active' => true]);
        Area::create(['warehouse_id' => $magExt->id, 'name' => 'Zona Ingresso', 'code' => 'ZI', 'has_weight_calculator' => false, 'active' => true]);
        Area::create(['warehouse_id' => $magExt->id, 'name' => 'Zona Uscita',   'code' => 'ZU', 'has_weight_calculator' => false, 'active' => true]);
    }
}
