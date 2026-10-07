<?php

namespace App\Infrastructure\Security;

use Symfony\Component\HttpFoundation\Request;
use App\Shared\Exception\UnauthorizedException;

final class JwtAuthenticator
{
    public function __construct(
        private readonly JwtTokenDecoder $decoder
    ) {
    }

    public function authenticate(Request $request): object
    {
        $header = $request->headers->get('Authorization');

        if (!$header) {
            throw new UnauthorizedException(
                'Authorization header required'
            );
        }

        if (!str_starts_with($header, 'Bearer ')) {
            throw new UnauthorizedException(
                'Invalid authorization header'
            );
        }

        $token = substr($header, 7);

      return  $this->decoder->decode($token);
    }
}
