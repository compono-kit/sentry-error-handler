<?php declare(strict_types=1);

namespace ComponoKit\ErrorHandlers\Sentry\Tests\Unit;

use ComponoKit\ErrorHandlers\Sentry\SentryErrorHandler;
use ComponoKit\SentryClient\Interfaces\InformsSentry;
use ComponoKit\SentryClient\Models\Context;
use ComponoKit\SentryClient\Models\Tags;
use ComponoKit\SentryClient\Models\Types\Message;
use ComponoKit\SentryClient\Models\Types\Severity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SentryErrorHandlerTest extends TestCase
{
	public function testInstall(): void
	{
		$client = $this->buildSentryClient();
		$client->expects($this->once())->method('install');

		(new SentryErrorHandler($client))->install();
	}

	public function testException(): void
	{
		$client = $this->buildSentryClient();
		$throwable = new \RuntimeException('Something went wrong');

		$client->expects($this->once())
			->method('captureMessage')
			->with(
				$this->callback(fn(Message $message) => $message->toString() === \RuntimeException::class . ' occurred'),
				$this->callback(fn(Severity $severity) => $severity->toString() === 'fatal'),
				$this->isInstanceOf(Context::class)
			);

		(new SentryErrorHandler($client))->exception($throwable);
	}

	public function testExceptionWithCustomMessage(): void
	{
		$client = $this->buildSentryClient();
		$throwable = new \RuntimeException('Something went wrong');

		$client->expects($this->once())
			->method('captureMessage')
			->with(
				$this->callback(fn(Message $message) => $message->toString() === 'Custom error message'),
				$this->anything(),
				$this->anything()
			);

		(new SentryErrorHandler($client))->exception($throwable, 'Custom error message');
	}

	public function testExceptionAddsThrowableDetailsToContext(): void
	{
		$client = $this->buildSentryClient();
		$throwable = new \RuntimeException('Something went wrong');

		$capturedContext = null;
		$client->method('captureMessage')
			->with(
				$this->anything(),
				$this->anything(),
				$this->callback(function (Context $context) use (&$capturedContext) {
					$capturedContext = $context;
					return true;
				})
			);

		(new SentryErrorHandler($client))->exception($throwable);

		$details = $capturedContext['Details'];
		$byName  = array_combine(
			array_map(fn($entry) => $entry->getName(), $details),
			array_map(fn($entry) => $entry->getValue(), $details)
		);

		$this->assertSame($throwable->getMessage(), $byName['message']);
		$this->assertSame($throwable->getFile(), $byName['file']);
		$this->assertSame((string)$throwable->getLine(), $byName['line']);
		$this->assertSame($throwable->getTraceAsString(), $byName['trace']);
	}

	public function testWarning(): void
	{
		$client = $this->buildSentryClient();

		$client->expects($this->once())
			->method('captureMessage')
			->with(
				$this->callback(fn(Message $message) => $message->toString() === 'Test warning'),
				$this->callback(fn(Severity $severity) => $severity->toString() === 'warning'),
				$this->isNull()
			);

		(new SentryErrorHandler($client))->warning('Test warning');
	}

	public function testError(): void
	{
		$client = $this->buildSentryClient();

		$client->expects($this->once())
			->method('captureMessage')
			->with(
				$this->callback(fn(Message $message) => $message->toString() === 'Test error'),
				$this->callback(fn(Severity $severity) => $severity->toString() === 'error'),
				$this->isNull()
			);

		(new SentryErrorHandler($client))->error('Test error');
	}

	public function testAlert(): void
	{
		$client = $this->buildSentryClient();

		$client->expects($this->once())
			->method('captureMessage')
			->with(
				$this->callback(fn(Message $message) => $message->toString() === 'Test alert'),
				$this->callback(fn(Severity $severity) => $severity->toString() === 'fatal'),
				$this->isNull()
			);

		(new SentryErrorHandler($client))->alert('Test alert');
	}

	public function testCritical(): void
	{
		$client = $this->buildSentryClient();

		$client->expects($this->once())
			->method('captureMessage')
			->with(
				$this->callback(fn(Message $message) => $message->toString() === 'Test critical'),
				$this->callback(fn(Severity $severity) => $severity->toString() === 'fatal'),
				$this->isNull()
			);

		(new SentryErrorHandler($client))->critical('Test critical');
	}

	public function testEmergency(): void
	{
		$client = $this->buildSentryClient();

		$client->expects($this->once())
			->method('captureMessage')
			->with(
				$this->callback(fn(Message $message) => $message->toString() === 'Test emergency'),
				$this->callback(fn(Severity $severity) => $severity->toString() === 'fatal'),
				$this->isNull()
			);

		(new SentryErrorHandler($client))->emergency('Test emergency');
	}

	public function testWarningWithContextPassesContextToClient(): void
	{
		$client = $this->buildSentryClient();

		$client->expects($this->once())
			->method('captureMessage')
			->with(
				$this->anything(),
				$this->anything(),
				$this->isInstanceOf(Context::class)
			);

		(new SentryErrorHandler($client))->warning('Test warning', ['key' => 'value']);
	}

	public function testWarningWithoutContextPassesNullToClient(): void
	{
		$client = $this->buildSentryClient();

		$client->expects($this->once())
			->method('captureMessage')
			->with(
				$this->anything(),
				$this->anything(),
				$this->isNull()
			);

		(new SentryErrorHandler($client))->warning('Test warning');
	}

	/**
	 * @dataProvider eventTagsProvider
	 */
	public function testTagsArePassedAsEventTagsInsteadOfGlobalTags(array $tags, ?array $expectedEventTags): void
	{
		$client = $this->buildSentryClient();

		$client->expects($this->never())->method('useTags');
		$client->expects($this->once())
			->method('captureMessage')
			->with(
				$this->anything(),
				$this->anything(),
				$this->anything(),
				$this->isNull(),
				$this->callback(fn(?Tags $eventTags) => $eventTags?->toKeyValueArray() === $expectedEventTags)
			);

		(new SentryErrorHandler($client))->warning('Test warning', [], $tags);
	}

	public static function eventTagsProvider(): array
	{
		return [
			'without tags no event tags are passed'      => [
				[],
				null,
			],
			'string tags are passed unchanged'           => [
				['environment' => 'production'],
				['environment' => 'production'],
			],
			'non string tag values are converted'        => [
				['retry' => 3, 'amount' => 12.5, 'paid' => true, 'shipped' => false, 'customer' => null],
				['retry' => '3', 'amount' => '12.5', 'paid' => 'true', 'shipped' => 'false', 'customer' => 'null'],
			],
			'array tag value is converted to json'       => [
				['ids' => [1, 2]],
				['ids' => '[1,2]'],
			],
		];
	}

	public function testContextGroupingWithNamedGroup(): void
	{
		$client = $this->buildSentryClient();

		$capturedContext = null;
		$client->method('captureMessage')
			->with(
				$this->anything(),
				$this->anything(),
				$this->callback(function (Context $context) use (&$capturedContext) {
					$capturedContext = $context;
					return true;
				})
			);

		(new SentryErrorHandler($client))->warning('Test', ['RequestData' => ['url' => 'https://example.com', 'method' => 'GET']]);

		$this->assertTrue(isset($capturedContext['RequestData']));
		$byName = array_combine(
			array_map(fn($entry) => $entry->getName(), $capturedContext['RequestData']),
			array_map(fn($entry) => $entry->getValue(), $capturedContext['RequestData'])
		);
		$this->assertSame('https://example.com', $byName['url']);
		$this->assertSame('GET', $byName['method']);
	}

	public function testContextExceedingTwoLevelsThrowsLogicException(): void
	{
		$this->expectException(\LogicException::class);

		(new SentryErrorHandler($this->buildSentryClient()))->warning('Test', ['GroupName' => ['key' => ['too' => 'deep']]]);
	}

	private function buildSentryClient(): InformsSentry|MockObject
	{
		return $this->createMock(InformsSentry::class);
	}
}
