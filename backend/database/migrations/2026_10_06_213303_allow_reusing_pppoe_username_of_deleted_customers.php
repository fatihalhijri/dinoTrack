<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// active_pppoe_username berisi pppoe_username untuk pelanggan yang tidak di-soft-delete dan NULL
// untuk yang terhapus, sehingga unique (router_id, active_pppoe_username) tetap menjamin satu
// username per router tetapi mengizinkan username pelanggan salah input dipakai lagi.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('active_pppoe_username')->nullable()
                ->storedAs('IF(deleted_at IS NULL, pppoe_username, NULL)')
                ->after('pppoe_username');

            // Dibuat sebelum unique lama dihapus agar foreign key router_id tetap punya index.
            $table->unique(['router_id', 'active_pppoe_username']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['router_id', 'pppoe_username']);
            $table->index(['router_id', 'pppoe_username']);
        });
    }

    /**
     * Reverse the migrations. Gagal jika username pelanggan terhapus sudah dipakai lagi.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->unique(['router_id', 'pppoe_username']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['router_id', 'pppoe_username']);
            $table->dropUnique(['router_id', 'active_pppoe_username']);
            $table->dropColumn('active_pppoe_username');
        });
    }
};
