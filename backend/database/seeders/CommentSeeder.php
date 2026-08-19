<?php

namespace Database\Seeders;

use App\Models\Comment;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    private const PARENT_BODIES = [
        '<p>Great <strong>article</strong>, thanks for sharing!</p>',
        '<p>This is exactly what I was looking for, very well explained.</p>',
        '<p>I have to disagree a bit here, but still an <i>interesting</i> read.</p>',
        '<p>Bookmarked for later, lots of useful detail in here.</p>',
        '<p>Could you elaborate a bit more on the second point?</p>',
        '<p>Ran into the same issue last week — glad I found this.</p>',
        '<p>Solid write-up. Here is what worked for me: <code>npm install</code></p>',
        '<p>Not sure I fully agree, but appreciate the perspective.</p>',
        '<p>More posts like this please, really enjoyed reading it.</p>',
        '<p>Check out <a href="https://example.com" title="Related resource">this related resource</a>, it goes deeper into the topic.</p>',
        '<p>Clear and to the point. Exactly what I needed today.</p>',
        '<p>Been following this for a while, quality keeps getting better.</p>',
    ];

    private const REPLY_BODIES = [
        '<p>Agreed, very <i>useful</i> write-up.</p>',
        '<p>Same here, bookmarked it.</p>',
        '<p>Thanks for the clarification!</p>',
        '<p>Good point, hadn\'t thought of it that way.</p>',
        '<p>+1, ran into this too.</p>',
        '<p>Not quite, I think it works differently — see <code>docs</code>.</p>',
        '<p>Appreciate the quick reply.</p>',
        '<p>That fixed it for me, thank you!</p>',
        '<p>Interesting take, I still lean the other way though.</p>',
        '<p>Makes sense now, thanks for explaining.</p>',
    ];

    public function run(): void {
        for ($i = 0; $i < 50; $i++) {
            $created_at = now()->subDays(random_int(0, 90))->subHours(random_int(0, 23));

            $parent = Comment::create([
                'user_name' => fake()->name(),
                'email' => fake()->safeEmail(),
                'home_page' => random_int(1, 100) <= 30 ? fake()->url() : null,
                'body' => fake()->randomElement(self::PARENT_BODIES),
            ]);

            $parent->forceFill(['created_at' => $created_at, 'updated_at' => $created_at])->save();

            $replies = [];
            $reply_count = random_int(0, 4);

            for ($r = 0; $r < $reply_count; $r++) {
                $replied_to = $replies && random_int(1, 100) <= 50
                    ? fake()->randomElement($replies)
                    : null;

                $reply_created_at = $created_at->copy()->addHours(random_int(1, 72));

                $reply = Comment::create([
                    'parent_id' => $parent->id,
                    'replied_to_comment_id' => $replied_to?->id,
                    'user_name' => fake()->name(),
                    'email' => fake()->safeEmail(),
                    'body' => fake()->randomElement(self::REPLY_BODIES),
                ]);

                $reply->forceFill(['created_at' => $reply_created_at, 'updated_at' => $reply_created_at])->save();

                $replies[] = $reply;
            }
        }
    }
}
