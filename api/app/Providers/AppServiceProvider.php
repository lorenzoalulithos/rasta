<?php declare(strict_types=1);

namespace App\Providers;

use App\Services\Catalog\PainterCatalogImporter;
use Illuminate\Support\ServiceProvider;

/**
 * Class AppServiceProvider.
 */
final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            PainterCatalogImporter::class,
            static fn (): PainterCatalogImporter => new PainterCatalogImporter((string) config('catalog.seed_path'))
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
