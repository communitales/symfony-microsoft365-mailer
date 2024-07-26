<?php

/**
 * @copyright   Copyright (c) 2024 Communitales GmbH (https://www.communitales.com/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Communitales\Symfony\Component\Mailer\Bridge\Microsoft365\Tests\Unit\Transport;

use Communitales\Symfony\Component\Mailer\Bridge\Microsoft365\Transport\Microsoft365ApiTransport;
use Communitales\Symfony\Component\Mailer\Bridge\Microsoft365\Transport\Microsoft365TransportFactory;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Test\TransportFactoryTestCase;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Class Microsoft365TransportFactoryTest
 */
class Microsoft365TransportFactoryTest extends TransportFactoryTestCase
{
    public function getFactory(): TransportFactoryInterface
    {
        return new Microsoft365TransportFactory(null, new MockHttpClient(), new NullLogger());
    }

    /**
     * @return iterable<array{Dsn, bool}>
     */
    public static function supportsProvider(): iterable
    {
        yield [
            new Dsn('microsoft365+api', 'default'),
            true,
        ];
    }

    /**
     * @return iterable<array{Dsn, TransportInterface}>
     */
    public static function createProvider(): iterable
    {
        $client = new MockHttpClient();
        $logger = new NullLogger();

        yield [
            new Dsn(
                'microsoft365+api',
                'default',
                self::USER,
                self::PASSWORD,
                null,
                ['tenant_id' => 'tenantId', 'username' => 'u$sername']
            ),
            new Microsoft365ApiTransport(
                self::USER,
                self::PASSWORD,
                'tenantId',
                'u$sername',
                $client,
                null,
                $logger
            ),
        ];
    }

    /**
     * @return iterable<array{Dsn}>
     */
    public static function unsupportedSchemeProvider(): iterable
    {
        yield [
            new Dsn('microsoft365+foo', 'default', self::USER, self::PASSWORD),
            'The "microsoft365+foo" scheme is not supported; supported schemes for mailer "microsoft365" are: "microsoft365+api".',
        ];
    }

    /**
     * @return iterable<array{Dsn}>
     */
    public static function incompleteDsnProvider(): iterable
    {
        yield [new Dsn('microsoft365+api', 'default', self::USER)];
        yield [new Dsn('microsoft365+api', 'default', self::USER, self::PASSWORD)];
        yield [new Dsn('microsoft365+api', 'default', self::USER, self::PASSWORD, null, ['tenant_id' => 'tenantId'])];
    }
}
