<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gander_stack', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 15);
            $table->integer('sequence');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('file')->nullable();
            $table->string('function')->nullable();
            $table->integer('line')->nullable();
            $table->float('elapsed_seconds', 12, 5)->nullable();
            $table->text('message')->nullable();
            $table->timestamps();

            $table->index(['request_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gander_stack');
    }
};
