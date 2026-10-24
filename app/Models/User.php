<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * @property UserRole|null $role
 */
class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'password',
        'role',
        'google_id',
        'avatar',
        'notify_in_app',
        'notify_by_mail',
    ];

    protected static function booted(): void
    {
        // Someone invited to projects by e-mail joins them as soon as the account exists.
        static::created(fn (User $user) => ProjectInvitation::acceptFor($user));
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Administrators and managers run the whole CRM; the other roles work inside the projects they are assigned to.
     */
    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    public function isCollaborator(): bool
    {
        return $this->role === UserRole::COLLABORATOR;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::CUSTOMER;
    }

    public function canReportTime(): bool
    {
        return $this->hasAnyRole() && ! $this->isCustomer();
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::MANAGER;
    }

    public function hasAnyRole(): bool
    {
        return $this->role !== null && in_array($this->role, UserRole::cases(), true);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'role' => UserRole::class,
            'notify_in_app' => 'boolean',
            'notify_by_mail' => 'boolean',
        ];
    }
}
