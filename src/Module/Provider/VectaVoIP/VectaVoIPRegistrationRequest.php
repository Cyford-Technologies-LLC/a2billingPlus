<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

final class VectaVoIPRegistrationRequest
{
    public function __construct(
        private readonly string $installKey,
        private readonly string $companyName,
        private readonly string $companyDomain,
        private readonly string $contactName,
        private readonly string $contactEmail,
        private readonly string $contactPhone,
        private readonly string $details,
        private readonly string $appName,
        private readonly string $appVersion
    ) {
    }

    public function getInstallKey(): string
    {
        return $this->installKey;
    }

    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function getCompanyDomain(): string
    {
        return $this->companyDomain;
    }

    public function getContactName(): string
    {
        return $this->contactName;
    }

    public function getContactEmail(): string
    {
        return $this->contactEmail;
    }

    public function getContactPhone(): string
    {
        return $this->contactPhone;
    }

    public function getDetails(): string
    {
        return $this->details;
    }

    public function getAppName(): string
    {
        return $this->appName;
    }

    public function getAppVersion(): string
    {
        return $this->appVersion;
    }

    /**
     * @return array<string, string>
     */
    public function toPayload(): array
    {
        return [
            'install_key' => $this->installKey,
            'company_name' => $this->companyName,
            'company_domain' => $this->companyDomain,
            'contact_name' => $this->contactName,
            'contact_email' => $this->contactEmail,
            'contact_phone' => $this->contactPhone,
            'details' => $this->details,
            'app_name' => $this->appName,
            'app_version' => $this->appVersion,
        ];
    }
}
