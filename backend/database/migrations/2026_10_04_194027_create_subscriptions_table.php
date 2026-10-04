<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// is_current bernilai 1 hanya untuk subscription aktif (ends_at null) dan NULL untuk riwayat,
// sehingga unique (customer_id, is_current) menjamin satu subscription aktif per pelanggan.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('price');
            $table->unsignedTinyInteger('billing_day');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->foreignId('next_package_id')->nullable()->constrained('packages')->restrictOnDelete();
            $table->boolean('is_current')->nullable()->storedAs('IF(ends_at IS NULL, 1, NULL)');
            $table->timestamps();

            $table->unique(['customer_id', 'is_current']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
