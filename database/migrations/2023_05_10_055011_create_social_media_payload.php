<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('social_media_payload', function (Blueprint $table) {
            $table->increments('id');
            $table->json('payload')->nullable();
            $table->dateTime('upload_time');
            $table->tinyInteger('upload_post_status')->comment('1 => pending , 2 => completed')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_payload');
    }
};
