<?php

namespace App\Auth;

interface ProviderIdentityVerifier
{
    /**
     * Verify provider transport, JWT signature, issuer, audience and expiry before returning claims.
     */
    public function verify(string $provider, string $idToken): ProviderIdentityClaims;
}
