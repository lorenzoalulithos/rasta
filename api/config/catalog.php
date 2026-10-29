<?php declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Seed catalog
    |--------------------------------------------------------------------------
    |
    | Folder with the normalized price list of the partner (docs/seed/*.json):
    | materials.json, jobs.json, job_materials.json. See domain.md §8.
    |
    */

    'seed_path' => env('CATALOG_SEED_PATH', base_path('../docs/seed')),
];
