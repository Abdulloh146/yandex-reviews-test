<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('yandex_url');
            $table->string('name')->nullable();
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count')->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedInteger('parsed_reviews_count')->default(0);
            $table->string('parse_status')->default('pending');
            $table->text('parse_error')->nullable();
            $table->timestamp('last_parsed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
