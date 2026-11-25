<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Block\System\Config\Form\Field;

use Magento\Backend\Block\AbstractBlock;
use Magento\Backend\Block\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Data\Form\Element\Renderer\RendererInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Monei\MoneiPayment\Model\Config\Backend\ApiKey;
use Monei\MoneiPayment\Model\Config\Backend\CredentialValue;
use Monei\MoneiPayment\Service\Connect\CallbackUrl;
use Monei\MoneiPayment\Service\Connect\ConnectService;

/**
 * The "Connect with MONEI" row of the payment configuration, for the scope being edited.
 *
 * The buttons are no submit buttons of the config form: Enter in a key field would press the first one. Each posts
 * its own form, built on click, with the backend form key and the scope.
 */
class ConnectButton extends AbstractBlock implements RendererInterface
{
    /**
     * @var CallbackUrl
     */
    private CallbackUrl $callbackUrl;

    /**
     * @var ConnectService
     */
    private ConnectService $connectService;

    /**
     * @var SecureHtmlRenderer
     */
    private SecureHtmlRenderer $secureRenderer;

    /**
     * @var FormKey
     */
    private FormKey $formKey;

    /**
     * @param Context $context
     * @param CallbackUrl $callbackUrl
     * @param ConnectService $connectService
     * @param SecureHtmlRenderer $secureRenderer
     * @param FormKey $formKey
     * @param array $data
     */
    public function __construct(
        Context $context,
        CallbackUrl $callbackUrl,
        ConnectService $connectService,
        SecureHtmlRenderer $secureRenderer,
        FormKey $formKey,
        array $data = []
    ) {
        $this->callbackUrl = $callbackUrl;
        $this->connectService = $connectService;
        $this->secureRenderer = $secureRenderer;
        $this->formKey = $formKey;
        parent::__construct($context, $data);
    }

    /**
     * Render element html.
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $website = $this->getRequest()->getParam('website');
        $store = $this->getRequest()->getParam('store');
        [$scope, $scopeId] = CallbackUrl::scopeOf($website, $store);
        $keys = $this->connectService->apiKeys($scope, $scopeId);

        $canConnect = $this->callbackUrl->redirectUri($scope, $scopeId) !== null;

        $html = $this->resultHtml($scope, $scopeId)
            . $this->loadedHtml($keys + ['mode' => $this->connectService->mode($scope, $scopeId)]);
        if ($canConnect && (strpos($keys['live'], 'pk_') === 0 || strpos($keys['test'], 'pk_') === 0)) {
            $html .= $this->messageHtml(
                'warning',
                (string) __('Connect with MONEI to stop storing your account API key.')
            );
        }
        if ($keys['live'] !== '') {
            $html .= $this->noteHtml((string) __('Production key in use: %1', ApiKey::mask($keys['live'])));
        }
        if ($keys['test'] !== '') {
            $html .= $this->noteHtml((string) __('Test key in use: %1', ApiKey::mask($keys['test'])));
        }

        $scopeFields = ['website' => (string) $website, 'store' => (string) $store];
        if (!$canConnect) {
            $html .= '<p>' . $this->_escaper->escapeHtml(
                (string) __('Connect with MONEI is not available for the URL of this store. Use manual keys.')
            ) . '</p>';
        } else {
            $html .= $this->buttonHtml(
                'monei-connect-button',
                (string) __('Connect with MONEI'),
                $this->postScript($this->getUrl('monei/connect/start'), $scopeFields, true)
            );
        }
        if ($this->connectService->connectedModes($scope, $scopeId)) {
            $confirm = json_encode((string) __('Disconnect MONEI from this scope?'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            $html .= ' ' . $this->buttonHtml(
                'monei-disconnect-button',
                (string) __('Disconnect'),
                'if (!confirm(' . $confirm . ')) { return; } '
                    . $this->postScript($this->getUrl('monei/connect/disconnect'), $scopeFields, false)
            );
            $html .= '<p class="note">' . $this->_escaper->escapeHtml(
                (string) __('The keys stay active until you revoke them in the MONEI Dashboard:')
            ) . ' <a href="' . $this->_escaper->escapeUrl(ConnectService::DASHBOARD_KEYS_URL)
                . '" target="_blank" rel="noopener noreferrer">'
                . $this->_escaper->escapeHtml(ConnectService::DASHBOARD_KEYS_URL) . '</a></p>';
        }

        return \sprintf(
            '<tr id="row_%s"><td class="label"><label>%s</label></td><td class="value">%s</td><td></td></tr>',
            $this->_escaper->escapeHtmlAttr($element->getHtmlId()),
            $this->_escaper->escapeHtml((string) $element->getLabel()),
            $html
        );
    }

    /**
     * Render the result of the last connect of this scope, once
     *
     * @param string $scope
     * @param int $scopeId
     * @return string
     */
    private function resultHtml(string $scope, int $scopeId): string
    {
        $result = $this->connectService->takeResult($scope, $scopeId);
        if ($result === null) {
            return '';
        }
        $html = '';
        if ($result['switched']) {
            $html .= $this->messageHtml('success', (string) __('MONEI connected.'));
        }
        foreach ($result['problems'] as $problem) {
            $html .= $this->messageHtml('error', ConnectService::message($problem['mode'] ?? null, (string) $problem['code']));
        }

        return $html;
    }

