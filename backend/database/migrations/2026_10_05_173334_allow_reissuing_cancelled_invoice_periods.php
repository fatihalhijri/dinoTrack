<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// billed_period_start berisi period_start untuk invoice yang tidak dibatalkan dan NULL untuk yang
// `cancelled`, sehingga unique (subscription_id, billed_period_start) tetap menjamin satu invoice
// aktif per periode tetapi mengizinkan periode yang invoice-nya dibatalkan diterbitkan ulang.
// Nilai 'cancelled' = InvoiceStatus::Cancelled.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('billed_period_start')->nullable()
                ->storedAs("IF(status <> 'cancelled', period_start, NULL)")
                ->after('period_end');

            // Dibuat sebelum unique lama dihapus agar foreign key subscription_id tetap punya index.
            $table->unique(['subscription_id', 'billed_period_start']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['subscription_id', 'period_start']);
            $table->index(['subscription_id', 'period_start']);
        });
    }

    /**
     * Reverse the migrations. Gagal jika sudah ada periode yang diterbitkan ulang.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['subscription_id', 'period_start']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['subscription_id', 'period_start']);
            $table->dropUnique(['subscription_id', 'billed_period_start']);
            $table->dropColumn('billed_period_start');
        });
    }
};
