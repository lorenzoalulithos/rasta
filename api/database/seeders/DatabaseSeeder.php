<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Database\v1\Tenant;
use App\Models\Database\v1\User;
use Illuminate\Database\Seeder;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

/**
 * Class DatabaseSeeder.
 */
final class DatabaseSeeder extends Seeder
{
    /**
     * Local tenant with the painter catalog: log in with test@example.com / password.
     *
     * @return void
     */
    public function run(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Ravizza Patrick']);

        User::factory()->for($tenant)->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(PainterCatalogSeeder::class);
    }
}
