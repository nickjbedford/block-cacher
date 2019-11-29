<?php
	
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
		 * @param string $contents The contents, if valid, of the cached string.
		 */
		public function __construct($key, $prefixed = true, $contents = null)
		{
			$this->key = $key;
			$this->contents = $contents;
			$this->prefixed = $prefixed;
			$this->hit = $contents !== null;
		}
	}
	
	/**
	 * Represents the results of a block cacher clear procedure.
	 */
	class BlockCacherClearResults
	{
		/** @var string[] $allFiles */
		public $allFiles;
		
		/** @var string[] $clearedFiles */
		public $clearedFiles;
		
		/**
		 * Initialises a new instance of the clear results.
		 * @param string[] $allFiles The list of cache files to be cleared.
		 * @param string[] $clearedFiles The list of cache files that were successfully cleared.
		 */
		public function __construct($allFiles, $clearedFiles)
		{
			$this->allFiles = $allFiles;
			$this->clearedFiles = $clearedFiles;
		}
		
		/**
		 * Gets the number of cache files successfully cleared.
		 * @return int
		 */
		public function count()
		{
			return count($this->clearedFiles);
		}
		
		/**
		 * Gets the number of cache files to be cleared.
		 * @return int
		 */
		public function total()
		{
			return count($this->allFiles);
		}
	}
	
	/**
	 * Represents the file-based data/text block cacher.
	 */
	class BlockCacher
	{
		/** @var int Specifies the default lifetime for cache files of one day. */
		const DefaultLifetime = 86400;
		
		/** @var BlockCacher $default */
		private static $default;
		
		/** @var BlockCacherOutputBuffer[] $buffers */
		private $buffers = array();
		
		/** @var string $directory */
		private $directory;
		
		/** @var string $prefix */
		private $prefix = '';
		
		/** @var bool $forceCached */
		private $forceCached = false;
		
		/** @var bool $enabled */
		private $enabled = true;
		
		/** @var string[] $protectedPatterns */
		private $protectedPatterns = array();
		
		/**
		 * Initialises a new instance of the block cacher.
		 * @param string $directory The directory to store all cache files in.
		 * @param string $filePrefix Optional. The prefix to add to cache filenames (i.e. localisation, versions).
		 * @param boolean $automaticallyEnsureStorageDirectoryExists Set to true to automatically ensure the storage directory exists.
		 * @throws
		 */
		public function __construct($directory = __DIR__, $filePrefix = '', $automaticallyEnsureStorageDirectoryExists = true)
		{
			$this->directory = rtrim($directory, '/\\') . '/';
			$this->prefix = $filePrefix;
			if ($automaticallyEnsureStorageDirectoryExists)
				$this->ensureStorageDirectoryExists();
		}
		
		/**
		 * Ensures the storage directory exists. An exception will be thrown if it cannot be created.
		 * @throws
		 */
		public function ensureStorageDirectoryExists()
		{
			if (!file_exists($this->directory) && !mkdir($this->directory, 0755, true))
				throw new \Exception("The specified block cacher storage directory ($this->directory) could not be created. Please ensure you have the correct permissions to create this directory.");
		}
		
		/**
		 * Gets a list of all cache files in the storage directory.
		 * @return string[]
		 */
		public function getCacheFilePaths()
		{
			return glob($this->directory . '*');
		}
		
		/**
		 * Adds a file pattern to be protected when clearing cache files.
		 * @param string $pattern
		 */
		public function protectFilePattern($pattern)
		{
			$this->protectedPatterns[] = $pattern;
		}
		
		/**
		 * Clears cache files using a specified pattern (defaults to all files) or a specified filename.
		 * @param string $pattern The glob file search pattern.
		 * @param bool $isFilename Set to true to treat the pattern parameter as an explicit filename.
		 * @param bool $includeProtectedPatterns Set to true to include protected cache file patterns in the clear process.
		 * @return BlockCacherClearResults
		 */
		public function clear($pattern = '*', $isFilename = false, $includeProtectedPatterns = false)
		{
			if ($isFilename)
				$files = array($pattern);
			else
			{
				$files = glob($this->directory . $pattern);
				if (!$includeProtectedPatterns && !empty($this->protectedPatterns))
				{
					$files = array_filter($files, function($file)
					{
						$filename = pathinfo($file, PATHINFO_BASENAME);
						foreach($this->protectedPatterns as $pattern) {
							if (fnmatch($pattern, $filename)) {
								return false;
							}
						}
						return true;
					});
				}
			}
			
			$cleared = array();
			foreach($files as $file)
				if (unlink($file))
					$cleared[] = $file;

			return new BlockCacherClearResults($files, $cleared);
		}
		
		/**
		 * Gets a cached value that is still valid. If the cached value does not exist or has expired,
		 * null is returned.
		 * @param string $key The key for the cached value.
		 * @param int $lifetime The lifetime for the cached value in seconds. The default is 86,400 (one day).
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return mixed|null
		 */
		public function get($key, $lifetime = self::DefaultLifetime, $prefixed = true)
		{
			if (!$this->enabled && !$this->forceCached)
				return null;
			
			$filename = $this->filepath($key, $prefixed);
			if (!$this->isValid($filename, $lifetime))
				return null;
			
			$tmp = fopen($filename, 'r');
			@flock($tmp, LOCK_SH);
			$contents = file_get_contents($filename);
			@flock($tmp, LOCK_UN);
			fclose($tmp);
			return unserialize($contents);
		}
		
		/**
		 * Gets the filename for a cache key.
		 * @param string $key The key for the cached value.
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return string
		 */
		public function filepath($key, $prefixed = true)
		{
			return $this->directory . ($prefixed ? "$this->prefix$key" : $key);
		}
		
		/**
		 * Determines if a cache file exists and is valid.
		 * @param string $filename
		 * @param $lifetime
		 * @return bool
		 */
		private function isValid($filename, $lifetime)
		{
			return file_exists($filename) &&
			       filemtime($filename) > (time() - $lifetime);
		}
		
		/**
		 * Determines if a cached value exists and is valid.
		 * @param string $key The key for the cached value.
		 * @param int $lifetime The arbitrary lifetime of the cached value (in seconds).
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return bool
		 */
		public function exists($key, $lifetime = self::DefaultLifetime, $prefixed = true)
		{
			if (!$this->enabled && !$this->forceCached)
				return null;
			
			return $this->isValid($this->filepath($key, $prefixed), $lifetime);
		}
		
		/**
		 * Stores a serializable value in the file cache.
		 * @param string $key The key for the cached value.
		 * @param mixed $value The value to store in the cache.
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return bool
		 */
		public function store($key, $value, $prefixed = true)
		{
			return $this->storeText($key, serialize($value), $prefixed);
		}
		
		/**
		 * Stores a string value in the file cache. This does not serialize the value.
		 * @param string $key The key for the cached value.
		 * @param mixed $value The value to store in the cache.
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return bool
		 */
		public function storeText($key, $value, $prefixed = true)
		{
			if (!$this->enabled && !$this->forceCached)
				return false;
			return file_put_contents($this->filepath($key, $prefixed), $value, LOCK_EX) !== false;
		}
		
		/**
		 * Starts a caching buffer, otherwise storing the existing cached contents until end() is called.
		 * @param string $key The key for the cached value.
		 * @param int $lifetime The arbitrary lifetime of the cached value (in seconds).
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return bool Returns true if the output should be generated, false if the cache exists and is valid.
		 */
		public function start($key, $lifetime = self::DefaultLifetime, $prefixed = true)
		{
			$this->buffers[] = $buffer = new BlockCacherOutputBuffer($key, $prefixed, $this->get($key, $lifetime, $prefixed));
			
			if (!$buffer->hit)
				ob_start();
			return !$buffer->hit;
		}
		
		/**
		 * Stores the output buffer into the cache file and optionally echoes the content.
		 * @param bool $echo Set to true to echo the contents of the buffer automatically.
		 * @return BlockCacherOutputBuffer The output buffer information.
		 * @throws
		 */
		public function end($echo = true)
		{
			if (empty($this->buffers))
				throw new \Exception('No block cacher buffer has been started.');
			
			$buffer = array_pop($this->buffers);
			if (!$buffer->hit)
			{
				$buffer->contents = ob_get_clean();
				$this->storeText($buffer->key, $buffer->contents, $buffer->prefixed);
			}
			
			if ($echo)
				echo $buffer->contents;
			
			return $buffer;
		}
	}