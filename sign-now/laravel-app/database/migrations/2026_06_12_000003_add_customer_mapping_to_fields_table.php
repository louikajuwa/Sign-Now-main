<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->string('customer_key', 64)->nullable()->after('assigned_to');
            $table->string('customer_value', 500)->nullable()->after('customer_key');
            $table->text('default_value')->nullable()->after('customer_value');
        });
    }

    public function down(): void
    {
        Schema::table('fields', function (Blueprint $table) {
            $table->dropColumn(['customer_key', 'customer_value', 'default_value']);
        });
    }
};
