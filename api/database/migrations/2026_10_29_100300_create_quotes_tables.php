<?php declare(strict_types=1);

use App\Enums\ConsumptionMode;
use App\Enums\JobMaterialRole;
use App\Enums\QuoteStatus;
use Database\Constants\MigrationConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Quote (Preventivo), QuoteLine (Riga) and QuoteLineMaterial, domain.md §3.8–3.10.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(
            'quotes',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('site_id')
                      ->constrained()
                      ->restrictOnDelete();

                $table->unsignedInteger('number');

                $table->unsignedSmallInteger('year');

                $table->date('issued_at');

                $table->date('expires_at');

                $table->string('status', MigrationConstants::SHORT_STRING_MAX_LENGTH)
                      ->default(QuoteStatus::Draft->value);

                $table->decimal('vat_rate', MigrationConstants::RATE_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->default(0);

                $table->string('payment_terms')
                      ->nullable();

                $table->text('footer_text')
                      ->nullable();

                $table->text('private_notes')
                      ->nullable();

                $table->decimal('subtotal', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->default(0);

                $table->decimal('vat_amount', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->default(0);

                $table->decimal('total', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->default(0);

                $table->decimal('estimated_cost', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->default(0);

                $table->decimal('estimated_margin', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->default(0);

                $table->dateTime('accepted_at')
                      ->nullable();

                $table->dateTime('completed_at')
                      ->nullable();

                $table->timestamps();

                $table->unique(['tenant_id', 'year', 'number']);
                $table->index(['tenant_id', 'status']);
            }
        );

        Schema::create(
            'quote_lines',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('quote_id')
                      ->constrained()
                      ->cascadeOnDelete();

                // Null for free lines (riga libera)
                $table->foreignUuid('job_id')
                      ->nullable()
                      ->constrained()
                      ->nullOnDelete();

                $table->string('description');

                $table->string('unit', MigrationConstants::VERY_SHORT_STRING_MAX_LENGTH);

                $table->decimal('quantity', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE);

                $table->unsignedTinyInteger('coats')
                      ->nullable();

                $table->decimal('unit_price', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE);

                $table->decimal('amount', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE);

                $table->unsignedInteger('sort_order')
                      ->default(0);

                $table->timestamps();
            }
        );

        Schema::create(
            'quote_line_materials',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('quote_line_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('material_id')
                      ->constrained()
                      ->restrictOnDelete();

                // Consumption rule snapshot: hand-added materials have no JobMaterial (domain.md §3.10)
                $table->string('role', MigrationConstants::SHORT_STRING_MAX_LENGTH)
                      ->default(JobMaterialRole::Secondary->value);

                $table->string('consumption_mode', MigrationConstants::SHORT_STRING_MAX_LENGTH)
                      ->default(ConsumptionMode::Coverage->value);

                $table->decimal('consumption_per_unit', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::QUANTITY_SCALE)
                      ->nullable();

                $table->boolean('apply_coats')
                      ->default(false);

                $table->boolean('selected')
                      ->default(true);

                $table->decimal('coverage_used', MigrationConstants::COVERAGE_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->nullable();

                $table->decimal('required_quantity', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::QUANTITY_SCALE);

                $table->decimal('required_packages', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::QUANTITY_SCALE);

                $table->decimal('unit_cost', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE);

                $table->decimal('estimated_cost', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE);

                $table->unsignedInteger('sort_order')
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
        Schema::dropIfExists('quote_line_materials');
        Schema::dropIfExists('quote_lines');
        Schema::dropIfExists('quotes');
    }
};
