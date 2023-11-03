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
        Schema::create('gander_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_id', 15);
            $table->string('method', 8);
            $table->string('endpoint');
            $table->integer('response_status');
            $table->string('response_status_text', 32)->nullable();
            $table->text('url')->nullable();
            $table->text('request_headers_json')->nullable();
            $table->json('request_body_json')->nullable();
            $table->json('response_body_json')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->ipAddress('user_ip')->nullable();
            $table->text('curl')->nullable();
            $table->float('elapsed_seconds', 12, 5)->nullable();
            $table->timestamps();

            $table->index(['request_id', 'response_status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gander_requests');
    }
};
