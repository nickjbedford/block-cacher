<?php
	/** @noinspection PhpUnused */
	
	namespace BlockCacher;
	
	use Closure;
	use Exception;
	
	/**
	 * @template TData = mixed
	 * Represents a cache block (file caching context) with a name and
	 * a cacher to use in storing and retrieving content.
	 */
	class Block
	{
		private BlockCacher $cacher;
		private string $name;
		private int $lifetime;
		
		public function __construct(
			BlockCacher $cacher,
			string $name = 'Block.cache',
			int $lifetime = BlockCacher::DefaultLifetime)
		{
			$this->cacher = $cacher;
			$this->lifetime = $lifetime;
			$this->name = $name;
		}
		
		/**
		 * Gets the BlockCacher responsible for caching for this block.
		 */
		public function getCacher(): BlockCacher
		{
			return $this->cacher;
		}
		
		/**
		 * Gets the name of the cache block.
		 */
		public function getName(): string
		{
			return $this->name;
		}
		
		/**
		 * Gets the lifetime of the cache.
		 */
		public function getLifetime(): int
		{
			return $this->lifetime;
		}
		
		/**
		 * Sets the lifetime for the cache block.
		 * @param int $lifetime The lifetime of the cache in seconds.
		 */
		public function lifetime(int $lifetime = BlockCacher::DefaultLifetime): self
		{
			$this->lifetime = $lifetime;
			return $this;
		}
		
		/**
		 * Sets the cache block name from an item ID with a prefix and suffix component, joined by a separator.
		 * For example: "prefix-id-suffix.cache"
		 * @param string $itemType The name of the item being cache.
		 * @param string $itemId The ID of the item being cached.
		 * @param string $blockType The type of block being cached for the item.
		 * @param int|null $version The version of the cached data being generated.
		 * @param string $extension The file extension for the cached data.
		 * @param string $separator The separator used to join the components of the name.
		 * @return self
		 */
		public function namedForItem(string $itemType,
		                             string $itemId,
		                             string $blockType = '',
		                             ?int $version = 1,
		                             string $extension = '.cache',
		                             string $separator = '-'): self
		{
			return $this->namedFromParts(array_filter([ $itemType, $itemId, $blockType, $version ? "V$version" : null ], function(string $part)
			{
				return !empty($part);
			}), $separator, $extension);
		}
		
		/**
		 * Sets the cache block name from an array of parts joined by a separator.
		 * @param array $nameParts The components of the name to be joined.
		 * @param string $separator The separator used to join the components of the name.
		 * @param string $extension The file extension for the cached data.
		 * @return $this
		 */
		public function namedFromParts(array $nameParts, string $separator = '-', string $extension = '.cache'): self
		{
			$this->name = join($separator, $nameParts) . $extension;
			return $this;
		}
		
		/**
		 * Determines whether the cached data exists and is valid.
		 */
		public function exists(): bool
		{
			return $this->cacher->exists($this->name, $this->lifetime);
		}
		
		/**
		 * Generates and caches data using a generator function only if the data is not yet cached.
		 * @param callable|Closure $generator A callback that will generate the data if the cached data does not exist.
		 * @return TData|null The generated data.
		 */
		public function generate(callable|Closure $generator): mixed
		{
			return $this->cacher->generate($this->name, $generator, $this->lifetime);
		}
		
		/**
		 * Generates and caches text using a generator function only if the data is not yet cached.
		 * @param callable|Closure $generator A callback that will return the text if the cached
		 * text does not exist.
		 * @return string|null
		 */
		public function generateText(callable|Closure $generator): ?string
		{
			return $this->cacher->generateText($this->name, $generator, $this->lifetime);
		}
		
		/**
		 * Generates and caches HTML output using a generator function only if the HTML is not yet cached.
		 * @param callable|Closure $outputGenerator A callback that will print the HTML to the output buffer
		 * if the cached HTML does not exist.
		 * @param bool $echo Whether to echo the HTML directly to the output buffer afterwards.
		 * @return string
		 * @throws Exception
		 */
		public function html(callable|Closure $outputGenerator, bool $echo = false): string
		{
			return $this->cacher->html($this->name, $outputGenerator, $this->lifetime, true, $echo);
		}
		
		/**
		 * Gets a cached value that is still valid. If the cached value does not exist or has expired,
		 * null is returned.
		 * @return TData|null
		 */
		public function get(): mixed
		{
			return $this->cacher->get($this->name, $this->lifetime);
		}
		
		/**
		 * Gets a cached value that is still valid. If the cached value does not exist or has expired,
		 * null is returned.
		 * @return string|null
		 */
		public function getText(): ?string
		{
			return $this->cacher->getText($this->name, $this->lifetime);
		}
		
		/**
		 * Stores a value in the file cache. The value must be serializable
		 * using the native serialize() function.
		 * @param TData $value
		 * @return bool
		 */
		public function store(mixed $value): bool
		{
			return $this->cacher->store($this->name, $value);
		}
		
		/**
		 * Stores a string value in the file cache. This does not serialize the value
		 * but will coerce its type to string.
		 * @param TData|string $value
		 * @return bool
		 */
		public function storeText(mixed $value): bool
		{
			return $this->cacher->storeText($this->name, $value);
		}
		
		/**
		 * Starts a caching buffer, otherwise storing the existing cached contents until end() is called.
		 * @return bool
		 */
		public function start(): bool
		{
			return $this->cacher->start($this->name, $this->lifetime);
		}
		
		/**
		 * Stores the output buffer into the cache file and optionally echoes the content.
		 * @param bool $echo Set to true to echo the contents of the buffer automatically.
		 * @return BlockCacherOutputBuffer
		 * @throws Exception
		 */
		public function end(bool $echo = true): BlockCacherOutputBuffer
		{
			return $this->cacher->end($echo);
		}
		
		/**
		 * Clears the cache block and returns true if cached data was cleared.
		 * @return bool
		 */
		public function clear(): bool
		{
			return $this->cacher->clear($this->name)->count() > 0;
		}
	}
