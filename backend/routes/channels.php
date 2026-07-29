<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('marketplace.user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
