<?php

/**
 * @copyright   Copyright (c) 2024 Communitales GmbH (https://www.communitales.com/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Communitales\Symfony\Component\Mailer\Bridge\Microsoft365\Tests\Unit\Transport;

use Communitales\Symfony\Component\Mailer\Bridge\Microsoft365\Transport\Microsoft365ApiTransport;
use Http\Promise\FulfilledPromise;
use Microsoft\Graph\Generated\Models\Message;
use Microsoft\Graph\Generated\Models\UploadSession;
use Microsoft\Kiota\Abstractions\ObservabilityOptions;
use Microsoft\Kiota\Abstractions\RequestAdapter;
use Microsoft\Kiota\Abstractions\RequestInformation;
use Microsoft\Kiota\Abstractions\Serialization\SerializationWriterFactoryRegistry;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function basename;
use function str_repeat;

/**
 * Class Microsoft365ApiTransportTest
 */
class Microsoft365ApiTransportTest extends TestCase
{
    public function testToString(): void
    {
        $transport = $this->createTransport(new MockHttpClient());
        $this->assertSame(
            'microsoft365+api://client-id-xxxxxxxx@default?tenant_id=example.onmicrosoft.com&username=info%40example.com',
            (string)$transport
        );
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    public function testSend(): void
    {
        $client = new MockHttpClient();

        $requestInformation = new RequestInformation(new ObservabilityOptions());
        $requestInformation->urlTemplate = '{+baseurl}/users/{user%2Did}/sendMail';
        $requestInformation->pathParameters = [
            'baseurl' => '',
            'user%2Did' => 'info@example.com',
        ];
        $requestInformation->httpMethod = 'POST';

        $content = '{"Message":{"@odata.type":"#microsoft.graph.message","bccRecipients":[],"body":{"content":"Hello.","contentType":"html"},"ccRecipients":[],"from":{"emailAddress":{"address":"from@example.com","name":"John From Doe"}},"replyTo":[],"subject":"Microsoft 365 Unit Test 1 - Plain Message","toRecipients":[{"emailAddress":{"address":"to@example.com","name":"John To Doe"}}]}}';

        $requestAdapter = $this->createMock(RequestAdapter::class);
        $requestAdapter->method('getSerializationWriterFactory')
            ->willReturn(SerializationWriterFactoryRegistry::getDefaultInstance());
        $requestAdapter->expects($this->once())
            ->method('sendNoContentAsync')
            ->with(
                $this->callback(function (RequestInformation $value) use ($requestInformation, $content): true {
                    $this->assertSame($requestInformation->urlTemplate, $value->urlTemplate);
                    $this->assertSame($requestInformation->pathParameters, $value->pathParameters);
                    $this->assertSame($requestInformation->httpMethod, $value->httpMethod);
                    $this->assertSame($content, (string)$value->content);

                    return true;
                })
            );

        $transport = $this->createTransport($client, $requestAdapter);

        $email = (new Email())
            ->html('Hello.')
            ->subject('Microsoft 365 Unit Test 1 - Plain Message')
            ->from(new Address('from@example.com', 'John From Doe'))
            ->to(new Address('to@example.com', 'John To Doe'));

        $message = $transport->send($email);

        $this->assertInstanceOf(SentMessage::class, $message);
        $this->assertStringEndsWith('@example.com', $message->getMessageId());
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     *
     * @see https://symfony.com/doc/current/mailer.html#always-send-to-the-same-address
     */
    public function testSendDefaultSender(): void
    {
        $client = new MockHttpClient();

        $requestInformation = new RequestInformation(new ObservabilityOptions());
        $requestInformation->urlTemplate = '{+baseurl}/users/{user%2Did}/sendMail';
        $requestInformation->pathParameters = [
            'baseurl' => '',
            'user%2Did' => 'info@example.com',
        ];
        $requestInformation->httpMethod = 'POST';

        $content = '{"Message":{"@odata.type":"#microsoft.graph.message","bccRecipients":[],"body":{"content":"Hello.","contentType":"html"},"ccRecipients":[],"from":{"emailAddress":{"address":"from@example.com","name":"John From Doe"}},"replyTo":[],"subject":"Microsoft 365 Unit Test 1 - Plain Message With Default recipient","toRecipients":[{"emailAddress":{"address":"to-default@example.com"}}]}}';

        $requestAdapter = $this->createMock(RequestAdapter::class);
        $requestAdapter->method('getSerializationWriterFactory')
            ->willReturn(SerializationWriterFactoryRegistry::getDefaultInstance());
        $requestAdapter->expects($this->once())
            ->method('sendNoContentAsync')
            ->with(
                $this->callback(function (RequestInformation $value) use ($requestInformation, $content): true {
                    $this->assertSame($requestInformation->urlTemplate, $value->urlTemplate);
                    $this->assertSame($requestInformation->pathParameters, $value->pathParameters);
                    $this->assertSame($requestInformation->httpMethod, $value->httpMethod);
                    $this->assertSame($content, (string)$value->content);

                    return true;
                })
            );

        $transport = $this->createTransport($client, $requestAdapter);

        $email = (new Email())
            ->html('Hello.')
            ->subject('Microsoft 365 Unit Test 1 - Plain Message With Default recipient')
            ->from(new Address('from@example.com', 'John From Doe'))
            ->to(new Address('to@example.com', 'John To Doe'));

        // Envelope is removing the name of the recipients
        $envelope = new Envelope(
            new Address('from@example.com', 'John From Doe'),
            [new Address('to-default@example.com', 'John To Doe')]
        );

        $message = $transport->send($email, $envelope);

        $this->assertInstanceOf(SentMessage::class, $message);
        $this->assertStringEndsWith('@example.com', $message->getMessageId());
    }

    /**
     * @throws TransportExceptionInterface|Exception
     */
    public function testSendWithSmallAttachment(): void
    {
        $client = new MockHttpClient();

        $requestInformation = new RequestInformation(new ObservabilityOptions());
        $requestInformation->urlTemplate = '{+baseurl}/users/{user%2Did}/sendMail';
        $requestInformation->pathParameters = [
            'baseurl' => '',
            'user%2Did' => 'info@example.com',
        ];
        $requestInformation->httpMethod = 'POST';

        $content = '{"Message":{"@odata.type":"#microsoft.graph.message","attachments":[{"@odata.type":"#microsoft.graph.fileAttachment","contentType":"image/png","isInline":false,"name":"attachment-small.png","contentBytes":"Li4uLi4uLi4uLg==","contentId":"id-unit-test@symfony"}],"bccRecipients":[],"body":{"content":"Hello.","contentType":"html"},"ccRecipients":[],"from":{"emailAddress":{"address":"from@example.com","name":"John From Doe"}},"hasAttachments":true,"replyTo":[],"subject":"Microsoft 365 Unit Test 2 - With Attachment","toRecipients":[{"emailAddress":{"address":"to@example.com","name":"John To Doe"}}]}}';

        $requestAdapter = $this->createMock(RequestAdapter::class);
        $requestAdapter->method('getSerializationWriterFactory')
            ->willReturn(SerializationWriterFactoryRegistry::getDefaultInstance());
        $requestAdapter->expects($this->once())
            ->method('sendNoContentAsync')
            ->with(
                $this->callback(function (RequestInformation $value) use ($requestInformation, $content): true {
                    $this->assertSame($requestInformation->urlTemplate, $value->urlTemplate);
                    $this->assertSame($requestInformation->pathParameters, $value->pathParameters);
                    $this->assertSame($requestInformation->httpMethod, $value->httpMethod);
                    $this->assertSame($content, (string)$value->content);

                    return true;
                })
            );

        $transport = $this->createTransport($client, $requestAdapter);

        $filename = '/tmp/attachment-small.png';
        $fileContent = str_repeat('.', 10);

        $attachment = new DataPart(
            $fileContent,
            basename($filename),
            'image/png'
        );
        $attachment->setContentId('id-unit-test@symfony');

        $email = (new Email())
            ->html('Hello.')
            ->subject('Microsoft 365 Unit Test 2 - With Attachment')
            ->from(new Address('from@example.com', 'John From Doe'))
            ->to(new Address('to@example.com', 'John To Doe'))
            ->addPart($attachment);

        $message = $transport->send($email);

        $this->assertInstanceOf(SentMessage::class, $message);
        $this->assertStringEndsWith('@example.com', $message->getMessageId());
    }

    /**
     * @throws TransportExceptionInterface|Exception
     */
    public function testSendWithLargeAttachment(): void
    {
        $client = new MockHttpClient();

        $expectedUrlTemplates = [
            '{+baseurl}/users/{user%2Did}/messages{?%24count,%24expand,%24filter,%24orderby,%24search,%24select,%24skip,%24top,includeHiddenMessages*}',
            '{+baseurl}/users/{user%2Did}/messages/{message%2Did}/attachments/createUploadSession',
            '{+baseurl}/users/{user%2Did}/messages/{message%2Did}/send',
        ];
        $sentUrlTemplates = [];

        $expectedContent = [
            '{"@odata.type":"#microsoft.graph.message","bccRecipients":[],"body":{"content":"Hello.","contentType":"html"},"ccRecipients":[],"from":{"emailAddress":{"address":"from@example.com","name":"John From Doe"}},"replyTo":[],"subject":"Microsoft 365 Unit Test 3 - With Large Attachment","toRecipients":[{"emailAddress":{"address":"to@example.com","name":"John To Doe"}}]}',
            '{"AttachmentItem":{"attachmentType":"file","name":"attachment-small.png","size":4500000}}',
            '',
        ];
        $sentContent = [];

        $message = new Message();
        $message->setId('mock-id-1');

        $responseCreate = new FulfilledPromise($message);

        $uploadSession = new UploadSession();
        $uploadSession->setUploadUrl('https://example.com/upload');

        $responseUploadSession = new FulfilledPromise($uploadSession);

        $requestAdapter = $this->createMock(RequestAdapter::class);
        $requestAdapter->method('getSerializationWriterFactory')
            ->willReturn(SerializationWriterFactoryRegistry::getDefaultInstance());
        $requestAdapter->expects($this->exactly(2))
            ->method('sendAsync')
            ->with(
                $this->callback(function (RequestInformation $value) use (&$sentContent, &$sentUrlTemplates): true {
                    $sentUrlTemplates[] = $value->urlTemplate;
                    $sentContent[] = (string)$value->content;

                    return true;
                })
            )
            ->willReturnOnConsecutiveCalls(
                $responseCreate,
                $responseUploadSession,
                null
            );
        $requestAdapter->method('sendNoContentAsync')
            ->with(
                $this->callback(function (RequestInformation $value) use (&$sentContent, &$sentUrlTemplates): true {
                    $sentUrlTemplates[] = $value->urlTemplate;
                    $sentContent[] = (string)$value->content;

                    return true;
                })
            );

        $transport = $this->createTransport($client, $requestAdapter);

        $filename = '/tmp/attachment-small.png';
        $fileContent = str_repeat('.', 4500000);

        $email = (new Email())
            ->html('Hello.')
            ->subject('Microsoft 365 Unit Test 3 - With Large Attachment')
            ->from(new Address('from@example.com', 'John From Doe'))
            ->to(new Address('to@example.com', 'John To Doe'))
            ->attach(
                $fileContent,
                basename($filename),
                'image/png'
            );

        $message = $transport->send($email);

        $this->assertInstanceOf(SentMessage::class, $message);
        $this->assertStringEndsWith('@example.com', $message->getMessageId());
        $this->assertSame($expectedUrlTemplates, $sentUrlTemplates);
        $this->assertSame($expectedContent, $sentContent);

        // Upload 2 Chunks
        $this->assertSame(2, $client->getRequestsCount());
    }

    private function createTransport(
        HttpClientInterface $client,
        ?RequestAdapter $requestAdapter = null
    ): Microsoft365ApiTransport {
        $tenantId = 'example.onmicrosoft.com';
        $clientId = 'client-id-xxxxxxxx';
        $clientSecret = 'client-secret-xxxxxxxx';
        $username = 'info@example.com';

        return new Microsoft365ApiTransport(
            $clientId,
            $clientSecret,
            $tenantId,
            $username,
            $client,
            null,
            null,
            $requestAdapter
        );
    }
}
