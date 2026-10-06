<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Laporan pergerakan pelanggan (baru, berhenti, terisolir) membaca riwayat dari activity_logs
// karena kolom status di customers ditimpa saat status berubah lagi. Query-nya selalu berbentuk
// `action = ? AND created_at` dalam rentang, sehingga tanpa index ini seluruh log dipindai.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['action', 'created_at']);
        });
    }
};
