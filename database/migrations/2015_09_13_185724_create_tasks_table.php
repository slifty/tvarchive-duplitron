<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {

            // Create fields
            $table->increments('id');
            $table->integer('media_id');
            $table->integer('attempts');
            $table->string('type');
            $table->string('status_code')->nullable();
            $table->string('result_code')->nullable();
            $table->json('result_data')->nullable();
            $table->text('result_output')->nullable();
            $table->timestamps();

            // Create indexes
            $table->foreign('media_id')->references('id')->on('media')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('tasks');
    }
}
