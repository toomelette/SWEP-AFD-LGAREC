<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('su_biometric_devices', function (Blueprint $table) {
            $table->tinyInteger('last_state')->nullable();
            $table->dateTime('last_state_timestamp')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('su_biometric_devices', function (Blueprint $table) {
            $table->dropColumn(['last_state','last_state_timestamp']);
        });
    }
};
