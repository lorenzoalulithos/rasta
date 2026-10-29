<?php declare(strict_types=1);

use Database\Constants\MigrationConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Purchase (Acquisto), PurchaseLine and StockMovement, domain.md §3.12–3.13.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(
            'purchases',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->string('supplier')
                      ->nullable();

                $table->date('purchased_at');

                $table->boolean('paid')
                      ->default(false);

                $table->decimal('total', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE)
                      ->nullable();

                $table->text('notes')
                      ->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'paid']);
            }
        );

        Schema::create(
            'purchase_lines',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('purchase_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('material_id')
                      ->constrained()
                      ->restrictOnDelete();

                $table->unsignedInteger('packages');

                $table->decimal('unit_cost', MigrationConstants::DECIMAL_PRECISION, MigrationConstants::MONEY_SCALE);

                $table->timestamps();
            }
        );

        Schema::create(
            'stock_movements',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('material_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->string('type', MigrationConstants::SHORT_STRING_MAX_LENGTH);

                // Deltas can be negative (consumption, adjustment)
                $table->integer('closed_delta')
                      ->default(0);

                $table->integer('opened_delta')
                      ->default(0);

                $table->foreignUuid('quote_id')
                      ->nullable()
                      ->constrained()
                      ->nullOnDelete();

                $table->foreignUuid('purchase_id')
                      ->nullable()
                      ->constrained()
                      ->nullOnDelete();

                $table->string('notes')
                      ->nullable();

                $table->dateTime('occurred_at');

                $table->timestamps();

                $table->index(['material_id', 'occurred_at']);
            }
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('purchases');
    }
};
