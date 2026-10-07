<?php



namespace App\Infrastructure\Security;

final class RandomPasswordGenerator
{
    public function generate(): string
    {
        return substr(bin2hex(random_bytes(8)), 0, 12);
    }
}
