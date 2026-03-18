<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class ApiJwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JwtTokenManager $jwtTokenManager,
        private readonly UserProviderInterface $userProvider,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api')) {
            return false;
        }

        if (str_starts_with($path, '/api/docs') || $path === '/api/auth/login' || $path === '/api/health') {
            return false;
        }

        $auth = (string) $request->headers->get('Authorization', '');

        return str_starts_with($auth, 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $auth = (string) $request->headers->get('Authorization', '');
        $token = trim(substr($auth, 7));

        if ($token === '') {
            throw new AuthenticationException('Missing Bearer token');
        }

        $claims = $this->jwtTokenManager->decode($token);
        $email = (string) ($claims['email'] ?? '');

        // Security hardening: ensure token was issued for the expected frontend.
        $iss = (string) ($claims['iss'] ?? '');
        $aud = (string) ($claims['aud'] ?? '');
        if ($iss !== $this->jwtTokenManager->getIssuer() || $aud !== $this->jwtTokenManager->getAudience()) {
            throw new AuthenticationException('Invalid token issuer/audience');
        }

        if ($email === '') {
            throw new AuthenticationException('Invalid token');
        }

        $userBadge = new UserBadge($email, function (string $userIdentifier) {
            try {
                return $this->userProvider->loadUserByIdentifier($userIdentifier);
            } catch (UserNotFoundException $e) {
                throw new AuthenticationException('Invalid token user', 0, $e);
            }
        });

        return new SelfValidatingPassport($userBadge);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['error' => 'Unauthorized', 'message' => $exception->getMessage()],
            Response::HTTP_UNAUTHORIZED
        );
    }
}

