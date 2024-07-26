<?php
/**
 * @copyright   Copyright (c) 2024 Communitales GmbH (https://www.communitales.com/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Communitales\Symfony\Component\Mailer\Bridge\Microsoft365\Transport;

use Microsoft\Graph\Generated\Models\EmailAddress;
use Microsoft\Graph\Generated\Models\Recipient;
use Symfony\Component\Mime\Address;

trait GraphModelMapperTrait
{
    private function mapMimeAddressToRecipient(Address $mimeAddress): Recipient
    {
        $emailAddress = new EmailAddress();
        if ($mimeAddress->getName() !== '') {
            $emailAddress->setName($mimeAddress->getName());
        }

        $emailAddress->setAddress($mimeAddress->getAddress());

        $recipient = new Recipient();
        $recipient->setEmailAddress($emailAddress);

        return $recipient;
    }
}
