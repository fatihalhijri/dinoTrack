<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->string('gateway');
            $table->string('order_id')->unique();
            $table->unsignedBigInteger('amount');
            $table->text('qr_string')->nullable();
            $table->string('qr_url')->nullable();
            $table->string('status')->index();
            $table->timestamp('expires_at')->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'attempt']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_charges');
    }
};
