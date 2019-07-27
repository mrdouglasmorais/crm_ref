<?php

namespace Omnipay\AuthorizeNet\Message;

use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Authorize.Net SIM Complete Authorize Request
 */
class SIMCompleteAuthorizeRequest extends SIMAbstractRequest
{
    /**
     * Get the transaction ID passed in through the custom field.
     * This is used to look up the transaction in storage.
     */
    public function getTransactionId()
    {
        return $this->httpRequest->request->get(static::TRANSACTION_ID_PARAM);
    }

    public function getData()
    {
        // The hash sent in the callback from the Authorize.Net gateway.
        $hash_posted = strtolower($this->httpRequest->request->get('x_MD5_Hash'));

        // The transaction reference generated by the Authorize.Net gateway and sent in the callback.
        $posted_transaction_reference = $this->httpRequest->request->get('x_trans_id');

        // The amount that the callback has authorized.
        $posted_amount = $this->httpRequest->request->get('x_amount');

        // Calculate the hash locally, using the shared "hash secret" and login ID.
        $hash_calculated = $this->getHash($posted_transaction_reference, $posted_amount);

        if ($hash_posted !== $hash_calculated) {
            // If the hash is incorrect, then we can't trust the source nor anything sent.
            // Throwing exceptions here is probably a bad idea. We are trying to get the data,
            // and if it is invalid, then we need to be able to log that data for analysis.
            // Except we can't, baceuse the exception means we can't get to the data.
            // For now, this is consistent with other OmniPay gateway drivers.

            throw new InvalidRequestException('Incorrect hash');
        }

        // The hashes have passed, but the amount should also be validated against the
        // amount in the stored and retrieved transaction. If the application has the
        // ability to retrieve the transaction (using the transaction_id sent as a custom
        // form field, or perhaps in an otherwise unused field such as x_invoice_id.

        $amount = $this->getAmount();

        if (isset($amount) && $amount != $posted_amount) {
            // The amounts don't match. Someone may have been playing with the
            // transaction references.

            throw new InvalidRequestException('Incorrect amount');
        }

        return $this->httpRequest->request->all();
    }

    /**
     * CHECKME: should this be the transactionReference in the hash, not the transactionId?
     * The transaction reference and the amount are both sent by the remote gateway (x_trans_id
     * and x_amount) and it is those that should be checked against.
     * @param $transaction_reference
     * @param $amount
     * @return string
     */
    public function getHash($transaction_reference, $amount)
    {
        $key = array(
            $this->getHashSecret(),
            $this->getApiLoginId(),
            $transaction_reference,
            $amount,
         );

        return md5(implode('', $key));
    }

    public function sendData($data)
    {
        return $this->response = new SIMCompleteAuthorizeResponse($this, $data);
    }
}
