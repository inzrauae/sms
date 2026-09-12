<?php

namespace App\Support;

class Uid
{
    /**
     * Short opaque identifier used for public-facing ids (users, messages,
     * campaigns). Twelve hex characters, matching the reference Node app.
     */
    public static function make(): string
    {
        return bin2hex(random_bytes(6));
    }
}
