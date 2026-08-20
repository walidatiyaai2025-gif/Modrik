<?php

namespace App\Auth;

use App\Exceptions\ApiProblemException;

final class PendingProviderIdentityVerifier implements ProviderIdentityVerifier
{
    public function verify(string $provider, string $idToken): ProviderIdentityClaims
    {
        throw new ApiProblemException(
            503,
            'PROVIDER_CONFIGURATION_PENDING',
            'Identity provider unavailable',
            'This identity provider is not configured for production transport yet.',
            retryable: false,
        );
    }
}
