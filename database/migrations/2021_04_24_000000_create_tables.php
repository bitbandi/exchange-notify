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
            $table->double('amount', 20, 9)->unsigned();
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
            $table->double('tradeprice', 20, 9)->unsigned()->comment("The price the trade occurred at");
            $table->double('quantity', 20, 9)->unsigned()->comment("Quantity traded");
            $table->double('total', 20, 9)->unsigned()->comment("Total value of trade (tradeprice * quantity)");
            $table->double('fee', 20, 9)->unsigned()->comment("Fee Charged for this Trade");
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
            $table->double('amount', 20, 9)->unsigned();
            $table->double('fee', 20, 9)->unsigned();
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
