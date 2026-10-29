<?php declare(strict_types=1);

use Database\Constants\MigrationConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Material (Materiale) and Stock (Giacenza), domain.md §3.5 and §3.11.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(
            'materials',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->string('name');

                $table->string('category', MigrationConstants::SHORT_STRING_MAX_LENGTH);

                $table->string('kind', MigrationConstants::SHORT_STRING_MAX_LENGTH);

                $table->string('base_unit', MigrationConstants::VERY_SHORT_STRING_MAX_LENGTH);

                $table->decimal('package_size', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::QUANTITY_SCALE);

                $table->string('package_label', MigrationConstants::MEDIUM_STRING_MAX_LENGTH);

                $table->decimal('list_price', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->nullable();

                $table->string('discount_notes', MigrationConstants::SHORT_STRING_MAX_LENGTH)
                      ->nullable();

                // Paid per package, VAT included: the only price used by the calculator
                $table->decimal('cost_price', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->nullable();

                $table->boolean('shipping_excluded')
                      ->default(false);

                $table->decimal('coverage_min', MigrationConstants::COVERAGE_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->nullable();

                $table->decimal('coverage_max', MigrationConstants::COVERAGE_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->nullable();

                $table->string('supplier')
                      ->nullable();

                $table->decimal('min_stock', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->default(0);

                $table->text('notes')
                      ->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'category']);
            }
        );

        Schema::create(
            'stocks',
            static function (Blueprint $table): void {
                $table->foreignUuid('material_id')
                      ->primary()
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->unsignedInteger('closed_packages')
                      ->default(0);

                $table->unsignedInteger('opened_packages')
                      ->default(0);

                $table->timestamps();
            }
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('materials');
    }
};
