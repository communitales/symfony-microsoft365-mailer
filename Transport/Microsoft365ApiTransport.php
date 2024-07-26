<?php

/**
 * @copyright   Copyright (c) 2024 Communitales GmbH (https://www.communitales.com/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Communitales\Symfony\Component\Mailer\Bridge\Microsoft365\Transport;

use Exception;
use GuzzleHttp\Psr7\Utils;
use Microsoft\Graph\Core\NationalCloud;
use Microsoft\Graph\Generated\Models\AttachmentItem;
use Microsoft\Graph\Generated\Models\AttachmentType;
use Microsoft\Graph\Generated\Models\BodyType;
use Microsoft\Graph\Generated\Models\Entity;
use Microsoft\Graph\Generated\Models\FileAttachment;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\Message;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Microsoft\Graph\Generated\Models\UploadSession;
use Microsoft\Graph\Generated\Users\Item\Messages\Item\Attachments\CreateUploadSession\CreateUploadSessionPostRequestBody;
use Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Abstractions\RequestAdapter;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Kiota\Serialization\Text\TextSerializationWriter;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SensitiveParameter;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

use function base64_encode;
use function ceil;
use function current;
use function in_array;
use function str_split;
use function strlen;

/**
 * Class Microsoft365ApiTransport
 */
class Microsoft365ApiTransport extends AbstractApiTransport
{
    use GraphModelMapperTrait;

    private const LARGE_ATTACHMENT = 3145728; // 3 MB

    public function __construct(
        #[SensitiveParameter] private readonly string $clientId,
        #[SensitiveParameter] private readonly string $clientSecret,
        #[SensitiveParameter] private readonly string $tenantId,
        private readonly string $username,
        HttpClientInterface $client,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
        private readonly ?RequestAdapter $requestAdapter = null
    ) {
        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        return sprintf(
            'microsoft365+api://%s@default?tenant_id=%s&username=%s',
            $this->clientId,
            $this->tenantId,
            urlencode($this->username)
        );
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        try {
            $hasLargeAttachments = false;
            $messageBody = $this->getSendMailRequestBody($email, $envelope, $hasLargeAttachments);

            if ($hasLargeAttachments) {
                $this->sendMessage($email, $messageBody->getMessage());
            } else {
                $this->sendSendmail($messageBody);
            }
        } catch (TransportExceptionInterface $transportException) {
            throw new TransportException(
                'Could not reach the remote Microsoft Graph server.',
                0,
                $transportException
            );
        } catch (ODataError $error) {
            throw new TransportException(
                'Unable to send an email: '.(string)($error->getError()?->getMessage()),
                $error->getCode(),
                $error
            );
        } catch (Throwable $throwable) {
            throw new TransportException(
                'Unable to send an email.',
                (int)$throwable->getCode(),
                $throwable
            );
        }

        // Microsoft Graph does not return a response. So use a mocked one.
        return new MockResponse();
    }

    /**
     * Send a mail without large attachments.
     *
     * @see https://learn.microsoft.com/en-us/graph/api/user-sendmail?view=graph-rest-1.0&tabs=http
     *
     * @throws Exception
     */
    private function sendSendmail(SendMailPostRequestBody $messageBody): void
    {
        $this->getGraphServiceClient()
            ->users()
            ->byUserId($this->username)
            ->sendMail()
            ->post($messageBody)
            ->wait();
    }

    /**
     * Send a mail with large attachments.
     *
     * @see https://learn.microsoft.com/en-us/graph/api/resources/message?view=graph-rest-1.0
     * @see https://learn.microsoft.com/en-us/graph/api/user-post-messages?view=graph-rest-1.0&tabs=php
     * @see https://learn.microsoft.com/en-us/graph/api/message-send?view=graph-rest-1.0&tabs=http
     *
     * @throws Exception|TransportExceptionInterface
     */
    private function sendMessage(Email $email, ?Message $message): void
    {
        if (!$message instanceof Message) {
            throw new RuntimeException('Message was not initialized.');
        }

        // Create draft.
        /** @var Message $response */
        $response = $this->getGraphServiceClient()
            ->users()
            ->byUserId($this->username)
            ->messages()
            ->post($message)
            ->wait();

        $messageId = $response->getId();
        if ($messageId === null) {
            $this->sendResponseException('Could not create message.', $response);
        }

        // Upload large attachments.
        foreach ($email->getAttachments() as $attachment) {
            $this->sendLargeAttachment($attachment, $messageId);
        }

        // Send the draft.
        $this->getGraphServiceClient()
            ->users()
            ->byUserId($this->username)
            ->messages()
            ->byMessageId($messageId)
            ->send()
            ->post()
            ->wait();
    }

    /**
     * Add all attachments >= 3 MB to a message.
     * Smaller attachments has been added inline to the message.
     *
     * @throws Exception|TransportExceptionInterface
     */
    private function sendLargeAttachment(DataPart $attachment, string $messageId): void
    {
        $fileName = $this->getContentDispositionFilename($attachment);
        $content = $attachment->getBody();
        $fileSize = strlen($content);
        if ($fileSize < self::LARGE_ATTACHMENT) {
            return;
        }

        $requestBody = new CreateUploadSessionPostRequestBody();

        $attachmentItem = new AttachmentItem();
        $attachmentItem->setAttachmentType(new AttachmentType(AttachmentType::FILE));
        $attachmentItem->setName($fileName);
        $attachmentItem->setSize($fileSize);

        $requestBody->setAttachmentItem($attachmentItem);

        /** @var UploadSession $result */
        $result = $this->getGraphServiceClient()
            ->users()
            ->byUserId($this->username)
            ->messages()
            ->byMessageId($messageId)
            ->attachments()
            ->createUploadSession()
            ->post($requestBody)
            ->wait();

        $uploadUrl = $result->getUploadUrl();
        if ($uploadUrl === null) {
            throw new RuntimeException('Es konnte keine Uploadsession erstellt werden.');
        }

        $this->uploadChunks($uploadUrl, $fileSize, $content);
    }

