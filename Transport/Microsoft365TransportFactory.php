<?php

/**
 * @copyright   Copyright (c) 2024 Communitales GmbH (https://www.communitales.com/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Communitales\Symfony\Component\Mailer\Bridge\Microsoft365\Transport;

use RuntimeException;
use Symfony\Component\Mailer\Exception\IncompleteDsnException;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class Microsoft365TransportFactory
 */
final class Microsoft365TransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if ('microsoft365+api' === $scheme) {
            $clientId = $this->getUser($dsn);
            $clientSecret = $this->getPassword($dsn);
            /** @var string $tenantId */
            $tenantId = $dsn->getOption('tenant_id')
                ?? throw new IncompleteDsnException('Option tenant_id is not set.');
            /** @var string $username */
            $username = $dsn->getOption('username')
                ?? throw new IncompleteDsnException('Option username is not set.');
            if (!$this->client instanceof HttpClientInterface) {
                throw new RuntimeException('No HttpClient was set.');
            }

            return new Microsoft365ApiTransport(
                $clientId,
                $clientSecret,
                $tenantId,
                $username,
                $this->client,
                $this->dispatcher,
                $this->logger,
                null
            );
        }

        throw new UnsupportedSchemeException($dsn, 'microsoft365', $this->getSupportedSchemes());
    }

    /**
     * @return string[]
     */
    protected function getSupportedSchemes(): array
    {
        return ['microsoft365+api'];
    }
}
