<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\Model\ActionValidator\RemoveAction;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Model\Config\Backend\AccountId;
use Monei\MoneiPayment\Model\Config\Backend\ApiKey;
use Monei\MoneiPayment\Model\Config\Backend\CredentialValue;
use Monei\MoneiPayment\Registry\AccountId as RegistryAccountId;
use PHPUnit\Framework\TestCase;

/**
 * The backend models of the fields that Connect and Disconnect also change: the API keys (ApiKey), the account IDs
 * (AccountId) and the mode (CredentialValue).
 *
 * Every test names the bug it catches.
 */
class ApiKeyTest extends TestCase
{
    private const TEST_KEY = 'payment/monei/test_api_key';
    private const LIVE_KEY = 'payment/monei/production_api_key';

    /** @var array[] Arguments of each config writer call, with the method name first */
    private array $writes = [];

    /** @var string[] Texts of the warnings added */
    private array $warnings = [];

    /**
     * A field at website 2, posted with $posted, with $inUse the config values in use there (by path)
     */
    private function model(string $path, string $posted, array $inUse, ?array $loaded): CredentialValue
    {
        $context = $this->createMock(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createMock(ManagerInterface::class));
        $validator = $this->createMock(RemoveAction::class);
        $validator->method('isAllowed')->willReturn(true);
        $context->method('getActionValidator')->willReturn($validator);
        $config = $this->createMock(ScopeConfigInterface::class);
        $config->method('getValue')->willReturnCallback(function ($configPath) use ($inUse) {
            return $inUse[$configPath] ?? null;
        });
        $writer = $this->createMock(WriterInterface::class);
        foreach (['save', 'delete'] as $method) {
            $writer->method($method)->willReturnCallback(function (...$args) use ($method) {
                $this->writes[] = array_merge([$method], $args);
            });
        }
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->with(CredentialValue::LOADED_PARAM)->willReturn($loaded);
        $messages = $this->createMock(MessageManager::class);
        $messages->method('createMessage')->with(MessageInterface::TYPE_WARNING)->willReturnCallback(function () {
            $message = $this->createMock(MessageInterface::class);
            $message->method('setText')->willReturnCallback(function ($text) use ($message) {
                $this->warnings[] = $text;
                return $message;
            });
            return $message;
        });
        $parents = [$context, $this->createMock(Registry::class), $config, $this->createMock(TypeListInterface::class),
            $request, $messages];
        $resource = $this->createMock(AbstractDb::class);
        if ($path === 'payment/monei/mode') {
            $model = new CredentialValue(...array_merge($parents, [$resource]));
        } elseif (strpos($path, 'account_id') !== false) {
            $model = new AccountId(...array_merge($parents, [$this->createMock(RegistryAccountId::class), $writer, $resource]));
        } else {
            $model = new ApiKey(...array_merge($parents, [$writer, $resource]));
        }
        $model->setPath($path);
        $model->setScope('websites');
        $model->setScopeId(2);
        $model->setId(31);
        $model->setValue($posted);

        return $model;
    }

    /**
     * Bug: after a manual key change, v2 callbacks still verify with the secret of the replaced key; the secret is
     * deleted (the scope then inherits a parent's secret) or cleared at another scope; or a save that leaves the
     * key alone, or posts it empty (the field never shows a scoped key), drops the secret of a connected mode.
     *
     * @dataProvider savedKeys
     */
    public function testAChangedKeyClearsTheSecretOfItsModeAtItsScope(
        string $inUse,
        string $posted,
        string $saved,
        array $expectedWrites
    ): void {
        $model = $this->model(self::TEST_KEY, $posted, [self::TEST_KEY => $inUse], null);

        $model->beforeSave();
        $model->afterSave();

        $this->assertSame([$saved, $expectedWrites], [$model->getValue(), $this->writes]);
    }

