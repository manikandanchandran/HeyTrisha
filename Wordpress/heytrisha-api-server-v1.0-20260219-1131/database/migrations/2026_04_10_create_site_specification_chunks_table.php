<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the table that stores chunked + embedded specification text per site.
     *
     * Each row represents one overlapping chunk of the raw specification file
     * uploaded by the WordPress admin.  The embedding column stores the
     * serialised float vector (JSON array) for cosine-similarity retrieval.
     */
    public function up(): void
    {
        Schema::create('site_specification_chunks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->index();
            $table->string('spec_version', 64)->index();  // hash of the raw text
            $table->unsignedSmallInteger('chunk_index');  // sequential chunk number
            $table->text('content');                      // chunk text
            $table->mediumText('embedding')->nullable();  // JSON float array
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->unique(['site_id', 'spec_version', 'chunk_index'], 'spec_chunk_unique');
        });

        Schema::create('site_specifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->unique()->index();
            $table->string('spec_version', 64);
            $table->mediumText('allowlist_json');   // JSON: suffix => [columns] or '*'
            $table->text('rules_summary')->nullable();
            $table->text('forbidden_tables_json')->nullable(); // JSON array of forbidden suffixes
            $table->timestamps();

            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_specification_chunks');
        Schema::dropIfExists('site_specifications');
    }
};
