<?php

/**
 * Test case for AccountId backend model.
 *
 * @category  Monei
 * @package   Monei\MoneiPayment
 * @author    Monei <info@monei.com>
 * @copyright 2023 Monei
 * @license   https://opensource.org/license/mit/ MIT License
 * @link      https://monei.com/
 */

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Monei\MoneiPayment\Model\Config\Backend\AccountId;
use Monei\MoneiPayment\Registry\AccountId as RegistryAccountId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test case for AccountId backend model.
 */
class AccountIdTest extends TestCase
{
    /**
     * @var AccountId
     */
    private $accountId;

    /**
     * @var Context|MockObject
     */
    private $contextMock;

    /**
     * @var Registry|MockObject
     */
    private $registryMock;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $configMock;

    /**
     * @var TypeListInterface|MockObject
     */
    private $cacheTypeListMock;

    /**
     * @var RegistryAccountId|MockObject
     */
    private $registryAccountIdMock;

    /**
     * @var WriterInterface|MockObject
     */
    private $configWriterMock;

    /**
     * @var AbstractResource|MockObject
     */
    private $resourceMock;

    /**
     * @var AbstractDb|MockObject
     */
    private $resourceCollectionMock;

    /**
     * Set up test environment
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $eventManagerMock = $this->createMock(\Magento\Framework\Event\ManagerInterface::class);
        $this->contextMock->method('getEventDispatcher')->willReturn($eventManagerMock);

        $this->registryMock = $this->createMock(Registry::class);
        $this->configMock = $this->createMock(ScopeConfigInterface::class);
        $this->cacheTypeListMock = $this->createMock(TypeListInterface::class);
        $this->registryAccountIdMock = $this->createMock(RegistryAccountId::class);
        $this->configWriterMock = $this->createMock(WriterInterface::class);
        $this->resourceMock = $this->createMock(AbstractResource::class);
        $this->resourceCollectionMock = $this
            ->getMockBuilder(AbstractDb::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->accountId = new AccountId(
            $this->contextMock,
            $this->registryMock,
            $this->configMock,
            $this->cacheTypeListMock,
            $this->registryAccountIdMock,
            $this->configWriterMock,
            $this->resourceMock,
            $this->resourceCollectionMock
        );
    }

    /**
     * Test beforeSave method with a valid account ID
     *
     * @return void
     */
    public function testBeforeSaveWithValidAccountId(): void
    {
        $accountId = 'acc_123456';
        $scope = 'websites';
        $scopeId = 1;

        // Configure the test data
        $this->accountId->setValue($accountId);
        $this->accountId->setScope($scope);
        $this->accountId->setScopeId($scopeId);

        // Set expectations for registry storage
        $this
            ->registryAccountIdMock
            ->expects($this->once())
            ->method('set')
            ->with($accountId);

        // Set expectations for config writer
        $this
            ->configWriterMock
            ->expects($this->once())
            ->method('save')
            ->with(
                'monei/account_id_storage',
                $accountId,
                $scope,
                $scopeId
            );

        // Call the method under test
        $result = $this->accountId->beforeSave();

        // Assert the result is the same object (fluent interface)
        $this->assertInstanceOf(AccountId::class, $result);
    }

    /**
     * Test beforeSave method with an empty account ID
     *
     * @return void
     */
    public function testBeforeSaveWithEmptyAccountId(): void
    {
        // Configure the test data with empty account ID
        $this->accountId->setValue('');

        // Registry and config writer should not be called
        $this
            ->registryAccountIdMock
            ->expects($this->never())
            ->method('set');
        $this
            ->configWriterMock
            ->expects($this->never())
            ->method('save');

        // Call the method under test
        $result = $this->accountId->beforeSave();

        // Assert the result is the same object
        $this->assertInstanceOf(AccountId::class, $result);
    }

    /**
     * Test beforeSave method with null account ID
     *
     * @return void
     */
    public function testBeforeSaveWithNullAccountId(): void
    {
        // Configure the test data with null account ID
        $this->accountId->setValue(null);

        // Registry and config writer should not be called
        $this
            ->registryAccountIdMock
            ->expects($this->never())
            ->method('set');
        $this
            ->configWriterMock
            ->expects($this->never())
            ->method('save');

        // Call the method under test
        $result = $this->accountId->beforeSave();

        // Assert the result is the same object
        $this->assertInstanceOf(AccountId::class, $result);
    }

    /**
     * Test getRegistry method
     *
     * @return void
     */
    public function testGetRegistry(): void
    {
        $reflectionMethod = new \ReflectionMethod(AccountId::class, '_getRegistry');
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invoke($this->accountId);

        $this->assertSame($this->registryMock, $result);
    }
}
