<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInterviewerPrefecturesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('interviewer_prefectures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('prefecture_id');
            $table->string('interviewer_1');
            $table->string('interviewer_2')->nullable();
            $table->string('interviewer_3')->nullable();
            $table->string('interviewer_4')->nullable();
            $table->string('interviewer_5')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('interviewer_prefectures');
    }
}
