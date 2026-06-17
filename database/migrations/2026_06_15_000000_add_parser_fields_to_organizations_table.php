<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (! Schema::hasColumn('organizations', 'parsed_reviews_count')) {
                $table->unsignedInteger('parsed_reviews_count')->default(0)->after('reviews_count');
            }

            if (! Schema::hasColumn('organizations', 'last_parsed_at')) {
                $table->timestamp('last_parsed_at')->nullable()->after('parsed_reviews_count');
            }

            if (! Schema::hasColumn('organizations', 'parse_status')) {
                $table->string('parse_status')->default('pending')->after('yandex_url');
            }

            if (! Schema::hasColumn('organizations', 'parse_error')) {
                $table->text('parse_error')->nullable()->after('parse_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'parsed_reviews_count')) {
                $table->dropColumn('parsed_reviews_count');
            }
        });
    }
};
