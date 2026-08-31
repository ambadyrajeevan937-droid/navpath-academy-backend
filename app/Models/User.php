<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone', 'target_exam', 'school', 'stream', 'learnyst_user_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function enrolments(): HasMany { return $this->hasMany(Enrolment::class); }
    public function attempts(): HasMany   { return $this->hasMany(Attempt::class); }
    public function progress(): HasMany   { return $this->hasMany(Progress::class); }
    public function devices(): HasMany    { return $this->hasMany(Device::class); }

    /**
     * Device binding — max two active devices. A third sign-in revokes the
     * oldest. The cheapest effective control against credential sharing, which
     * is the dominant piracy vector in Indian ed-tech.
     */
    public function registerDevice(string $deviceId, string $platform): void
    {
        $this->devices()->updateOrCreate(
            ['device_id' => $deviceId],
            ['platform' => $platform, 'last_seen_at' => now(), 'revoked_at' => null]
        );

        $this->devices()->whereNull('revoked_at')->latest('last_seen_at')
            ->skip(2)->take(10)->get()->each->update(['revoked_at' => now()]);
    }
}
