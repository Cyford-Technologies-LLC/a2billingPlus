<?php

declare(strict_types=1);

namespace A2BillingPlus\Module\Provider\VectaVoIP;

final class VectaVoIPRegistrationRequest
{
    public function __construct(
        private readonly string $installKey,
        private readonly string $username,
        private readonly string $password,
        private readonly string $companyName,
        private readonly string $companyDomain,
        private readonly string $contactEmail,
        private readonly string $requestIp,
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

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getCompanyDomain(): string
    {
        return $this->companyDomain;
    }

    public function getContactEmail(): string
    {
        return $this->contactEmail;
    }

    public function getRequestIp(): string
    {
        return $this->requestIp;
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
            'username' => $this->username,
            'password' => $this->password,
            'company_name' => $this->companyName,
            'company_domain' => $this->companyDomain,
            'contact_email' => $this->contactEmail,
            'request_ip' => $this->requestIp,
            'app_name' => $this->appName,
            'app_version' => $this->appVersion,
        ];
    }
}
