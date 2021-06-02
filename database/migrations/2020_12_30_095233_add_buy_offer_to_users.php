<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBuyOfferToUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('buy_offer')->default(false);
            $table->bigInteger('buy_min')->default(0);
            $table->bigInteger('buy_total')->default(0);
            $table->decimal('buy_price', 8, 2)->default(18);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('buy_offer');
            $table->dropColumn('buy_min');
            $table->dropColumn('buy_total');
            $table->dropColumn('buy_price');
        });
    }
}
