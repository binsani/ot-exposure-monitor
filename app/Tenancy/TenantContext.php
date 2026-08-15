<?php

namespace App\Tenancy;

final class TenantContext
{
    private ?int $organizationId = null;

    public function set(?int $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    public function id(): ?int
    {
        return $this->organizationId;
    }

    public function clear(): void
    {
        $this->organizationId = null;
    }
}
