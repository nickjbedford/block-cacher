<?php
	namespace BlockCacher;
	
	/**
	 * Represents a block cache output buffer entry.
	 */
	class BlockCacherOutputBuffer
	{
		/** @var string $key The name of the cache file. */
		public string $key;
		
		/** @var string|null $contents The contents of the block cacher output buffer, or null. */
		public ?string $contents;
		
		/** @var bool $prefixed Whether to add the cacher's prefix to this key. */
		public bool $prefixed;
		
		/** @var bool $hit Whether the cache was hit (contents is not null). */
		public bool $hit = false;
		
		/**
		 * Initialises a new instance of the output buffer.
		 * @param string $key The path of the cache file.
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @param string|null $contents The contents, if valid, of the cached string.
		 */
		public function __construct(string $key, bool $prefixed = true, ?string $contents = null)
		{
			$this->key = $key;
			$this->contents = $contents;
			$this->prefixed = $prefixed;
			$this->hit = $contents !== null;
		}
	}
