<?php

declare(strict_types=1);

use A2BillingPlus\Api\ApiCustomerContext;
use A2BillingPlus\Api\ApiCustomerContextAuthenticator;
use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

final class ApiCustomerContextAuthenticatorTest extends TestCase
{
    public function testAuthenticatesSignedCustomerContext(): void
    {
        $authenticator = new ApiCustomerContextAuthenticator(new AppConfig(['A2BP_CUSTOMER_API_SECRET' => 'customer-secret']));

        $result = $authenticator->authenticate(new JsonRequest('GET', [], [], [
            'X-A2BP-Customer-Id' => '42',
            'X-A2BP-Customer-Signature' => hash_hmac('sha256', '42', 'customer-secret'),
        ]));

        $this->assertInstanceOf(ApiCustomerContext::class, $result);
        $this->assertSame(42, $result->customerId);
    }

    public function testRejectsInvalidCustomerSignature(): void
    {
        $authenticator = new ApiCustomerContextAuthenticator(new AppConfig(['A2BP_CUSTOMER_API_SECRET' => 'customer-secret']));

        $result = $authenticator->authenticate(new JsonRequest('GET', [], [], [
            'X-A2BP-Customer-Id' => '42',
            'X-A2BP-Customer-Signature' => 'bad',
        ]));

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertSame(403, $result->getStatusCode());
    }
}
