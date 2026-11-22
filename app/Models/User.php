<?php

namespace App\Models;

use App\Traits\Auditable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    use Auditable, HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $table = 'users';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'village_id',
        'name',
        'email',
        'password',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class, 'village_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function farmerProfile(): HasOne
    {
        return $this->hasOne(Farmer::class, 'user_id');
    }

    public function ownerProfile(): HasOne
    {
        return $this->hasOne(Owner::class, 'user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class, 'user_id');
    }

    public function fertilizationRecords(): HasMany
    {
        return $this->hasMany(FertilizationRecord::class, 'recorded_by');
    }

    public function sprayingRecords(): HasMany
    {
        return $this->hasMany(SprayingRecord::class, 'recorded_by');
    }

    public function harvestSessions(): HasMany
    {
        return $this->hasMany(HarvestSession::class, 'recorded_by');
    }

    public function treeMeasurements(): HasMany
    {
        return $this->hasMany(TreeMeasurement::class, 'recorded_by');
    }

    public function activityPhotos(): HasMany
    {
        return $this->hasMany(ActivityPhoto::class, 'uploaded_by');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('name', $roleName);
    }

    public function hasPermission(string $permissionName): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->roles->flatMap->permissions->contains('name', $permissionName);
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $this->loadMissing('roles');

        return $this->roles->pluck('name')->intersect([
            'super_admin',
            'admin',
            'petani',
            'pemerintah_desa',
            'owner', // Backwards-compatible fallback during migration
            'plantation_manager', // Backwards-compatible fallback during migration
        ])->isNotEmpty();
    }
}
