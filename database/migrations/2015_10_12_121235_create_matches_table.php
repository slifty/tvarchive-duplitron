<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('matches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('destination_id');
            $table->integer('source_id');

            $table->decimal('duration');
            $table->decimal('destination_start');
            $table->decimal('source_start');
            $table->timestamps();

            // Create indexes
            $table->foreign('destination_id')->references('id')->on('media')
                ->onDelete('cascade');
            $table->foreign('source_id')->references('id')->on('media')
                ->onDelete('cascade');

            $table->unique(array('destination_id', 'source_id', 'destination_start', 'source_start'));
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('matches');
    }
}
