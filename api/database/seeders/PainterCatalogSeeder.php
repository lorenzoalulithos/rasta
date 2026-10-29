<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Trade;
use App\Models\Database\v1\Tenant;
use App\Services\Catalog\PainterCatalogImporter;
use Illuminate\Database\Seeder;
use JsonException;

/**
 * Loads docs/seed into every painter tenant whose catalog is still empty.
 *
 * php artisan db:seed --class=PainterCatalogSeeder
 */
final class PainterCatalogSeeder extends Seeder
{
    /**
     * @param PainterCatalogImporter $importer
     *
     * @throws JsonException
     *
     * @return void
     */
    public function run(PainterCatalogImporter $importer): void
    {
        $tenants = Tenant::query()->where('trade', Trade::Painter)->get();

        foreach ($tenants as $tenant) {
            if ($importer->hasCatalog($tenant)) {
                $this->command?->line("Catalogo già presente per {$tenant->name}, salto.");

                continue;
            }

            $counts = $importer->import($tenant);
            $this->command?->info("{$tenant->name}: {$counts['jobs']} lavorazioni, {$counts['materials']} materiali.");
        }
    }
}
