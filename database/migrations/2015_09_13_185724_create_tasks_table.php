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
            $table->integer('project_id');
            $table->text('fprint_url')->nullable();
            $table->text('media_url')->nullable();
            $table->string('image_id')->nullable();
            $table->json('result')->nullable();
            $table->integer('status');
            $table->timestamps();

            // Create indexes
            $table->foreign('project_id')->references('id')->on('projects')
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
