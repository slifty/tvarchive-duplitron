<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectTasksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('project_tasks', function (Blueprint $table) {

            // Create fields
            $table->increments('id');
            $table->integer('project_id');
            $table->integer('attempts')->nullable();
            $table->string('type');
            $table->string('status_code')->nullable();
            $table->string('result_code')->nullable();
            $table->json('result_data')->nullable();
            $table->json('result_output')->nullable();
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
        Schema::drop('project_tasks');
    }
}
