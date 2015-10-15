<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProjectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('projects', function (Blueprint $table) {

            // Create fields
            $table->increments('id');
            $table->text('name');
            $table->text('audf_corpus')->nullable();
            $table->text('audf_potential_targets')->nullable();
            $table->text('audf_matches')->nullable();
            $table->text('audf_distractors')->nullable();
            $table->timestamps();

            // Create indexes
            $table->unique('name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('projects');
    }
}
