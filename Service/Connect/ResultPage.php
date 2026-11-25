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
use Magento\Framework\View\Asset\Repository;

/**
 * The page of the Connect with MONEI callback, a whole HTML document, as in the WooCommerce and PrestaShop plugins.
 *
 * Colors come from the MONEI Dashboard theme (primary, success, destructive, foreground, muted). Nothing loads from
 * another host: the logo is the module's own file and the font is the system font.
 *
 * The route is public, and a custom admin path hides the admin: only a page whose single-use state was valid
 * links to it.
 */
class ResultPage
{
    public const LOGO = 'Monei_MoneiPayment::img/monei-logo.svg';

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
     * @var Repository
     */
    private Repository $assetRepository;

    /**
     * @var ResolverInterface
     */
    private ResolverInterface $localeResolver;

    /**
     * @param Escaper $escaper
     * @param ScopeConfigInterface $scopeConfig
     * @param FrontNameResolver $frontNameResolver
     * @param Repository $assetRepository
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        Escaper $escaper,
        ScopeConfigInterface $scopeConfig,
        FrontNameResolver $frontNameResolver,
        Repository $assetRepository,
        ResolverInterface $localeResolver
    ) {
        $this->escaper = $escaper;
        $this->scopeConfig = $scopeConfig;
        $this->frontNameResolver = $frontNameResolver;
        $this->assetRepository = $assetRepository;
        $this->localeResolver = $localeResolver;
    }

    /**
     * Render the page of a connect result
     *
     * @param array $outcome The result of ConnectService::complete()
     * @return string
     */
    public function render(array $outcome): string
    {
        $refused = ConnectService::isRefused($outcome);
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
        $next = $refused
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
            . '<img class="logo" src="' . $this->escaper->escapeUrl($this->logoUrl()) . '" alt="MONEI" width="110" height="24">'
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
     * Get the URL of the module's MONEI logo, or an empty string when the static file is unknown
     *
     * @return string
     */
    private function logoUrl(): string
    {
        try {
            return $this->assetRepository->getUrl(self::LOGO);
        } catch (\Exception $e) {
            return '';
        }
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