    /**
     * Upload large attachments in chunks of 4 MB.
     *
     * @throws TransportExceptionInterface
     */
    private function uploadChunks(string $uploadUrl, int $fileSize, string $content): void
    {
        if (!$this->client instanceof HttpClientInterface) {
            throw new RuntimeException('No Http Client was set.');
        }

        $fragSize = 1024 * 1024 * 4; //4mb at once...
        $numFragments = ceil($fileSize / $fragSize);
        $contentChunked = str_split($content, $fragSize);
        $bytesRemaining = $fileSize;
        $i = 0;

        while ($i < $numFragments) {
            $chunkSize = $fragSize;
            $numBytes = $fragSize;
            $start = $i * $fragSize;
            $end = $i * $fragSize + $chunkSize - 1;
            if ($bytesRemaining < $chunkSize) {
                $chunkSize = $bytesRemaining;
                $numBytes = $bytesRemaining;
                $end = $fileSize - 1;
            }

            $data = $contentChunked[$i];
            $content_range = 'bytes '.$start.'-'.$end.'/'.$fileSize;
            $headers = [
                'Content-Length' => $numBytes,
                'Content-Range' => $content_range,
            ];

            $this->client->request('PUT', $uploadUrl, [
                'headers' => $headers,
                'body' => $data,
                'timeout' => 1000,
            ]);

            // if response body is empty, then the file was successfully uploaded

            $bytesRemaining -= $chunkSize;
            ++$i;
        }
    }

    private function getGraphServiceClient(): GraphServiceClient
    {
        $tokenRequestContext = new ClientCredentialContext(
            $this->tenantId,
            $this->clientId,
            $this->clientSecret
        );

        return new GraphServiceClient($tokenRequestContext, [], NationalCloud::GLOBAL, $this->requestAdapter);
    }

    /**
     * Creates the mail request body.
     * Also checks if there are attachments larger than 3 MB. They must be uploaded using an upload session.
     *
     * @see https://learn.microsoft.com/en-us/graph/api/user-sendmail?view=graph-rest-1.0&tabs=http
     * @see https://docs.microsoft.com/en-us/graph/api/resources/message?view=graph-rest-1.0
     * @see https://learn.microsoft.com/en-us/graph/api/attachment-createuploadsession?view=graph-rest-1.0&tabs=http
     */
    private function getSendMailRequestBody(
        Email $email,
        Envelope $envelope,
        bool &$hasLargeAttachments
    ): SendMailPostRequestBody {
        $message = new Message();

        $from = current($email->getFrom());
        if ($from !== false) {
            $message->setFrom($this->mapMimeAddressToRecipient($from));
        }

        $this->addRecipientsToMessage($message, $email, $envelope);

        $message->setSubject($email->getSubject());

        $body = new ItemBody();
        $body->setContentType(new BodyType(BodyType::HTML));
        $body->setContent((string)$email->getHtmlBody());

        $message->setBody($body);

        $messageBody = new SendMailPostRequestBody();
        $messageBody->setMessage($message);

        $attachmentsList = [];

        foreach ($email->getAttachments() as $attachment) {
            $content = $attachment->getBody();
            $fileSize = strlen($content);
            if ($fileSize >= self::LARGE_ATTACHMENT) {
                $hasLargeAttachments = true;
                continue;
            }

            $fileAttachment = new FileAttachment();
            $fileAttachment->setOdataType('#microsoft.graph.fileAttachment');
            $fileAttachment->setContentId($fileAttachment->getContentId());
            $fileAttachment->setName($this->getContentDispositionFilename($attachment));
            $fileAttachment->setContentType($attachment->getMediaType().'/'.$attachment->getMediaSubtype());
            $fileAttachment->setContentBytes(Utils::streamFor(base64_encode($attachment->getBody())));
            $attachmentsList [] = $fileAttachment;
        }

        if ($attachmentsList !== []) {
            $message->setAttachments($attachmentsList);
            $message->setHasAttachments(true);
        }

        return $messageBody;
    }

    private function addRecipientsToMessage(Message $message, Email $email, Envelope $envelope): void
    {
        $to = [];
        $cc = [];
        $bcc = [];
        $replyTo = [];

        foreach ($envelope->getRecipients() as $address) {
            $recipient = $this->mapMimeAddressToRecipient($address);

            if (in_array($address, $email->getTo(), true)) {
                $to[] = $recipient;
            }

            if (in_array($address, $email->getCc(), true)) {
                $cc[] = $recipient;
            }

            if (in_array($address, $email->getBcc(), true)) {
                $bcc[] = $recipient;
            }

            if (in_array($address, $email->getReplyTo(), true)) {
                $replyTo[] = $recipient;
            }
        }

        $message->setToRecipients($to);
        $message->setCcRecipients($cc);
        $message->setBccRecipients($bcc);
        $message->setReplyTo($replyTo);
    }

    private function getContentDispositionFilename(DataPart $attachment): string
    {
        $header = $attachment->getPreparedHeaders()->get('Content-Disposition');

        if ($header instanceof ParameterizedHeader) {
            return $header->getParameter('filename');
        }

        return 'attachment';
    }

    private function sendResponseException(string $error, Entity $response): never
    {
        $writer = new TextSerializationWriter();
        $response->serialize($writer);
        $data = $writer->getSerializedContent()->getContents();

        throw new HttpTransportException(
            $error,
            new MockResponse($data),
            0
        );
    }
}
