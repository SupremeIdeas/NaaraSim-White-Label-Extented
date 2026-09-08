<?php

namespace App\Models\Concerns;

use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * Gives any model a polymorphic reactions surface (component-library batch 2 §5).
 * A user holds at most one reaction on the subject; react() is idempotent and
 * toggles: same type again removes it, a different type switches it.
 */
trait HasReactions
{
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /** [type => count] for every type that has at least one reaction. */
    public function reactionCounts(): array
    {
        return $this->reactions()
            ->select('type', DB::raw('count(*) as aggregate'))
            ->groupBy('type')
            ->pluck('aggregate', 'type')
            ->toArray();
    }

    public function totalReactions(): int
    {
        return $this->reactions()->count();
    }

    /** The reaction type this user currently holds on the subject, or null. */
    public function userReaction(User $user): ?string
    {
        return $this->reactions()->where('user_id', $user->id)->value('type');
    }

    /**
     * Toggle a user's reaction. Invalid types are ignored. Returns the type the
     * user now holds (null if they just removed it).
     */
    public function react(User $user, string $type): ?string
    {
        if (! Reaction::isValidType($type)) {
            return $this->userReaction($user);
        }

        $existing = $this->reactions()->where('user_id', $user->id)->first();

        if ($existing && $existing->type === $type) {
            $existing->delete();

            return null;
        }

        if ($existing) {
            $existing->update(['type' => $type]);

            return $type;
        }

        $this->reactions()->create(['user_id' => $user->id, 'type' => $type]);

        return $type;
    }
}
