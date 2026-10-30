<?php declare(strict_types=1);

use Database\Constants\MigrationConstants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * v1.1 entities, domain.md §3.14: migrations only, no models or code in v1.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(
            'calendar_events',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('site_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('quote_id')
                      ->nullable()
                      ->constrained()
                      ->nullOnDelete();

                $table->date('date');

                $table->time('start_time')
                      ->nullable();

                $table->time('end_time')
                      ->nullable();

                $table->text('notes')
                      ->nullable();

                $table->timestamps();

                $table->index(['tenant_id', 'date']);
            }
        );

        Schema::create(
            'delivery_notes',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                // Destination is the site
                $table->foreignUuid('site_id')
                      ->constrained()
                      ->restrictOnDelete();

                $table->foreignUuid('quote_id')
                      ->nullable()
                      ->constrained()
                      ->nullOnDelete();

                $table->unsignedInteger('number');

                $table->unsignedSmallInteger('year');

                $table->date('issued_at');

                $table->string('reason', MigrationConstants::MEDIUM_STRING_MAX_LENGTH);

                // The recipient is not always the customer (partner's DDT sample): free fields
                $table->string('recipient_name')
                      ->nullable();

                $table->string('recipient_tax_code', MigrationConstants::VAT_LENGTH)
                      ->nullable();

                $table->string('recipient_vat_number', MigrationConstants::VAT_LENGTH)
                      ->nullable();

                $table->string('recipient_address')
                      ->nullable();

                // Luogo di destinazione: the site address unless overridden
                $table->string('destination_address')
                      ->nullable();

                // Trasporto a mezzo: sender, carrier or recipient
                $table->string('transport_by', MigrationConstants::SHORT_STRING_MAX_LENGTH)
                      ->nullable();

                $table->string('carrier')
                      ->nullable();

                $table->dateTime('pickup_at')
                      ->nullable();

                $table->string('sender_signature')
                      ->nullable();

                $table->string('carrier_signature')
                      ->nullable();

                $table->string('recipient_signature')
                      ->nullable();

                $table->text('notes')
                      ->nullable();

                $table->timestamps();

                $table->unique(['tenant_id', 'year', 'number']);
            }
        );

        Schema::create(
            'delivery_note_lines',
            static function (Blueprint $table): void {
                $table->uuid('id')
                      ->primary();

                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('delivery_note_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->foreignUuid('material_id')
                      ->nullable()
                      ->constrained()
                      ->nullOnDelete();

                $table->string('description');

                // Free text unit, e.g. "Bidoni da 14lt"
                $table->string('unit', MigrationConstants::SHORT_STRING_MAX_LENGTH)
                      ->nullable();

                // Free text quantity, e.g. "1 e 1/2"
                $table->string('quantity', MigrationConstants::SHORT_STRING_MAX_LENGTH);

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
        Schema::dropIfExists('delivery_note_lines');
        Schema::dropIfExists('delivery_notes');
        Schema::dropIfExists('calendar_events');
    }
};
