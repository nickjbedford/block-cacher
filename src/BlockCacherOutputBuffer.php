<?php
	namespace BlockCacher;
	
	/**
	 * Represents a block cache output buffer entry.
	 */
	class BlockCacherOutputBuffer
	{
		/** @var string $key */
		public $key;
		
		/** @var string $contents */
		public $contents;
		
		/** @var bool $prefixed */
		public $prefixed;
		
		/** @var bool $hit */
		public $hit = false;
		
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
