<?php

/**
 * php version 8.1
 * @author    Monei <support@monei.com>
 * @copyright 2023 Monei
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Service\Connect;

use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Locale\ResolverInterface;

/**
 * The page of the Connect with MONEI callback, a whole HTML document, as in the WooCommerce and PrestaShop plugins.
 *
 * Colors come from the MONEI Dashboard theme (primary, success, destructive, foreground, muted). Nothing loads from
 * another host: the logo is inline and the font is the system font.
 *
 * The route is public, and a custom admin path hides the admin: only a page whose single-use state was valid
 * links to it.
 */
class ResultPage
{
    /** The MONEI logo, inline: a static URL can be on a CDN, another host. */
    private const LOGO = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0nMTQxJyBoZWlnaHQ9JzMxJyB4bWxucz0naHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmcnPgogICAgPHBhdGggaWQ9J21vbmVpLWxvZ28nCiAgICAgICAgICBkPSdNLjguOWg4LjZsNyAxMS4zIDctMTEuM0gzMnYyOS4zaC04LjFWMTMuM2wtNy41IDExLjVoLS4yTDguOCAxMy40djE2LjdoLThWLjl6bTUxIDIyLjVjMS4xIDAgMi4xLS4yIDMuMS0uNi45LS40IDEuNy0xIDIuMy0xLjcuNi0uNyAxLjEtMS41IDEuNS0yLjUuNC0uOS41LTIgLjUtM3YtLjFjMC0xLjEtLjItMi4xLS41LTMtLjQtLjktLjktMS44LTEuNS0yLjUtLjctLjctMS41LTEuMy0yLjQtMS43LS45LS40LTEuOS0uNi0zLjEtLjYtMS4xIDAtMi4xLjItMyAuNi0uOS40LTEuNyAxLTIuMyAxLjctLjcuNy0xLjIgMS41LTEuNSAyLjUtLjMuOS0uNSAyLS41IDN2LjFjMCAxLjEuMiAyLjEuNSAzIC40LjkuOSAxLjggMS41IDIuNS43LjcgMS40IDEuMyAyLjQgMS43LjkuNCAxLjkuNiAzIC42em0tLjEgNy4zYy0yLjMgMC00LjMtLjQtNi4zLTEuMi0xLjktLjgtMy42LTEuOS01LTMuMi0xLjQtMS40LTIuNS0zLTMuMy00LjgtLjgtMS44LTEuMi0zLjgtMS4yLTUuOXYtLjFjMC0yLjEuNC00LjEgMS4yLTUuOS44LTEuOSAxLjktMy41IDMuMy00LjggMS40LTEuNCAzLjEtMi41IDUtMy4zIDEuOS0uOCA0LTEuMiA2LjMtMS4yUzU2IC43IDU4IDEuNWMxLjkuOCAzLjYgMS45IDUgMy4yIDEuNCAxLjQgMi41IDMgMy4zIDQuOC44IDEuOCAxLjIgMy44IDEuMiA1Ljl2LjFjMCAyLjEtLjQgNC4xLTEuMiA1LjktLjggMS45LTEuOSAzLjUtMy4zIDQuOC0xLjQgMS40LTMuMSAyLjUtNSAzLjMtMS45LjgtNCAxLjItNi4zIDEuMnpNNzEuOC45aDcuNmwxMiAxNS41Vi45aDh2MjkuM2gtNy4xbC0xMi41LTE2djE2aC04Vi45em0zMi44IDBoMjMuNXY2LjloLTE1LjV2NC40aDE0djYuNGgtMTR2NC42aDE1Ljd2Ni45aC0yMy43Vi45em0yOCAwaDguMXYyOS4zaC04LjFWLjl6JwogICAgICAgICAgZmlsbD0nIzAwYzFiMScgZmlsbC1ydWxlPSdub256ZXJvJy8+Cjwvc3ZnPgo=';

    private const ICON_OK = '<path d="M20 6 9 17l-5-5"/>';

    /**
     * @var Escaper
     */
    private Escaper $escaper;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var FrontNameResolver
     */
    private FrontNameResolver $frontNameResolver;

    /**
     * @var ResolverInterface
     */
    private ResolverInterface $localeResolver;

