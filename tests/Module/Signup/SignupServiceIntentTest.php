<?php

declare(strict_types=1);

namespace Tests\Module\Signup;

use A2BillingPlus\Module\Signup\SignupServiceIntent;
use PHPUnit\Framework\TestCase;

final class SignupServiceIntentTest extends TestCase
{
    public function testItSanitizesServiceForLegacyHiddenFields(): void
    {
        $intent = SignupServiceIntent::fromRaw(" Business, Line <script>alert('x')</script> ");

        self::assertSame('Business Line scriptalertx/script', $intent->service());
        self::assertSame('service=Business%20Line%20scriptalertx%2Fscript', $intent->queryString());
    }

    public function testItMergesServiceIntoTrafficTarget(): void
    {
        $intent = SignupServiceIntent::fromRaw('personal-line');

        self::assertSame('VectaVoIP service: personal-line', $intent->mergeTrafficTarget(''));
        self::assertSame(
            'Needs DID | VectaVoIP service: personal-line',
            $intent->mergeTrafficTarget('Needs DID')
        );
    }

    public function testItDoesNotDuplicateExistingNote(): void
    {
        $intent = SignupServiceIntent::fromRaw('SIP Trunk Service');
        $existing = 'VectaVoIP service: SIP Trunk Service';

        self::assertSame($existing, $intent->mergeTrafficTarget($existing));
    }
}
