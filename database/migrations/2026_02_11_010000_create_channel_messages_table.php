<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChannelMessagesTable extends Migration
{
    public function up(): void
    {
        Schema::create('channel_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel_id');
            $table->bigInteger('message_id')->unsigned();
            $table->text('raw_message');
            $table->double('parsed_lat', 10, 6)->nullable();
            $table->double('parsed_lon', 10, 6)->nullable();
            $table->text('parsed_text')->nullable();
            $table->json('keywords')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['channel_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_messages');
    }
}
