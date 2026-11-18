<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $table = 'users';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }

    public function farmerProfile(): HasOne
    {
        return $this->hasOne(Farmer::class, 'user_id');
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
        return $this->roles->flatMap->permissions->contains('name', $permissionName);
    }
}
