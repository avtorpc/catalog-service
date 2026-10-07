<?php

namespace App\Infrastructure\Security;

use App\Domain\Security\PasswordHasherInterface;

final class BcryptPasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT);
    }

    public function verify(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }
}
