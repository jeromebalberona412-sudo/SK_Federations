<?php

namespace App\Modules\Accounts\Database\Seeders;

use App\Modules\Accounts\Models\Barangay;
use App\Modules\Shared\Models\Tenant;
use Illuminate\Database\Seeder;

class BarangaySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('code', 'santa_cruz')->first();

        if (! $tenant) {
            return;
        }

        self::seedTenant($tenant);
    }

    public static function seedTenant(Tenant $tenant): void
    {
        $barangays = self::defaultBarangays();

        foreach ($barangays as $name) {
            Barangay::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'name' => $name,
                ],
                [
                    'municipality' => 'Santa Cruz',
                    'province' => 'Laguna',
                    'region' => 'IV-A CALABARZON',
                ]
            );
        }
    }

    public static function defaultBarangays(): array
    {
        return [
            'Alipit',
            'Bagumbayan',
            'Bubukal',
            'Calios',
            'Duhat',
            'Gatid',
            'Jasaan',
            'Labuin',
            'Malinao',
            'Oogong',
            'Pagsawitan',
            'Palasan',
            'Patimbao',
            'Poblacion I',
            'Poblacion II',
            'Poblacion III',
            'Poblacion IV',
            'Poblacion V',
            'San Jose',
            'San Juan',
            'San Pablo Norte',
            'San Pablo Sur',
            'Santisima Cruz',
            'Santo Angel Central',
            'Santo Angel Norte',
            'Santo Angel Sur',
        ];
    }
}
