<?php

declare(strict_types=1);

namespace Monei\MoneiPayment\Test\Unit\Controller\Payment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Monei\MoneiPayment\Api\PaymentProcessingResultInterface;
use Monei\MoneiPayment\Api\PaymentProcessorInterface;
use Monei\MoneiPayment\Controller\Payment\Callback;
use Monei\MoneiPayment\Model\Data\PaymentDTOFactory;
use Monei\MoneiPayment\Model\Service\StatusCodeHandler;
use Monei\MoneiPayment\Service\Api\ApiExceptionHandler;
use Monei\MoneiPayment\Service\Api\GetPayment;
use Monei\MoneiPayment\Service\Api\MoneiApiClient;
use Monei\MoneiPayment\Service\Logger;
use Monei\MoneiPayment\Test\Unit\Service\Connect\VerifierTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * The payment callback: it processes only the payment that CallbackVerifier returns, and answers its status.
 *
 * Every test names the bug it catches.
 */
class CallbackTest extends VerifierTestCase
{
    /** @var PaymentProcessorInterface|MockObject */
    private $paymentProcessor;

    /** @var int[] HTTP codes the controller set */
    private array $codes = [];

    private function controller(string $body, ?string $signature): Callback
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getContent')->willReturn($body);
        $request->method('getHeader')->with('MONEI-Signature')->willReturn($signature ?? false);

        $json = $this->createMock(Json::class);
        $json->method('setHttpResponseCode')->willReturnCallback(function (int $code) use ($json) {
            $this->codes[] = $code;
            return $json;
        });
        $json->method('setData')->willReturnSelf();
        $jsonFactory = $this->createMock(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($json);

        $this->paymentProcessor = $this->createMock(PaymentProcessorInterface::class);

        return new Callback(
            $this->createMock(Logger::class),
            $jsonFactory,
            $this->paymentProcessor,
            $this->verifier(),
            $this->createMock(OrderRepositoryInterface::class),
            $request,
            $this->createMock(HttpResponse::class),
            new PaymentDTOFactory(new StatusCodeHandler()),
            $this->createMock(ScopeConfigInterface::class)
        );
    }

    /**
     * Bug: the handler processes the request body, not the payment MONEI answers, so a forged body settles the
     * order; or the fetched payment never reaches the processor; or the processor looks the increment id up again
     * across all stores instead of changing the order the verifier checked; or processing it (the order email, the
     * vault token) fetches it again through GetPayment.
     */
    public function testAFetchedSucceededPaymentReachesTheProcessor(): void
    {
        $controller = $this->controller(
            self::body(self::paymentFields('SUCCEEDED', ['statusMessage' => 'from the body'])),
            null
        );
        $result = $this->createMock(PaymentProcessingResultInterface::class);
        $result->method('isSuccess')->willReturn(true);
        $this->paymentProcessor->expects($this->once())->method('process')
            ->willReturnCallback(function (string $orderId, string $paymentId, array $payment, ?int $entityId) use (
                $result
            ) {
                $this->assertSame(
                    ['000000101', self::ID, 'SUCCEEDED', 'Transaction approved', 7],
                    [$orderId, $paymentId, $payment['status'], $payment['statusMessage'], $entityId]
                );
                $apiClient = $this->createMock(MoneiApiClient::class);
                $apiClient->expects($this->never())->method('getMoneiSdk');
                $getPayment = new GetPayment(
                    $this->createMock(Logger::class),
                    $this->createMock(ApiExceptionHandler::class),
                    $apiClient,
                    $this->fetchedPayments
                );
                $this->assertSame('SUCCEEDED', $getPayment->execute($paymentId)->getStatus());
                return $result;
            });

        $controller->execute();

        $this->assertSame([], $this->codes);
        $this->assertCount(1, $this->fetches);
    }

    /**
     * Bug: a payment that must not change the order is refused in validateForCsrf, which can only answer 403, so
     * MONEI retries for days; or it reaches the processor (an unsigned replay of a settled payment reopens the
     * order the merchant canceled).
     *
     * @dataProvider paymentsThatMustNotChangeTheOrder
     */
    public function testAPaymentThatMustNotChangeTheOrderAnswers200AndIsNotProcessed(array $remote, array $order): void
    {
        $this->remote = $remote;
        $this->orders['000000101'] = $order + $this->orders['000000101'];
        $controller = $this->controller(self::body(self::paymentFields('SUCCEEDED')), null);
        $this->paymentProcessor->expects($this->never())->method('process');

        $this->assertTrue($controller->validateForCsrf($this->createMock(HttpRequest::class)));
        $controller->execute();

        $this->assertSame([], $this->codes);
    }

    public function paymentsThatMustNotChangeTheOrder(): array
    {
        return [
            'forged SUCCEEDED, MONEI says PENDING' => [self::paymentFields('PENDING'), []],
            'stored SUCCEEDED, order canceled, MONEI says SUCCEEDED' => [
                self::paymentFields('SUCCEEDED'),
                ['state' => 'canceled', 'paymentId' => self::ID, 'storedStatus' => 'SUCCEEDED', 'paid' => true],
            ],
        ];
    }

    /**
     * Bug: an error while the order is processed answers 2xx, so MONEI never retries and the order stays open.
     */
    public function testAProcessingErrorAnswers500(): void
    {
        $controller = $this->controller(self::body($this->remote), null);
        $this->paymentProcessor->method('process')->willThrowException(new \RuntimeException('database gone'));

        $controller->execute();

        $this->assertSame([500], $this->codes);
    }

    /**
     * Bug: a failed fetch answers 200, so MONEI never retries and the order stays open.
     */
    public function testAFailedFetchAnswers503(): void
    {
        $this->fetchFails = true;
        $controller = $this->controller(self::body($this->remote), null);
        $this->paymentProcessor->expects($this->never())->method('process');

        $controller->execute();

        $this->assertSame([503], $this->codes);
    }
}
