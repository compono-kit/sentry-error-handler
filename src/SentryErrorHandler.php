<?php declare(strict_types=1);

namespace ComponoKit\ErrorHandlers\Sentry;

use ComponoKit\ErrorHandlers\Interfaces\HandlesErrors;
use ComponoKit\SentryClient\Interfaces\InformsSentry;
use ComponoKit\SentryClient\Models\Context;
use ComponoKit\SentryClient\Models\ContextEntry;
use ComponoKit\SentryClient\Models\ContextGroup;
use ComponoKit\SentryClient\Models\Tag;
use ComponoKit\SentryClient\Models\Tags;
use ComponoKit\SentryClient\Models\Types\Message;
use ComponoKit\SentryClient\Models\Types\Severity;
use ComponoKit\SentryClient\Models\Types\TagTitle;
use ComponoKit\SentryClient\Models\Types\TagValue;

class SentryErrorHandler implements HandlesErrors
{
	private InformsSentry $sentryClient;

	public function __construct( InformsSentry $sentryClient )
	{
		$this->sentryClient = $sentryClient;
	}

	public function install(): void
	{
		$this->sentryClient->install();
	}

	public function exception( \Throwable $throwable, string $message = '', array $context = [], array $tags = [] ): void
	{
		$errContext = [
			'message' => $throwable->getMessage(),
			'file'    => $throwable->getFile(),
			'line'    => $throwable->getLine(),
			'trace'   => $throwable->getTraceAsString(),
		];

		$this->captureMessage(
			Severity::fatal(),
			$message === '' ? get_class( $throwable ) . ' occurred' : $message,
			array_merge( $context, $errContext ),
			$tags
		);
	}

	public function warning( string $message, array $context = [], array $tags = [] ): void
	{
		$this->captureMessage( Severity::warning(), $message, $context, $tags );
	}

	public function error( string $message, array $context = [], array $tags = [] ): void
	{
		$this->captureMessage( Severity::error(), $message, $context, $tags );
	}

	public function alert( string $message, array $context = [], array $tags = [] ): void
	{
		$this->captureMessage( Severity::fatal(), $message, $context, $tags );
	}

	public function critical( string $message, array $context = [], array $tags = [] ): void
	{
		$this->captureMessage( Severity::fatal(), $message, $context, $tags );
	}

	public function emergency( string $message, array $context = [], array $tags = [] ): void
	{
		$this->captureMessage( Severity::fatal(), $message, $context, $tags );
	}

	private function captureMessage( Severity $severity, string $message, array $context = [], array $tags = [] ): void
	{
		$this->guardContextLevels( $context );

		if ( $tags )
		{
			$this->sentryClient->useTags( new Tags( iterator_to_array( $this->createTags( $tags ) ) ) );
		}

		$this->sentryClient->captureMessage(
			new Message( $message ),
			$severity,
			$context ? $this->createContext( $context ) : null
		);
	}

	/**
	 * @param array $tags
	 *
	 * @return \Iterator|Tag[]
	 */
	private function createTags( array $tags ): \Iterator
	{
		foreach ( $tags as $tagTitle => $tagValue )
		{
			yield new Tag(
				new TagTitle( $tagTitle ),
				new TagValue( $tagValue )
			);
		}
	}

	private function createContext( array $context ): Context
	{
		$contextGroups = [];
		$details       = [];

		foreach ( $context as $title => $value )
		{
			if ( is_array( $value ) )
			{
				if ( $title !== 'Details' )
				{
					$contextGroups[] = $this->createContextGroup( (string)$title, $value );
				}
				else
				{
					foreach ( $value as $detailsTitle => $detailsValue )
					{
						$details[ $detailsTitle ] = $detailsValue;
					}
				}
			}
			else
			{
				$details[ $title ] = $value;
			}
		}

		if ( $details )
		{
			$contextGroups[] = $this->createContextGroup( 'Details', $details );
		}

		return new Context( $contextGroups );
	}

	private function createContextGroup( string $title, array $entries ): ContextGroup
	{
		return new ContextGroup( $title, iterator_to_array( $this->createContextEntries( $entries ) ) );
	}

	/**
	 * @param array $entries
	 *
	 * @return \Iterator|ContextEntry[]
	 */
	private function createContextEntries( array $entries ): \Iterator
	{
		foreach ( $entries as $name => $value )
		{
			if ( is_array( $value ) )
			{
				throw new \LogicException( 'Too many context levels provided' );
			}

			yield new ContextEntry( (string)$name, (string)$value );
		}
	}

	private function guardContextLevels( array $context ): void
	{
		foreach ( $context as $value )
		{
			if ( !is_array( $value ) )
			{
				continue;
			}

			foreach ( $value as $subValue )
			{
				if ( is_array( $subValue ) )
				{
					throw new \LogicException( 'Max context level of 2 exceeded' );
				}
			}
		}
	}
}
