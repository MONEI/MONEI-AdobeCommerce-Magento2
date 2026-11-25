<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ActionValidator\RemoveAction;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Model\Config\Backend\ApiKey;
use Monei\MoneiPayment\Model\Config\Backend\CredentialValue;
use PHPUnit\Framework\TestCase;

/**
 * The backend models of the fields that Connect and Disconnect also write: the API keys (ApiKey) and the mode
 * (CredentialValue).
 *
 * Every test names the bug it catches.
 */
class ApiKeyTest extends TestCase
{
    /** @var array[] Arguments of each config writer save */
    private array $saves = [];

    /**
     * A field at website 2, posted with $posted, while $inUse is the value in use there
     */
    private function model(string $inUse, string $posted, ?array $loaded, string $path = 'payment/monei/test_api_key')
    {
        $context = $this->createMock(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createMock(ManagerInterface::class));
        $validator = $this->createMock(RemoveAction::class);
        $validator->method('isAllowed')->willReturn(true);
        $context->method('getActionValidator')->willReturn($validator);
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturn($inUse);
        $writer = $this->createMock(WriterInterface::class);
        $writer->method('save')->willReturnCallback(function (...$args) {
            $this->saves[] = $args;
        });
        $writer->expects($this->never())->method('delete');
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with(CredentialValue::LOADED_PARAM)->willReturn($loaded);
        $parents = [$context, $this->createMock(Registry::class), $config, $this->createMock(TypeListInterface::class), $request];
        $resource = $this->createMock(AbstractDb::class);
        $model = $path === 'payment/monei/mode'
            ? new CredentialValue(...array_merge($parents, [$resource]))
            : new ApiKey(...array_merge($parents, [$writer, $resource]));
        $model->setPath($path);
        $model->setScope('websites');
        $model->setScopeId(2);
        $model->setId(31);
        $model->setValue($posted);

        return $model;
    }

    /**
     * Bug: after a manual key change, v2 callbacks still verify with the secret of the replaced key; the secret is
     * deleted (the scope then inherits a parent's secret) or cleared at another scope; or the other mode loses its
     * secret; or a save that leaves the key alone, or posts it masked or empty, drops the secret of a connected
     * mode, or writes the mask as the key.
     *
     * @dataProvider savedKeys
     */
    public function testAChangedKeyClearsTheSecretOfItsModeAtItsScope(
        string $inUse,
        string $posted,
        string $saved,
        array $expectedSaves
    ): void {
        $model = $this->model($inUse, $posted, null);

        $model->beforeSave();
        $model->afterSave();

        $this->assertSame([$saved, $expectedSaves], [$model->getValue(), $this->saves]);
    }

    public function savedKeys(): array
    {
        $cleared = [['payment/monei/test_signing_secret', '', 'websites', 2]];

        return [
            'changed' => ['pk_test_old', 'mk_test_new', 'mk_test_new', $cleared],
            'unchanged' => ['mk_test_new', 'mk_test_new', 'mk_test_new', []],
            'masked' => ['mk_test_new', '•••• _new', 'mk_test_new', []],
            'empty, connected' => ['mk_test_new', '', 'mk_test_new', []],
            'empty, manual' => ['pk_test_old', '', '', $cleared],
        ];
    }

    /**
     * Bug: the settings page shows the full scoped key, which then sits in the admin HTML; or a manual key the
     * merchant must read back is masked.
     *
     * @dataProvider loadedKeys
     */
    public function testTheFormShowsAScopedKeyMasked(string $stored, string $shown): void
    {
        $model = $this->model('', $stored, null);

        $model->afterLoad();

        $this->assertSame($shown, $model->getValue());
    }

    public function loadedKeys(): array
    {
        return [
            'scoped' => ['mk_test_0123456789abcdef', '•••• cdef'],
            'manual' => ['pk_test_manual', 'pk_test_manual'],
        ];
    }

    /**
     * Bug: a save of the settings page loaded before a Connect or a Disconnect (Connect opens in a new tab) writes
     * the old key or mode back, so the store loses the connect, or a disconnected store gets its old keys back, or
     * a test-only store returns to production mode; or the guard blocks a deliberate change. No save runs no
     * afterSave, so a refused save keeps the secret.
     *
     * @dataProvider pageSaves
     */
    public function testOnlyAPageThatLoadedTheValueInUseReplacesIt(
        string $path,
        string $inUse,
        string $posted,
        ?array $loaded,
        bool $saved
    ): void {
        $model = $this->model($inUse, $posted, $loaded, $path);

        $model->beforeSave();

        $this->assertSame($saved, $model->isSaveAllowed());
    }

    public function pageSaves(): array
    {
        $key = 'payment/monei/test_api_key';
        $mode = 'payment/monei/mode';

        return [
            'key, page loaded before a connect' => [$key, 'mk_test_new', 'pk_test_old', ['test' => CredentialValue::fingerprint('pk_test_old')], false],
            'key, page loaded before a disconnect' => [$key, '', 'pk_test_old', ['test' => CredentialValue::fingerprint('pk_test_old')], false],
            'mode, page loaded before a connect' => [$mode, '1', '2', ['mode' => CredentialValue::fingerprint('2')], false],
            'key, page loaded after the connect' => [$key, 'mk_test_new', 'pk_test_typed', ['test' => CredentialValue::fingerprint('mk_test_new')], true],
            'mode, page loaded after the connect' => [$mode, '1', '2', ['mode' => CredentialValue::fingerprint('1')], true],
            'no page (config:set)' => [$key, 'mk_test_new', 'pk_test_typed', null, true],
        ];
    }

    /**
     * Bug: a page loaded while the scope inherited its key posts "Use Default" checked; its save deletes the key
     * that a connect wrote at the scope since then.
     *
     * @dataProvider pageDeletes
     */
    public function testOnlyAPageThatLoadedTheValueInUseDeletesIt(string $loadedKey, ?int $deletedId): void
    {
        $model = $this->model('mk_test_new', '', ['test' => CredentialValue::fingerprint($loadedKey)]);

        $model->beforeDelete();

        $this->assertSame($deletedId, $model->getId());
    }

    public function pageDeletes(): array
    {
        return [
            'page loaded before the connect' => ['pk_test_inherited', null],
            'page loaded after the connect' => ['mk_test_new', 31],
        ];
    }
}
