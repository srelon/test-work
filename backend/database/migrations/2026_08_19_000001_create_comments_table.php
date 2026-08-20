<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->unsignedBigInteger('replied_to_comment_id')->nullable();
            $table->string('user_name');
            $table->string('email');
            $table->string('home_page')->nullable();
            $table->text('body');
            $table->json('images')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'created_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('comments');
    }
};