    public function savedKeys(): array
    {
        $cleared = [['save', 'payment/monei/test_signing_secret', '', 'websites', 2]];

        return [
            'changed' => ['pk_test_old', 'mk_test_new', 'mk_test_new', $cleared],
            'unchanged' => ['mk_test_new', 'mk_test_new', 'mk_test_new', []],
            'empty, connected' => ['mk_test_new', '', 'mk_test_new', []],
            'empty, manual' => ['pk_test_old', '', '', $cleared],
        ];
    }

    /**
     * Bug: "Use Default" on a key leaves the scope's own signing secret, which then pairs with the inherited key of
     * another connect; or a delete skipped as stale still deletes the secret.
     *
     * @dataProvider keyDeletes
     */
    public function testUseDefaultOnAKeyDeletesTheScopesSecret(string $loadedKey, ?int $deletedId, array $expectedWrites): void
    {
        $model = $this->model(self::TEST_KEY, '', [self::TEST_KEY => 'mk_test_new'], ['test' => CredentialValue::fingerprint($loadedKey)]);

        $model->beforeDelete();
        $model->afterDelete();

        $this->assertSame([$deletedId, $expectedWrites], [$model->getId(), $this->writes]);
    }

    public function keyDeletes(): array
    {
        return [
            'page loaded after the connect' => ['mk_test_new', 31, [['delete', 'payment/monei/test_signing_secret', 'websites', 2]]],
            'page loaded before the connect (inherited key)' => ['pk_test_inherited', null, []],
        ];
    }

    /**
     * Bug: a save of the settings page loaded before a Connect or a Disconnect (Connect opens in a new tab) writes
     * a mode's old key, account ID or the mode back, so the store loses the connect, a disconnected store gets its
     * old keys back, or checkout sends one account's ID with another account's key; or a change of one mode blocks
     * the other mode's fields; or the guard blocks a deliberate change. No save runs no afterSave, so the secret
     * stays.
     *
     * @dataProvider pageSaves
     */
    public function testOnlyAPageThatLoadedTheKeysInUseChangesTheirFields(
        string $path,
        array $inUse,
        ?array $loaded,
        bool $saved
    ): void {
        $model = $this->model($path, 'posted', $inUse, $loaded);

        $model->beforeSave();

        $this->assertSame($saved, $model->isSaveAllowed());
        if (!$saved) {
            // AccountId also copies the value to monei/account_id_storage.
            $this->assertSame([], $this->writes);
        }
    }

    public function pageSaves(): array
    {
        $beforeTestConnect = ['test' => CredentialValue::fingerprint('pk_test_old'), 'live' => CredentialValue::fingerprint('pk_live')];
        $afterTestConnect = [self::TEST_KEY => 'mk_test_new', self::LIVE_KEY => 'pk_live'];

        return [
            'test key, page loaded before a Test connect' => [self::TEST_KEY, $afterTestConnect, $beforeTestConnect, false],
            'test account id, page loaded before a Test connect' => ['payment/monei/test_account_id', $afterTestConnect, $beforeTestConnect, false],
            'mode, page loaded before a Test connect' => ['payment/monei/mode', $afterTestConnect, $beforeTestConnect, false],
            'live account id, page loaded before a Test connect' => ['payment/monei/production_account_id', $afterTestConnect, $beforeTestConnect, true],
            'test key, page loaded before a Disconnect' => [self::TEST_KEY, [self::LIVE_KEY => 'pk_live'], $beforeTestConnect, false],
            'test key, page loaded after the connect' => [self::TEST_KEY, $afterTestConnect, ['test' => CredentialValue::fingerprint('mk_test_new')], true],
            'no page (config:set)' => [self::TEST_KEY, $afterTestConnect, null, true],
        ];
    }

    /**
     * Bug: a skipped field is dropped silently, so the merchant thinks the old keys were saved.
     */
    public function testASkippedFieldWarnsToReloadThePage(): void
    {
        $model = $this->model(self::TEST_KEY, 'pk_test_old', [self::TEST_KEY => 'mk_test_new'], ['test' => CredentialValue::fingerprint('pk_test_old')]);

        $model->beforeSave();

        $this->assertSame(
            ['The MONEI keys changed in another tab, so the key fields were not saved. Reload this page.'],
            $this->warnings
        );
    }
}
