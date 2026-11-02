<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_DEACTIVATED = 'deactivated';

    public const STATUS_DELETED = 'deleted';

    public const CAP_REQUEST = 'ride.request';

    public const CAP_OFFER = 'ride.offer';

    public const CAP_VEHICLE_MANAGE = 'vehicle.manage';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'remember_token', 'nid_number_encrypted'];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'nid_verified_at' => 'datetime',
            'license_verified_at' => 'datetime',
            'last_location_at' => 'datetime',
            'dob' => 'date',
            'password' => 'hashed',
            'nid_number_encrypted' => 'encrypted',
            'last_lat' => 'float',
            'last_lng' => 'float',
            'rating_avg' => 'float',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function savedLocations(): HasMany
    {
        return $this->hasMany(SavedLocation::class);
    }

    public function rideRequests(): HasMany
    {
        return $this->hasMany(RideRequest::class, 'creator_id');
    }

    public function rideOffers(): HasMany
    {
        return $this->hasMany(RideOffer::class, 'provider_id');
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(JoinRequest::class);
    }

    public function tripsAsCoordinator(): HasMany
    {
        return $this->hasMany(Trip::class, 'coordinator_id');
    }

    public function tripsAsProvider(): HasMany
    {
        return $this->hasMany(Trip::class, 'provider_id');
    }

    public function tripParticipations(): HasMany
    {
        return $this->hasMany(TripParticipant::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function hasVerifiedIdentity(): bool
    {
        return $this->nid_verified_at !== null && $this->license_verified_at !== null;
    }

    public function canProvideRides(): bool
    {
        return $this->isActive()
            && $this->hasVerifiedIdentity()
            && $this->vehicles()->where('status', Vehicle::STATUS_ACTIVE)->exists();
    }
}
