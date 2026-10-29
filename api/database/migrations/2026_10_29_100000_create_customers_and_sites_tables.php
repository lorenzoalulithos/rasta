<?php declare(strict_types=1);

use Database\Constants\MigrationConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Customer (Cliente) and Site (Cantiere), domain.md §3.3–3.4.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(
            'customers',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->string('name');

                $table->string('tax_code', MigrationConstants::VAT_LENGTH)
                      ->nullable();

                $table->string('vat_number', MigrationConstants::VAT_LENGTH)
                      ->nullable();

                $table->string('email')
                      ->nullable();

                $table->string('phone', MigrationConstants::SHORT_STRING_MAX_LENGTH)
                      ->nullable();

                $table->string('address')
                      ->nullable();

                $table->string('zip', MigrationConstants::CAP_STRING_LENGTH)
                      ->nullable();

                $table->string('city')
                      ->nullable();

                $table->string('province', MigrationConstants::VERY_SHORT_STRING_MAX_LENGTH)
                      ->nullable();

                $table->text('notes')
                      ->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'name']);
            }
        );

        Schema::create(
            'sites',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('customer_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->string('name');

                $table->string('address')
                      ->nullable();

                $table->string('zip', MigrationConstants::CAP_STRING_LENGTH)
                      ->nullable();

                $table->string('city')
                      ->nullable();

                $table->string('province', MigrationConstants::VERY_SHORT_STRING_MAX_LENGTH)
                      ->nullable();

                $table->text('notes')
                      ->nullable();

                $table->timestamps();
            }
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
        Schema::dropIfExists('customers');
    }
};