    /**
     * @param Escaper $escaper
     * @param ScopeConfigInterface $scopeConfig
     * @param FrontNameResolver $frontNameResolver
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        Escaper $escaper,
        ScopeConfigInterface $scopeConfig,
        FrontNameResolver $frontNameResolver,
        ResolverInterface $localeResolver
    ) {
        $this->escaper = $escaper;
        $this->scopeConfig = $scopeConfig;
        $this->frontNameResolver = $frontNameResolver;
        $this->localeResolver = $localeResolver;
    }

    /**
     * Render the page of a connect result
     *
     * @param array $outcome The result of ConnectService::complete()
     * @param bool $stateChecked Whether complete() checked the state (it did not when it threw)
     * @return string
     */
    public function render(array $outcome, bool $stateChecked = true): string
    {
        $refused = ConnectService::isRefused($outcome);
        $linkAdmin = $stateChecked && !$refused;
        $ok = (bool) $outcome['switched'];
        if ($ok) {
            $title = (string) __('MONEI is connected');
        } elseif ($refused) {
            $title = (string) __('This connect link does not work');
        } else {
            $title = (string) __('MONEI is not connected');
        }
        $lines = '';
        foreach (array_keys($outcome['switched']) as $mode) {
            $text = (string) __('%1: connected.', $mode === 'live' ? __('Live') : __('Test'));
            $lines .= $this->lineHtml(true, $text);
        }
        foreach ($outcome['problems'] as $problem) {
            $lines .= $this->lineHtml(false, ConnectService::message($problem['mode'] ?? null, (string) $problem['code']));
        }
        $statusIcon = $ok ? self::ICON_OK : '<path d="M12 8v5M12 16.5v.01"/><circle cx="12" cy="12" r="9"/>';
        $next = !$linkAdmin
            ? '<p class="hint">' . $this->escaper->escapeHtml((string) __('Return to the MONEI settings in your admin panel.'))
                . '</p>'
            : '<a class="button" href="' . $this->escaper->escapeUrl($this->adminUrl()) . '">'
                . $this->escaper->escapeHtml((string) __('Go to MONEI settings')) . '</a>'
                . '<p class="hint">'
                . $this->escaper->escapeHtml((string) __('You can also close this tab and reload the settings page.'))
                . '</p>';

        return '<!DOCTYPE html>'
            . '<html lang="' . $this->escaper->escapeHtmlAttr(str_replace('_', '-', $this->localeResolver->getLocale())) . '">'
            . '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex, nofollow">'
            . '<title>' . $this->escaper->escapeHtml($title) . ' · MONEI</title><style>' . self::CSS . '</style></head>'
            . '<body><main class="card" role="status">'
            . '<img class="logo" src="' . self::LOGO . '" alt="MONEI" width="110" height="24">'
            . '<div class="status' . ($ok ? '' : ' status--error') . '" aria-hidden="true">'
            . '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"'
            . ' stroke-linecap="round" stroke-linejoin="round">' . $statusIcon . '</svg></div>'
            . '<h1>' . $this->escaper->escapeHtml($title) . '</h1>'
            . ($lines === '' ? '' : '<ul class="lines">' . $lines . '</ul>')
            . $next
            . '</main></body></html>';
    }

    /**
     * Get the admin base URL: a front route cannot make an admin link with a valid secret key
     *
     * @return string
     */
    public function adminUrl(): string
    {
        if ($this->scopeConfig->isSetFlag(FrontNameResolver::XML_PATH_USE_CUSTOM_ADMIN_URL)) {
            $base = (string) $this->scopeConfig->getValue(FrontNameResolver::XML_PATH_CUSTOM_ADMIN_URL);
        } else {
            $secure = $this->scopeConfig->isSetFlag('web/secure/use_in_adminhtml');
            $base = (string) $this->scopeConfig->getValue($secure ? 'web/secure/base_url' : 'web/unsecure/base_url');
        }

        return rtrim($base, '/') . '/' . $this->frontNameResolver->getFrontName() . '/';
    }

    /**
     * Render one result line
     *
     * @param bool $ok
     * @param string $text
     * @return string
     */
    private function lineHtml(bool $ok, string $text): string
    {
        $icon = $ok
            ? '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"'
                . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . self::ICON_OK . '</svg>'
            : '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"'
                . ' stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8v.01"/></svg>';

        return '<li class="' . ($ok ? 'line--ok' : 'line--problem') . '">' . $icon
            . '<span>' . $this->escaper->escapeHtml($text) . '</span></li>';
    }

    private const CSS = ':root{--primary:#65427e;--primary-hover:#74558b;--primary-foreground:#fafafa;'
        . '--success:#00a96e;--success-tint:rgba(0,169,110,.1);--destructive:#df2225;'
        . '--destructive-tint:rgba(223,34,37,.1);--foreground:#0a0a0a;--muted-foreground:#5e5e5e;'
        . '--background:#f3f3f3;--muted:#f5f5f5;--line:#e5e5e5}'
        . '*,*::before,*::after{box-sizing:border-box}'
        . 'body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px 16px;'
        . 'background:var(--background);color:var(--foreground);'
        . "font:15px/1.5 -apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif}"
        . '.card{width:100%;max-width:440px;padding:40px 32px 32px;background:#fff;border:1px solid var(--line);'
        . 'border-radius:20px;box-shadow:0 4px 12px rgba(10,10,10,.06);text-align:center}'
        . '.logo{display:block;height:24px;width:auto;margin:0 auto 32px}'
        . '.status{display:flex;align-items:center;justify-content:center;width:56px;height:56px;margin:0 auto 16px;'
        . 'border-radius:999px;background:var(--success-tint);color:var(--success)}'
        . '.status--error{background:var(--destructive-tint);color:var(--destructive)}'
        . 'h1{margin:0 0 20px;color:var(--foreground);font-size:22px;line-height:1.3;font-weight:600}'
        . '.lines{margin:0 0 24px;padding:0;list-style:none;text-align:left}'
        . '.lines li{display:flex;gap:10px;padding:12px 14px;border-radius:14px;background:var(--muted)}'
        . '.lines li+li{margin-top:8px}'
        . '.lines svg{flex:none;margin-top:3px}'
        . '.line--ok svg{color:var(--success)}'
        . '.line--problem svg{color:var(--muted-foreground)}'
        . '.button{display:inline-block;padding:12px 24px;border-radius:999px;background:var(--primary);'
        . 'color:var(--primary-foreground);'
        . 'font-weight:600;text-decoration:none}'
        . '.button:hover,.button:focus-visible{background:var(--primary-hover)}'
        . '.button:focus-visible{outline:2px solid var(--primary);outline-offset:2px}'
        . '.hint{margin:16px 0 0;color:var(--muted-foreground);font-size:13px}';
}
