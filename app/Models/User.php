<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'demo_id', 'is_demo', 'expires_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_demo' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Get the projects owned by the user.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get every task across the user's projects.
     *
     * @return HasManyThrough<Task, Project, $this>
     */
    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, Project::class);
    }

    /**
     * Get the whole days left before the demo workspace expires, rounding partial days up.
     */
    public function demoDaysRemaining(): int
    {
        if ($this->expires_at === null) {
            return 0;
        }

        return max(0, (int) ceil(now()->floatDiffInDays($this->expires_at, false)));
    }

    /**
     * Get the quiet header label for the remaining workspace lifetime.
     */
    public function demoExpiryLabel(): string
    {
        if ($this->demoExpiryUrgency() === 'today') {
            return 'Expires today';
        }

        $days = $this->demoDaysRemaining();

        return 'Workspace will be deleted in '.$days.' '.Str::plural('day', $days);
    }

    /**
     * Get how urgent the remaining lifetime is: normal, warning, critical or today.
     */
    public function demoExpiryUrgency(): string
    {
        if ($this->expires_at?->isToday()) {
            return 'today';
        }

        return match (true) {
            $this->demoDaysRemaining() >= 8 => 'normal',
            $this->demoDaysRemaining() >= 4 => 'warning',
            default => 'critical',
        };
    }

    /**
     * Determine whether this is a demo workspace that has not expired.
     */
    public function isActiveDemo(): bool
    {
        return $this->is_demo && ! $this->demoHasExpired();
    }

    /**
     * Determine whether this is a demo workspace whose lifetime has ended.
     */
    public function demoHasExpired(): bool
    {
        return $this->is_demo && $this->expires_at !== null && $this->expires_at->lte(now());
    }
}
