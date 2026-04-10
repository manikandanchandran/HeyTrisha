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
        Schema::table('sites', function (Blueprint $table) {
            // User account fields
            $table->string('username')->nullable()->unique()->after('email');
            $table->string('password')->nullable()->after('username'); // Hashed password
            $table->string('first_name')->nullable()->after('password');
            $table->string('last_name')->nullable()->after('first_name');
            
            // Database credentials (encrypted)
            $table->string('db_name')->nullable()->after('last_name');
            $table->string('db_username')->nullable()->after('db_name');
            $table->text('db_password')->nullable()->after('db_username'); // Encrypted
            
            // Indexes
            $table->index('username');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex(['username']);
            $table->dropColumn([
                'username',
                'password',
                'first_name',
                'last_name',
                'db_name',
                'db_username',
                'db_password'
            ]);
        });
    }
};


