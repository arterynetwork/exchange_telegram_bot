<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCurrenciesToOrders extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('amount_byr', 20, 8)->default(0);
            $table->decimal('amount_uah', 20, 8)->default(0);
            $table->decimal('amount_kzt', 20, 8)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('amount_byr');
            $table->dropColumn('amount_uah');
            $table->dropColumn('amount_kzt');
        });
    }
}
