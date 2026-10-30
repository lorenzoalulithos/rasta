<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Quote numbering per tenant and year, domain.md §3.8 and §5.1: never goes back.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create(
            'quote_counters',
            static function (Blueprint $table): void {
                $table->foreignUuid('tenant_id')
                      ->constrained()
                      ->cascadeOnDelete();

                $table->unsignedSmallInteger('year');

                $table->unsignedInteger('last_number')
                      ->default(0);

                $table->primary(['tenant_id', 'year']);
            }
        );
    }

    /**
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_counters');
    }
};
