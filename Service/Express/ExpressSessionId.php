<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Express;

use Magento\Checkout\Model\Session;

/**
 * The session id an express token and its payment share.
 *
 * monei.js makes the express token in the browser with the account id only. MONEI
 * binds a token made without a payment or session id to the browser's IP and user
 * agent, so the store's own confirm call can never use it. With the same session id
 * on the token and on the payment, MONEI checks that pair instead.
 */
class ExpressSessionId
{
    private const SESSION_KEY = 'monei_express_session_id';

    /**
     * @var Session
     */
    private Session $checkoutSession;

    /**
     * @param Session $checkoutSession Checkout session
     */
    public function __construct(Session $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * The id for this shopper's session, created on first use.
     *
     * @return string
     */
    public function get(): string
    {
        $id = (string) $this->checkoutSession->getData(self::SESSION_KEY);

        if ('' === $id) {
            $id = bin2hex(random_bytes(16));
            $this->checkoutSession->setData(self::SESSION_KEY, $id);
        }

        return $id;
    }
}