    /**
     * Render the fingerprint of each value in use, posted with the config form: CredentialValue uses it to tell a
     * save of this page from a save of a page loaded before a Connect or a Disconnect
     *
     * @param array{live: string, test: string, mode: string} $values
     * @return string
     */
    private function loadedHtml(array $values): string
    {
        $html = '';
        foreach ($values as $name => $value) {
            $html .= '<input type="hidden" name="' . CredentialValue::LOADED_PARAM . '[' . $name . ']" value="'
                . CredentialValue::fingerprint($value) . '"/>';
        }

        return $html;
    }

    /**
     * Render an admin message
     *
     * @param string $type success, warning or error
     * @param string $text
     * @return string
     */
    private function messageHtml(string $type, string $text): string
    {
        return '<div class="message message-' . $type . '">' . $this->_escaper->escapeHtml($text) . '</div>';
    }

    /**
     * Render a note
     *
     * @param string $text
     * @return string
     */
    private function noteHtml(string $text): string
    {
        return '<p class="note">' . $this->_escaper->escapeHtml($text) . '</p>';
    }

    /**
     * Render a button with its click handler
     *
     * @param string $id
     * @param string $label
     * @param string $javascript
     * @return string
     */
    private function buttonHtml(string $id, string $label, string $javascript): string
    {
        return '<button type="button" class="action-default" id="' . $id . '">'
            . $this->_escaper->escapeHtml($label) . '</button>'
            . $this->secureRenderer->renderEventListenerAsTag('onclick', $javascript, '#' . $id);
    }

    /**
     * Get the JavaScript that posts the form key and the scope to an admin route
     *
     * @param string $action
     * @param array<string, string> $fields
     * @param bool $newTab
     * @return string
     */
    private function postScript(string $action, array $fields, bool $newTab): string
    {
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        $fields = ['form_key' => $this->formKey->getFormKey()] + $fields;

        return 'var form = document.createElement("form"); form.method = "post"; form.action = '
            . json_encode($action, $flags) . ';' . ($newTab ? ' form.target = "_blank";' : '')
            . ' var fields = ' . json_encode($fields, $flags) . ';'
            . ' Object.keys(fields).forEach(function (name) { var input = document.createElement("input");'
            . ' input.type = "hidden"; input.name = name; input.value = fields[name]; form.appendChild(input); });'
            . ' document.body.appendChild(form); form.submit(); form.remove();';
    }
}
