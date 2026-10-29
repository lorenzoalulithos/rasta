<?php declare(strict_types=1);

use App\Enums\CoverageStrategy;
use App\Enums\PackageRounding;
use App\Enums\TaxRegime;
use App\Enums\Trade;
use Database\Constants\MigrationConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Tenant (Azienda), domain.md §3.1. Created before users, which belong to it.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(
            'tenants',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->string('name');

                $table->string('address')
                      ->nullable();

                $table->string('zip', MigrationConstants::CAP_STRING_LENGTH)
                      ->nullable();

                $table->string('city')
                      ->nullable();

                $table->string('province', MigrationConstants::VERY_SHORT_STRING_MAX_LENGTH)
                      ->nullable();

                $table->string('vat_number', MigrationConstants::VAT_LENGTH)
                      ->nullable();

                $table->string('tax_code', MigrationConstants::VAT_LENGTH)
                      ->nullable();

                $table->string('email')
                      ->nullable();

                $table->string('phone', MigrationConstants::SHORT_STRING_MAX_LENGTH)
                      ->nullable();

                $table->string('logo_path')
                      ->nullable();

                $table->string('tax_regime', MigrationConstants::SHORT_STRING_MAX_LENGTH)
                      ->default(TaxRegime::Forfettario->value);

                $table->decimal('default_vat_rate', MigrationConstants::RATE_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->default(0);

                $table->unsignedSmallInteger('quote_validity_days')
                      ->default(90);

                $table->text('quote_footer_text')
                      ->nullable();

                $table->string('coverage_strategy', MigrationConstants::VERY_SHORT_STRING_MAX_LENGTH)
                      ->default(CoverageStrategy::Avg->value);

                $table->decimal('opened_package_ratio', MigrationConstants::RATIO_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->default(0.5);

                $table->string('package_rounding', MigrationConstants::SHORT_STRING_MAX_LENGTH)
                      ->default(PackageRounding::Up->value);

                $table->string('trade', MigrationConstants::SHORT_STRING_MAX_LENGTH)
                      ->default(Trade::Painter->value);

                $table->timestamps();
            }
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
