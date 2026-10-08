<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tanda untuk admin: perintah router untuk pelanggan ini gagal setelah semua percobaan job habis.
// Diisi oleh failed() job router dan dikosongkan saat perintah router berikutnya berhasil.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('network_error_at')->nullable()->after('terminated_at')->index();
            $table->string('network_error')->nullable()->after('network_error_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['network_error_at']);
            $table->dropColumn(['network_error_at', 'network_error']);
        });
    }
};
