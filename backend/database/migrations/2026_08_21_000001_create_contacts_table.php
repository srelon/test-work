<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('user_name');
            $table->string('email');
            $table->timestamps();

            $table->unique(['email', 'user_name']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('contacts');
    }
};
