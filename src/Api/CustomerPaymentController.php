<?php

declare(strict_types=1);

namespace A2BillingPlus\Api;

use A2BillingPlus\Config\AppConfig;
use A2BillingPlus\Http\ApiResponder;
use A2BillingPlus\Http\JsonRequest;
use A2BillingPlus\Http\JsonResponse;
use A2BillingPlus\Module\Payment\CustomerPaymentPortalService;
use A2BillingPlus\Module\Payment\PaymentIntentService;
use A2BillingPlus\Module\Payment\StripePaymentIntentClient;

final class CustomerPaymentController
{
    /**
     * @param callable(): \PDO $pdoFactory
     * @param null|callable(): PaymentIntentService $intentServiceFactory
     */
    public function __construct(
        private readonly ApiCustomerContextAuthenticator $authenticator,
        private readonly AppConfig $config,
        private $pdoFactory,
        private $intentServiceFactory = null
    ) {
    }

    public function handle(JsonRequest $request): JsonResponse
    {
        $context = $this->authenticator->authenticate($request);
        if ($context instanceof JsonResponse) {
            return $context;
        }

        if (!in_array($request->getMethod(), ['GET', 'POST'], true)) {
            return ApiResponder::error('method_not_allowed', 'Customer payments support GET and POST.', 405);
        }

        $limit = $request->getInt('limit', 50);
        $offset = $request->getInt('offset', 0);
        if ($limit < 1 || $limit > 100) {
            return ApiResponder::error('invalid_limit', 'Limit must be between 1 and 100.', 422, ['field' => 'limit']);
        }
        if ($offset < 0) {
            return ApiResponder::error('invalid_offset', 'Offset must be zero or greater.', 422, ['field' => 'offset']);
        }

        $service = new CustomerPaymentPortalService(($this->pdoFactory)(), $this->intentService());
        if ($request->getMethod() === 'POST') {
            $result = $service->createHostedIntent($context->customerId, $request->getArray('payment'));
            if (!$result->success) {
                return ApiResponder::error('payment_intent_failed', $result->message, $result->statusCode ?: 422);
            }

            return ApiResponder::ok([
                'payment_intent' => [
                    'provider' => $result->provider,
                    'id' => $result->paymentIntentId,
                    'client_secret' => $result->clientSecret,
                    'status' => $result->status,
                ],
            ], [
                'resource' => 'customer-payments',
                'customer_id' => $context->customerId,
                'provider' => 'stripe',
                'mode' => 'hosted_intent',
            ], 201);
        }

        $from = trim($request->getString('from'));
        $to = trim($request->getString('to'));
        foreach (['from' => $from, 'to' => $to] as $field => $value) {
            if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $value) !== 1) {
                return ApiResponder::error('invalid_' . $field, ucfirst($field) . ' must be YYYY-MM-DD or YYYY-MM-DD HH:MM:SS.', 422, ['field' => $field]);
            }
        }

        try {
            $history = $service->history($context->customerId, $limit, $offset, $from, $to);
        } catch (\Throwable $exception) {
            return ApiResponder::error('customer_payment_query_failed', $exception->getMessage(), 500);
        }

        return ApiResponder::ok([
            'payments' => $history['payments']['items'],
            'documents' => $history['documents'],
        ], [
            'resource' => 'customer-payments',
            'customer_id' => $context->customerId,
            'limit' => $limit,
            'offset' => $offset,
            'columns' => $history['payments']['columns'],
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    private function intentService(): PaymentIntentService
    {
        if ($this->intentServiceFactory !== null) {
            return ($this->intentServiceFactory)();
        }

        return new PaymentIntentService(new StripePaymentIntentClient($this->config->string('STRIPE_SECRET_KEY')));
    }
}
