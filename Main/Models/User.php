<?php

declare(strict_types=1);

namespace Main\Models;

use Sofy\Auth\HasApiTokens;
use Sofy\Auth\HasRoles;
use Sofy\Database\Model;
use Sofy\Notification\Notifiable;

class User extends Model
{
    protected static string $table = 'users';

    protected static array $fillable = [
        'name',
        'email',
        'password',
    ];

    protected static array $hidden = [
        'password',
        'remember_token',
    ];

    protected static array $casts = [
        'email_verified_at' => 'datetime',
    ];

    use HasApiTokens, HasRoles, Notifiable;
}
