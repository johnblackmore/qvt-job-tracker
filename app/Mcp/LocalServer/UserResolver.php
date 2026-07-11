<?php

namespace App\Mcp\LocalServer;

use App\Models\User;

class UserResolver
{
    /**
     * Resolve the staff user from the --user={email} artisan command flag.
     */
    public static function resolve(): ?User
    {
        $email = null;

        foreach ($_SERVER['argv'] ?? [] as $arg) {
            if (str_starts_with($arg, '--user=')) {
                $email = substr($arg, 7);
                break;
            }
        }

        if (! $email) {
            return null;
        }

        return User::where('email', $email)->first();
    }
}
