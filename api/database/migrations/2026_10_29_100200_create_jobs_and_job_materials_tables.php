<?php declare(strict_types=1);

use App\Enums\ConsumptionMode;
use Database\Constants\MigrationConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Job (Lavorazione) and JobMaterial (Materiale suggerito), domain.md §3.6–3.7.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(
            'jobs',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->string('name');

                $table->string('unit', MigrationConstants::VERY_SHORT_STRING_MAX_LENGTH);

                $table->decimal('unit_price', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE);

                $table->unsignedTinyInteger('default_coats')
                      ->nullable();

                $table->string('avg_time_notes')
                      ->nullable();

                $table->decimal('estimated_hours_per_unit', MigrationConstants::COVERAGE_PRECISION, MigrationConstants::QUANTITY_SCALE)
                      ->nullable();

                $table->boolean('show_on_quote_pdf')
                      ->default(true);

                $table->unsignedInteger('sort_order')
                      ->default(0);

                $table->boolean('active')
                      ->default(true);

                $table->timestamps();

                $table->index(['tenant_id', 'active', 'sort_order']);
            }
        );

        Schema::create(
            'job_materials',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('job_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('material_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->string('role', MigrationConstants::SHORT_STRING_MAX_LENGTH);

                $table->string('consumption_mode', MigrationConstants::SHORT_STRING_MAX_LENGTH)
                      ->default(ConsumptionMode::Coverage->value);

                $table->decimal('consumption_per_unit', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::QUANTITY_SCALE)
                      ->nullable();

                $table->boolean('apply_coats')
                      ->default(false);

                $table->boolean('selected_by_default')
                      ->default(false);

                $table->unsignedInteger('sort_order')
                      ->default(0);

                $table->timestamps();

                $table->unique(['job_id', 'material_id']);
            }
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('job_materials');
        Schema::dropIfExists('jobs');
    }
};
