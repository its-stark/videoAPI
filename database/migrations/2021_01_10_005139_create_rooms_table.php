<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRoomsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->dateTime('start_date');
            $table->integer('max_duration')->default(60);
            $table->integer('max_participants')->default(20);
            $table->integer('actual_duration')->default(0);
            $table->integer('actual_participants')->default(0);
            $table->text('booking_token')->nullable();
            $table->text('user_token')->nullable();
            $table->text('token')->nullable();
            $table->string('status')->default('booked');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rooms');
    }
}
