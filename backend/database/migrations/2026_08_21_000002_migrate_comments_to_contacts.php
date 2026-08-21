<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('comments', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('replied_to_comment_id')->constrained('contacts');
        });

        DB::table('comments')
            ->select('email', 'user_name')
            ->distinct()
            ->get()
            ->each(function (object $pair) {
                $contact_id = DB::table('contacts')->insertGetId([
                    'user_name' => $pair->user_name,
                    'email' => $pair->email,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('comments')
                    ->where('email', $pair->email)
                    ->where('user_name', $pair->user_name)
                    ->update(['contact_id' => $contact_id]);
            });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `comments` MODIFY `contact_id` BIGINT UNSIGNED NOT NULL');
        }

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn(['user_name', 'email']);
        });
    }

    public function down(): void {
        Schema::table('comments', function (Blueprint $table) {
            $table->string('user_name')->nullable()->after('replied_to_comment_id');
            $table->string('email')->nullable()->after('user_name');
        });

        DB::table('comments')
            ->join('contacts', 'contacts.id', '=', 'comments.contact_id')
            ->update([
                'comments.user_name' => DB::raw('contacts.user_name'),
                'comments.email' => DB::raw('contacts.email'),
            ]);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `comments` MODIFY `user_name` VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE `comments` MODIFY `email` VARCHAR(255) NOT NULL');
        }

        Schema::table('comments', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
        });

        DB::table('contacts')->truncate();
    }
};
