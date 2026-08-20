<?php

namespace App\Auth;

final readonly class ProviderIdentityClaims
{
    public function __construct(
        public string $provider,
        public string $subject,
        public string $issuer,
        public string $audience,
        public int $expiresAt,
        public ?string $nonce,
        public ?string $email,
        public bool $emailVerified,
        public bool $signatureValidated,
        public bool $issuerValidated,
        public bool $audienceValidated,
    ) {}

    public function isCryptographicallyValid(): bool
    {
        return $this->signatureValidated
            && $this->issuerValidated
            && $this->audienceValidated
            && $this->expiresAt > time();
    }
}
