<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Url;
use Monei\MoneiPayment\Block\System\Config\Form\Field\ConnectButton;
use Monei\MoneiPayment\Controller\Adminhtml\Connect\Disconnect;
use Monei\MoneiPayment\Controller\Adminhtml\Connect\Start;
use Monei\MoneiPayment\Model\Config\Backend\ApiKey;
use Monei\MoneiPayment\Service\Connect\CallbackUrl;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Declarations of "Connect with MONEI" that the framework acts on, which a mock test can only pin.
 *
 * Every test names the bug it catches.
 */
class ConnectWiringTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    /**
     * Bug: the start or disconnect route runs without the admin check: without Magento\Backend\App\Action the
     * ADMIN_RESOURCE has no effect, another resource lets a lesser admin in, and a GET route takes a forged link
     * (the framework checks the form key of POST requests only).
     *
     * @dataProvider adminRoutes
     */
    public function testTheAdminRoutesAreAdminPostActionsOnTheConfigResource(string $class): void
    {
        $route = new ReflectionClass($class);

        $this->assertSame(
            [true, 'Monei_MoneiPayment::config', true, false],
            [
                $route->isSubclassOf(Action::class),
                $route->getConstant('ADMIN_RESOURCE'),
                $route->implementsInterface(HttpPostActionInterface::class),
                $route->implementsInterface(HttpGetActionInterface::class),
            ]
        );
    }

    public function adminRoutes(): array
    {
        return ['start' => [Start::class], 'disconnect' => [Disconnect::class]];
    }

    /**
     * Bug: the callback URL comes from the backend URL model, which adds the admin secret key/… to a URL the broker
     * stores and shows; or the button and the start route build it apart, so the button shows for a URI the start
     * does not send.
     */
    public function testTheButtonAndTheStartRouteBuildTheCallbackWithTheFrontendUrlModel(): void
    {
        $types = function (string $class): array {
            return array_map(function ($parameter) {
                return (string) $parameter->getType();
            }, (new ReflectionClass($class))->getConstructor()->getParameters());
        };

        $this->assertSame(Url::class, $types(CallbackUrl::class)[0]);
        $this->assertContains(CallbackUrl::class, $types(ConnectButton::class));
        $this->assertContains(CallbackUrl::class, $types(Start::class));
    }

    /**
     * Bug: a key field saves without the ApiKey model, so a manual key change keeps the signing secret of the
     * replaced key.
     */
    public function testBothKeyFieldsUseTheApiKeyBackendModel(): void
    {
        $xml = simplexml_load_file(self::ROOT . '/etc/adminhtml/system.xml');
        $models = [];
        foreach (['test_api_key', 'production_api_key'] as $id) {
            $models[$id] = (string) $xml->xpath("//group[@id='monei']/field[@id='$id']/backend_model")[0];
        }

        $this->assertSame(['test_api_key' => ApiKey::class, 'production_api_key' => ApiKey::class], $models);
    }

    /**
     * Bug: a request with a scoped (mk_) key sends MONEI-Account-ID, which MONEI refuses. setAccountId sticks on the
     * cached client, so one call breaks every later call of the store.
     */
    public function testNoModuleCodeCallsSetAccountId(): void
    {
        $callers = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::ROOT, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = substr($file->getPathname(), strlen(self::ROOT) + 1);
            if ($file->getExtension() !== 'php' || preg_match('#^(vendor|node_modules|Test|tests)/#', $path)) {
                continue;
            }
            if (strpos((string) file_get_contents($file->getPathname()), 'setAccountId(') !== false) {
                $callers[] = $path;
            }
        }

        $this->assertSame([], $callers);
    }
}
