<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable();
            $table->string('author_name')->nullable();
            $table->date('review_date')->nullable();
            $table->string('review_date_text')->nullable();
            $table->text('text')->nullable();
            $table->decimal('rating', 2, 1)->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'review_date']);
            $table->index(['organization_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
