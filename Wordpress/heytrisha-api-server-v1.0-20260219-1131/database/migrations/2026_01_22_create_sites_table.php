<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('site_url')->unique()->index();
            $table->string('api_key_hash', 64)->unique()->index(); // SHA-256 hash of API key
            $table->text('openai_key'); // Encrypted
            $table->string('email')->nullable();
            $table->string('wordpress_version')->nullable();
            $table->string('woocommerce_version')->nullable();
            $table->string('plugin_version')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('query_count')->default(0); // Track usage
            $table->timestamp('last_query_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('is_active');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sites');
    }
};




