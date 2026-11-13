<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('city')->nullable();
            $table->boolean('newsletter')->default(true);
            $table->unsignedInteger('logins')->default(0);
            $table->softDeletes();
        });

        Schema::create('organizers', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('email');
        });

        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
        Schema::dropIfExists('organizers');
        Schema::dropIfExists('users');
    }
};
