<?php

namespace App\Security;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtTokenManager
{
    public function __construct(
        private readonly string $jwtSecret,
        private readonly int $ttlSeconds = 3600,
        private readonly string $issuer = 'falconcare',
        private readonly string $audience = 'falconcare-frontend',
    ) {
    }

    public function getTtlSeconds(): int
    {
        return $this->ttlSeconds;
    }

    public function getIssuer(): string
    {
        return $this->issuer;
    }

    public function getAudience(): string
    {
        return $this->audience;
    }

    public function createToken(User $user): string
    {
        $now = time();

        $payload = [
            'iss' => $this->issuer,
            'aud' => $this->audience,
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
            'sub' => (string) $user->getId(),
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    /**
     * @return array{sub:string,email?:string,roles?:array<array-key,string>}
     */
    public function decode(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));

        return json_decode(json_encode($decoded, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
    }
}

