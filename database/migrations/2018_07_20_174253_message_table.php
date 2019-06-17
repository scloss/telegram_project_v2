<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class MessageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //

        Schema::create('message_table', function($table){
            $table->increments('id');
            $table->bigInteger('update_id');
            $table->bigInteger('chat_id');
            $table->string('fname', 100)->nullable();
            $table->string('lname', 100)->nullable();
            $table->string('message', 100)->nullable();
            $table->string('reply', 100)->nullable();
            $table->timestamp('message_date')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->timestamp('reply_date')->default(DB::raw('CURRENT_TIMESTAMP'));

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::drop('message_table');
    }
}
