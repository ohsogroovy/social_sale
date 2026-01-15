<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('facebook_user_id');
            $table->string('commenter');
            $table->string('facebook_id');
            $table->string('post_id');
            $table->string('parent_id');
            $table->string('post_link');
            $table->string('post_type');
            $table->mediumText('message');
            $table->timestamp('facebook_created_at', 3);
            $table->boolean('is_from_page')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
