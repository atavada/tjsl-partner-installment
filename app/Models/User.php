<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\Auditable;
use App\Enums\Permission;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    /**
     * @var array<string, bool>
     */
    protected array $grantedPermissions = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'permissions',
    ];

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
            'role' => Role::class,
            'permissions' => 'array',
        ];
    }

    public function hasRole(Role|string ...$roles): bool
    {
        foreach ($roles as $role) {
            $roleValue = $role instanceof Role ? $role->value : $role;
            if ($this->role->value === $roleValue) {
                return true;
            }
        }

        return false;
    }

    public function isSystemAdmin(): bool
    {
        return $this->role === Role::SystemAdmin;
    }

    public function isOperator(): bool
    {
        return $this->role === Role::Operator;
    }

    public function isReconciliationReviewer(): bool
    {
        return $this->role === Role::ReconciliationReviewer;
    }

    public function isProcessOwner(): bool
    {
        return $this->role === Role::ProcessOwner;
    }

    public function isAuditor(): bool
    {
        return $this->role === Role::Auditor;
    }

    public function isFinancial(): bool
    {
        return $this->role->isFinancial();
    }

    public function hasPermission(Permission|string $permission): bool
    {
        $permValue = $permission instanceof Permission ? $permission->value : $permission;

        if (! empty($this->grantedPermissions[$permValue])) {
            return true;
        }

        $persisted = $this->permissions ?? [];

        return ! empty($persisted[$permValue]);
    }

    public function grantPermission(Permission|string $permission): self
    {
        $permValue = $permission instanceof Permission ? $permission->value : $permission;
        $this->grantedPermissions[$permValue] = true;

        $permissions = $this->permissions ?? [];
        $permissions[$permValue] = true;
        $this->permissions = $permissions;

        return $this;
    }

    public function revokePermission(Permission|string $permission): self
    {
        $permValue = $permission instanceof Permission ? $permission->value : $permission;
        unset($this->grantedPermissions[$permValue]);

        $permissions = $this->permissions ?? [];
        unset($permissions[$permValue]);
        $this->permissions = $permissions;

        return $this;
    }

    public function clearPermissions(): self
    {
        $this->grantedPermissions = [];
        $this->permissions = [];

        return $this;
    }
}
