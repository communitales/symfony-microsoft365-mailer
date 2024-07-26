<?php

/**
 * @copyright   Copyright (c) 2024 Communitales GmbH (https://www.communitales.com/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Communitales\Symfony\Component\Mailer\Bridge\Microsoft365\Tests\Integration\Transport;

use Communitales\Symfony\Component\Mailer\Bridge\Microsoft365\Transport\Microsoft365ApiTransport;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

use function basename;
use function file_get_contents;

class Microsoft365ApiTransportTest extends TestCase
{
    private Mailer $mailer;

    protected function setUp(): void
    {
        $dotenv = new Dotenv();
        $dotenv->loadEnv(__DIR__.'/../../../.env.test', overrideExistingVars: true);

        $tenantId = $_ENV['MICROSOFT_365_TENANT_ID'];
        $clientId = $_ENV['MICROSOFT_365_CLIENT_ID'];
        $clientSecret = $_ENV['MICROSOFT_365_CLIENT_SECRET'];
        $username = $_ENV['MICROSOFT_365_USERNAME'];
        $client = new NativeHttpClient();

        $transport = new Microsoft365ApiTransport(
            $clientId,
            $clientSecret,
            $tenantId,
            $username,
            $client
        );

        $this->mailer = new Mailer($transport);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testSend(): void
    {
        $email = (new Email())
            ->html('Hello.')
            ->subject('Microsoft 365 Integration Test 1 - Plain Message')
            ->from(new Address($_ENV['MAILER_FROM_EMAIL'], $_ENV['MAILER_FROM_NAME']))
            ->to(new Address($_ENV['MAILER_TO_EMAIL'], $_ENV['MAILER_TO_NAME']));

        $this->mailer->send($email);

        self::assertTrue(true);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testSendWithSmallAttachment(): void
    {
        $filename = __DIR__.'/../../Fixtures/attachment-small.png';
        $fileContent = file_get_contents($filename);
        if ($fileContent === false) {
            throw new RuntimeException('Failed to read file: '.$filename);
        }

        $email = (new Email())
            ->html('Hello.')
            ->subject('Microsoft 365 Integration Test 2 - With Attachment')
            ->from(new Address($_ENV['MAILER_FROM_EMAIL'], $_ENV['MAILER_FROM_NAME']))
            ->to(new Address($_ENV['MAILER_TO_EMAIL'], $_ENV['MAILER_TO_NAME']))
            ->attach(
                $fileContent,
                basename($filename),
                'image/png'
            );

        $this->mailer->send($email);

        self::assertTrue(true);
    }

    /**
     * @throws TransportExceptionInterface
     */
    public function testSendWithLargeAttachment(): void
    {
        $filename = __DIR__.'/../../Fixtures/attachment-large.png';
        $fileContent = file_get_contents($filename);
        if ($fileContent === false) {
            throw new RuntimeException('Failed to read file: '.$filename);
        }

        $email = (new Email())
            ->html('Hello.')
            ->subject('Microsoft 365 Integration Test 3 - With Large Attachment')
            ->from(new Address($_ENV['MAILER_FROM_EMAIL'], $_ENV['MAILER_FROM_NAME']))
            ->to(new Address($_ENV['MAILER_TO_EMAIL'], $_ENV['MAILER_TO_NAME']))
            ->attach(
                $fileContent,
                basename($filename),
                'image/png'
            );

        $this->mailer->send($email);

        self::assertTrue(true);
    }
}
