<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Statamic\Auth\Eloquent\User as StatamicUser;

class User extends StatamicUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
}
