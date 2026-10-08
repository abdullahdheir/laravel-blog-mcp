<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->nullable()->unique();
            $table->longText('body_markdown');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('type')->default('internal');
            $table->string('source_url', 1000)->nullable();
            $table->string('source_platform')->nullable();
            $table->boolean('notify_subscribers')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
        });

        Schema::create('category_post', function (Blueprint $table) {
            $table->foreignId('post_id');
            $table->foreignId('category_id');
        });
    }
};
