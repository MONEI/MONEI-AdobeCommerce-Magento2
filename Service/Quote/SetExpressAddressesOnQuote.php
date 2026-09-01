<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Quote;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;

/**
 * Maps a wallet address payload onto a Magento quote.
 *
 * The direction matters: GetAddressDetailsByQuoteAddress maps Magento to MONEI.
 * This does the reverse, which nothing else in the module did.
 */
class SetExpressAddressesOnQuote
{
    /**
     * Fields Magento validates on both addresses before a quote can be submitted.
     *
     * Wallets withhold most personal data until approval and some never return a
     * telephone at all, so placeholders are required rather than optional. They are
     * corrected from the confirm response once the payment succeeds.
     */
    private const PLACEHOLDER_TELEPHONE = '000000000';

    /**
     * Apply the wallet's billing and shipping details to the quote.
     *
     * @param Quote   $quote
     * @param mixed[] $billing  Wallet billingDetails
     * @param mixed[] $shipping Wallet shippingDetails
     *
     * @throws LocalizedException
     */
    public function execute(Quote $quote, array $billing, array $shipping): void
    {
        // Either side may be absent depending on the wallet and whether shipping was
        // requested; each falls back to the other rather than failing.
        if (empty($shipping) || empty($shipping['address']['country'] ?? null)) {
            $shipping = $billing;
        }

        if (empty($billing) || empty($billing['address']['country'] ?? null)) {
            $billing = $shipping;
        }

        $billingData = $this->toMagentoAddress($billing);

        if (empty($billingData['country_id'])) {
            throw new LocalizedException(
                __('The wallet did not return an address, which is required to place the order.')
            );
        }

        $quote->getBillingAddress()->addData($billingData);

        if (!$quote->isVirtual()) {
            $quote->getShippingAddress()->addData($this->toMagentoAddress($shipping));
        }

        $name = $this->splitName((string) ($billing['name'] ?? ''));
        $quote->setCustomerFirstname($name['firstname']);
        $quote->setCustomerLastname($name['lastname']);
    }

    /**
     * Convert one wallet address payload into Magento address data.
     *
     * @param mixed[] $walletAddress
     *
     * @return mixed[]
     */
    private function toMagentoAddress(array $walletAddress): array
    {
        $address = $walletAddress['address'] ?? [];
        $name = $this->splitName((string) ($walletAddress['name'] ?? ''));

        $street = array_values(array_filter([
            $address['line1'] ?? null,
            $address['line2'] ?? null,
        ]));

        return [
            'firstname' => $name['firstname'],
            'lastname' => $name['lastname'],
            'email' => $walletAddress['email'] ?? null,
            'telephone' => ($walletAddress['phone'] ?? '') ?: self::PLACEHOLDER_TELEPHONE,
            'company' => $walletAddress['company'] ?? null,
            'street' => $street ?: [''],
            'city' => $address['city'] ?? '',
            'postcode' => $address['zip'] ?? '',
            'region' => $address['state'] ?? null,
            'country_id' => $address['country'] ?? null,
        ];
    }

    /**
     * Split a wallet's single display name into Magento's two fields.
     *
     * @param string $fullName
     *
     * @return array{firstname: string, lastname: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($parts) === 0) {
            // Magento validates both fields as required, so neither can be empty.
            return ['firstname' => '-', 'lastname' => '-'];
        }

        if (count($parts) === 1) {
            return ['firstname' => $parts[0], 'lastname' => '-'];
        }

        $lastname = (string) array_pop($parts);

        return ['firstname' => implode(' ', $parts), 'lastname' => $lastname];
    }
}
