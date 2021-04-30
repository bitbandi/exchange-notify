<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('balances', function (Blueprint $table) {
            $table->string('exchange', 64);
            $table->string('account', 64)->nullable();
            $table->string('currency', 16);
            $table->unsignedDecimal('amount', 20, 8);
            $table->timestamps();
            $table->primary(['exchange', 'account', 'currency']);
        });
        Schema::create('trades', function (Blueprint $table) {
            $table->string('exchange', 64);
            $table->string('account', 64)->nullable();
            $table->string('tradeid', 64);
            $table->dateTime('datetime');
            $table->string('primary_currency', 16);
            $table->string('secondary_currency', 16);
            $table->enum('type', ['BUY', 'SELL']);
            $table->unsignedDecimal('tradeprice', 20, 8)->comment("The price the trade occurred at");
            $table->unsignedDecimal('quantity', 20, 8)->comment("Quantity traded");
            $table->unsignedDecimal('total', 20, 8)->comment("Total value of trade (tradeprice * quantity)");
            $table->unsignedDecimal('fee', 20, 8)->comment("Fee Charged for this Trade");
            $table->primary(['exchange', 'account', 'tradeid']);

        });
        Schema::create('transactions', function (Blueprint $table) {
            $table->string('exchange', 64);
            $table->string('account', 64)->nullable();
            $table->string('trxid', 255);
            $table->string('currency', 16);
            $table->dateTime('datetime');
            $table->enum('type', ['DEPOSIT','WITHDRAWAL']);
            $table->string('address', 255)->nullable();
            $table->unsignedDecimal('amount', 20, 8);
            $table->unsignedDecimal('fee', 20, 8);
            $table->primary(['exchange', 'account', 'trxid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('balances');
        Schema::dropIfExists('trades');
        Schema::dropIfExists('transactions');
    }
}
