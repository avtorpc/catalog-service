<?php

namespace App\Infrastructure\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Shared\Exception\InvalidTokenException;

final class JwtTokenDecoder
{
    public function __construct(
        private readonly string $publicKey,
        private readonly string $issuer,
        private readonly string $audience,
    ) {
    }

    public function decode(string $token): object
    {
        try {
            $decoded = JWT::decode(
                $token,
                new Key($this->publicKey, 'RS256')
            );
        } catch (\Throwable $e) {
            throw new InvalidTokenException(
                'Invalid access token',
                previous: $e
            );
        }

        if ($decoded->iss !== $this->issuer) {
            throw new InvalidTokenException('Invalid issuer');
        }

        if ($decoded->aud !== $this->audience) {
            throw new InvalidTokenException('Invalid audience');
        }

        return $decoded;
    }
}
