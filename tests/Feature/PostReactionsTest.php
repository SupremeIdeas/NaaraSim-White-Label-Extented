<?php

namespace Tests\Feature;

use App\Livewire\PostReactions;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Component-library batch 2 §5 — polymorphic post reactions. One reaction per
 * user per post, toggle semantics, guest gating.
 */
class PostReactionsTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): Post
    {
        return Post::create([
            'title' => 'Hello', 'slug' => 'hello', 'category' => 'News',
            'body' => 'Body', 'status' => 'published', 'published_at' => now()->subDay(),
        ]);
    }

    public function test_react_is_a_toggle_switch_per_user(): void
    {
        $post = $this->makePost();
        $user = User::factory()->create();

        // add
        $this->assertSame('like', $post->react($user, 'like'));
        $this->assertSame(1, $post->reactions()->count());

        // same type again removes it
        $this->assertNull($post->react($user, 'like'));
        $this->assertSame(0, $post->reactions()->count());

        // different type switches (still one row)
        $post->react($user, 'like');
        $this->assertSame('love', $post->react($user, 'love'));
        $this->assertSame(1, $post->reactions()->count());
        $this->assertSame('love', $post->userReaction($user));
    }

    public function test_one_reaction_row_per_user_per_post_is_enforced(): void
    {
        $post = $this->makePost();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $post->react($a, 'like');
        $post->react($b, 'celebrate');

        $this->assertSame(2, $post->totalReactions());
        $this->assertEqualsCanonicalizing(['like' => 1, 'celebrate' => 1], $post->reactionCounts());
    }

    public function test_invalid_type_is_ignored(): void
    {
        $post = $this->makePost();
        $user = User::factory()->create();

        $post->react($user, 'thumbsdown-lol');
        $this->assertSame(0, $post->reactions()->count());
        $this->assertFalse(Reaction::isValidType('thumbsdown-lol'));
    }

    public function test_livewire_component_toggles_for_a_signed_in_user(): void
    {
        $post = $this->makePost();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(PostReactions::class, ['post' => $post])
            ->assertSet('mine', null)
            ->call('react', 'like')
            ->assertSet('mine', 'like')
            ->call('react', 'like')
            ->assertSet('mine', null);
    }

    public function test_guest_is_redirected_to_login_and_no_reaction_is_stored(): void
    {
        $post = $this->makePost();

        Livewire::test(PostReactions::class, ['post' => $post])
            ->call('react', 'like')
            ->assertRedirect(route('login'));

        $this->assertSame(0, $post->reactions()->count());
    }
}
