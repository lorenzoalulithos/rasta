<?php declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\ConsumptionMode;
use App\Models\Database\v1\Job;
use App\Models\Database\v1\JobMaterial;
use App\Models\Database\v1\Material;
use App\Models\Database\v1\Stock;
use App\Models\Database\v1\Tenant;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use const JSON_THROW_ON_ERROR;

/**
 * Loads the painter catalog of docs/seed into a tenant (domain.md §8): materials with
 * an empty stock, jobs (only the v1 ones active) and their suggested materials.
 */
final class PainterCatalogImporter
{
    /**
     * Default json_decode depth, the seed files are two levels deep.
     *
     * @var int
     */
    private const JSON_MAX_DEPTH = 512;

    /**
     * @param string $seedPath folder holding materials.json, jobs.json, job_materials.json
     */
    public function __construct(private readonly string $seedPath)
    {
    }

    /**
     * True when the tenant already has jobs or materials: the seed is only for an empty catalog.
     *
     * @param Tenant $tenant
     *
     * @return bool
     */
    public function hasCatalog(Tenant $tenant): bool
    {
        return Job::withoutGlobalScopes()->whereBelongsTo($tenant)->exists()
            || Material::withoutGlobalScopes()->whereBelongsTo($tenant)->exists();
    }

    /**
     * @param Tenant $tenant
     *
     * @throws JsonException
     *
     * @return array{jobs: int, materials: int}
     */
    public function import(Tenant $tenant): array
    {
        $materials = $this->read('materials.json');
        $jobs = $this->read('jobs.json');
        $suggestions = $this->read('job_materials.json');

        return DB::transaction(function () use ($tenant, $materials, $jobs, $suggestions): array {
            $materialIds = $this->importMaterials($tenant, $materials);
            $jobIds = $this->importJobs($tenant, $jobs);
            $this->importSuggestions($tenant, $suggestions, $jobIds, $materialIds);

            return ['jobs' => count($jobIds), 'materials' => count($materials)];
        });
    }

    /**
     * @param Tenant                           $tenant
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<string, string> material id by name (the first one wins on duplicates)
     */
    private function importMaterials(Tenant $tenant, array $rows): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $material = Material::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $row['name'],
                'category' => $row['category'],
                'kind' => $row['kind'],
                'base_unit' => $row['base_unit'],
                'package_size' => $row['package_size'],
                'package_label' => $row['package_label'],
                'list_price' => $row['list_price'] ?? null,
                'discount_notes' => $row['discount_notes'] ?? null,
                'cost_price' => $row['cost_price'] ?? null,
                'shipping_excluded' => $row['shipping_excluded'] ?? false,
                'coverage_min' => $row['coverage_min'] ?? null,
                'coverage_max' => $row['coverage_max'] ?? null,
                'supplier' => $row['supplier'] ?? null,
                'min_stock' => $row['min_stock'] ?? 0,
            ]);

            Stock::query()->create(['material_id' => $material->id, 'tenant_id' => $tenant->id]);

            $ids[(string) $row['name']] ??= $material->id;
        }

        return $ids;
    }

    /**
     * @param Tenant                           $tenant
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<string, string> job id by name
     */
    private function importJobs(Tenant $tenant, array $rows): array
    {
        $ids = [];

        foreach (array_values($rows) as $position => $row) {
            $job = Job::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $row['name'],
                'unit' => $row['unit'],
                'unit_price' => $row['unit_price'],
                'default_coats' => $row['default_coats'] ?? null,
                'avg_time_notes' => $row['avg_time_notes'] ?? null,
                'sort_order' => $position,
                'active' => $row['active'] ?? false,
            ]);

            $ids[(string) $row['name']] = $job->id;
        }

        return $ids;
    }

    /**
     * @param Tenant                           $tenant
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, string>            $jobIds
     * @param array<string, string>            $materialIds
     *
     * @return void
     */
    private function importSuggestions(Tenant $tenant, array $rows, array $jobIds, array $materialIds): void
    {
        foreach ($rows as $row) {
            $jobId = $jobIds[(string) $row['job']] ?? throw new RuntimeException("Seed: unknown job {$row['job']}");

            /** @var array<int, array<string, mixed>> $suggested */
            $suggested = $row['materials'];

            foreach (array_values($suggested) as $position => $item) {
                $materialId = $materialIds[(string) $item['material']]
                    ?? throw new RuntimeException("Seed: unknown material {$item['material']}");

                JobMaterial::query()->create([
                    'tenant_id' => $tenant->id,
                    'job_id' => $jobId,
                    'material_id' => $materialId,
                    'role' => $item['role'],
                    'consumption_mode' => $item['consumption_mode'] ?? ConsumptionMode::None->value,
                    'consumption_per_unit' => $item['consumption_per_unit'] ?? null,
                    'apply_coats' => $item['apply_coats'] ?? false,
                    'selected_by_default' => $item['selected_by_default'] ?? false,
                    'sort_order' => $position,
                ]);
            }
        }
    }

    /**
     * @param string $file
     *
     * @throws JsonException
     *
     * @return array<int, array<string, mixed>>
     */
    private function read(string $file): array
    {
        $path = mb_rtrim($this->seedPath, '/') . '/' . $file;
        $contents = file_get_contents($path);

        if (false === $contents) {
            throw new RuntimeException("Seed file not readable: {$path}");
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = json_decode($contents, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);

        return $rows;
    }
}
