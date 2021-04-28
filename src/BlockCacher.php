<?php
	/** @noinspection PhpUnused */
	
	namespace BlockCacher;
	
	use Closure;
	use Exception;
	
	/**
	 * Represents a file-based data, HTML and text caching mechanism.
	 * This "block" cacher class provides the ability to generate and
	 * store data efficiently using the file system alone. Reduce load
	 * times to fractions of a millisecond by caching generated information.
	 * Minimum PHP support is PHP 7.3.
	 */
	class BlockCacher
	{
		/** @var int Specifies the default lifetime for cache files of one day. */
		const DefaultLifetime = 86400;
		
		/** @var int Specifies the default expiry time randomisation in seconds. */
		const DefaultExpiryTimeRandomisation = 5;
		
		/** @var BlockCacher $default Specifies the default cacher. */
		private static $default;
		
		/** @var BlockCacher[] $namedCachers Specifies the array of named cachers. */
		private static $namedCachers = [];
		
		/** @var BlockCacherOutputBuffer[] $buffers Stores the stack of currently open buffers. */
		private $buffers = array();
		
		/** @var string $directory Specifies the directory where cache files will be stored. */
		private $directory;
		
		/** @var string $prefix Specifies the text to prefix to all filenames. */
		private $prefix;
		
		/** @var bool $forceCached Specifies whether to force caching regardless of whether the cacher is enabled. */
		private $forceCached = false;
		
		/** @var bool $enabled Specifies whether the cacher will actually use cached data or not. */
		private $enabled = true;
		
		/** @var string[] $protectedPatterns Specifies the list of file patterns to protect when clearing the cache. */
		private $protectedPatterns = array();
		
		/** @var int $expiryTimeRandomisation Specifies the expiry-time randomisaton in seconds. This dithers the expiry of cache. */
		private $expiryTimeRandomisation = 0;
		
		/** @var IFileSystem $fileSystem Specifies the file system interface to use. */
		private $fileSystem;
		
		/**
		 * Initialises a new instance of the block cacher.
		 * @param string $directory The directory to store all cache files in.
		 * @param string $filePrefix Optional. The prefix to add to cache filenames (i.e. such localisation, versions).
		 * @param boolean $automaticallyEnsureStorageDirectoryExists Set to true to automatically ensure the storage directory exists.
		 * @param IFileSystem|null $fileSystem Optional. Specifies the file system interface to use.
		 * By default, this is the built-in file system.
		 * @throws Exception
		 */
		public function __construct(
			string $directory,
			string $filePrefix = '',
			bool $automaticallyEnsureStorageDirectoryExists = true,
			?IFileSystem $fileSystem = null)
		{
			$this->fileSystem = $fileSystem ?? new NativeFileSystem();
			$this->directory = rtrim($directory, '/\\') . '/';
			$this->prefix = $filePrefix;
			
			if ($automaticallyEnsureStorageDirectoryExists)
				$this->ensureStorageDirectoryExists();
			
			if (!self::$default)
				$this->setAsDefault();
			
			$this->setExpiryTimeRandomisation(self::DefaultExpiryTimeRandomisation);
		}
		
		/**
		 * Creates and sets a new block cacher as the default.
		 * @param string $directory The directory to store all cache files in.
		 * @param string $filePrefix Optional. The prefix to add to cache filenames (i.e. such localisation, versions).
		 * @param boolean $automaticallyEnsureStorageDirectoryExists Set to true to automatically ensure the storage directory exists.
		 * @throws
		 * @return self
		 */
		public static function createDefault(
			string $directory,
			string $filePrefix = '',
			bool $automaticallyEnsureStorageDirectoryExists = true): BlockCacher
		{
			return (new self($directory, $filePrefix, $automaticallyEnsureStorageDirectoryExists))
				->setAsDefault();
		}
		
		/**
		 * Sets this instance to be the default instance.
		 * @return self
		 */
		public function setAsDefault(): self
		{
			return self::$default = $this;
		}
		
		/**
		 * Gets the default block cacher instance.
		 * @return self|null
		 */
		public static function default(): ?self
		{
			return self::$default;
		}
		
		/**
		 * Registers the cacher under a globally accessible name.
		 * @param string $name The name of the cacher.
		 */
		public function register(string $name): void
		{
			self::$namedCachers[$name] = $this;
		}
		
		/**
		 * Gets the default, or a named, cacher instance.
		 * @param string|null $cacherName Optional. The name of the cacher.
		 * @return BlockCacher|null
		 */
		public static function instance(?string $cacherName = null): ?BlockCacher
		{
			return $cacherName ? (self::$namedCachers[$cacherName] ?? null) : self::default();
		}
		
		/**
		 * Sets the randomisation of known expiry times to add a "dither" to cache misses.
		 * This can assist in minimising the synchronous expiry of related caches to reduce
		 * instantaneous cache generation load.
		 * @param int $seconds The maximum number of random seconds to add to the known expiry time.
		 * @return self
		 */
		public function setExpiryTimeRandomisation(int $seconds = 0): BlockCacher
		{
			$this->expiryTimeRandomisation = max(0, min(getrandmax(), $seconds));
			return $this;
		}
		
		/**
		 * Gets the block cache storage directory.
		 * @return string
		 */
		public function directory(): string
		{
			return $this->directory;
		}
		
		/**
		 * Ensures the storage directory exists. An exception will be thrown if it cannot be created.
		 * @throws
		 */
		public function ensureStorageDirectoryExists(): void
		{
			if (!$this->fileSystem->pathExists($this->directory) &&
			    !$this->fileSystem->createDirectory($this->directory))
				throw new Exception("The specified block cacher storage directory ($this->directory) could not be created. Please ensure you have the correct permissions to create this directory.");
		}
		
		/**
		 * Gets a list of all cache files in the storage directory.
		 * @param string $pattern The glob file pattern to search for cache files.
		 * @return string[]
		 */
		public function getCacheFilePaths(string $pattern = '*'): array
		{
			return $this->fileSystem->searchFiles($this->directory . $pattern);
		}
		
		/**
		 * Adds a file pattern to be protected when clearing cache files. This should
		 * follows the rules of glob file pattern matching.
		 * @param string $pattern
		 */
		public function protectFilePattern(string $pattern)
		{
			$this->protectedPatterns[] = $pattern;
		}
		
		/**
		 * Clears cache files found using a specified pattern (defaults to all files).
		 * @param string $pattern The glob file search pattern.
		 * @param bool $prefixed Whether to add the cacher's prefix to the pattern.
		 * @param bool $clearProtectedFiles Set to true to include protected cache file patterns in the clear process.
		 * @param int $minimumAge Only files older than this many seconds are cleared.
		 * @return BlockCacherClearResults The results of the clearing process.
		 */
		public function clear($pattern = '*', $prefixed = true, $clearProtectedFiles = false, int $minimumAge = 0): BlockCacherClearResults
		{
			if ($prefixed)
				$pattern = "$this->prefix$pattern";
			
			$files = $this->fileSystem->searchFiles($this->directory . $pattern) ?: [];
			
			if (!$clearProtectedFiles && !empty($this->protectedPatterns))
				$files = $this->filterProtectedFiles($files);
			
			if ($minimumAge > 0)
				$files = $this->filterNewerFiles($minimumAge, $files);
			
			$cleared = array();
			foreach($files as $file)
				if ($this->fileSystem->isFile($file) &&
				    $this->fileSystem->deleteFile($file))
					$cleared[] = $file;

			return new BlockCacherClearResults($files, $cleared);
		}
		
		/**
		 * Filters out any protected files based on the protected file patterns.
		 * @param array $files The files to be cleared.
		 * @return array
		 */
		private function filterProtectedFiles(array $files): array
		{
			return array_filter($files, function ($file)
			{
				$filename = pathinfo($file, PATHINFO_BASENAME);
				foreach ($this->protectedPatterns as $pattern)
				{
					if (fnmatch($pattern, $filename))
						return false;
				}
				return true;
			});
		}
		
		/**
		 * Filters files out that are newer than a specified age in seconds.
		 * @param int $minimumAge The minimum age of the files in seconds.
		 * @param array $files The files to filter.
		 * @return array
		 */
		private function filterNewerFiles(int $minimumAge, array $files): array
		{
			$timestamp = time() - $minimumAge;
			$self = $this;
			return array_filter($files, function(string $filename) use($timestamp, $self)
			{
				return $self->fileSystem->getModifiedTime($filename) <= $timestamp;
			});
		}
		
		/**
		 * Gets a cached string that is still valid. If the cached value does not exist or has expired,
		 * null is returned.
		 * @param string $key The key for the cached value.
		 * @param int $lifetime The lifetime for the cached value in seconds. The default is 86,400 (one day).
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return string|null
		 */
		public function getText(string $key, int $lifetime = self::DefaultLifetime, bool $prefixed = true): ?string
		{
			if (!$this->enabled && !$this->forceCached)
				return null;
			
			$filename = $this->filepath($key, $prefixed);
			if (!$this->isValid($filename, $lifetime))
				return null;
			
			return $this->fileSystem->readFile($filename);
		}
		
		/**
		 * Gets a cached value that is still valid. If the cached value does not exist or has expired,
		 * null is returned.
		 * @param string $key The key for the cached value.
		 * @param int $lifetime The lifetime for the cached value in seconds. The default is 86,400 (one day).
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return mixed|null
		 */
		public function get(string $key, int $lifetime = self::DefaultLifetime, bool $prefixed = true)
		{
			$value = $this->getText($key, $lifetime, $prefixed);
			return $value !== null ? unserialize($value) : null;
		}
		
		/**
		 * Gets the filename for a cache key.
		 * @param string $key The name for the cached value.
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return string
		 */
		public function filepath(string $key, bool $prefixed = true): string
		{
			return $this->directory . ($prefixed ? "$this->prefix$key" : $key);
		}
		
		/**
		 * Determines if a cache file exists and is valid.
		 * @param string $filepath The full path of the file.
		 * @param int $lifetime The lifetime in seconds.
		 * @return bool
		 */
		private function isValid(string $filepath, int $lifetime): bool
		{
			if (!$this->fileSystem->pathExists($filepath))
				return false;
			
			$timeRandomisation = rand(0, $this->expiryTimeRandomisation);
			$cacheTime = $this->fileSystem->getModifiedTime($filepath) - $timeRandomisation;
			$notBefore = time() - $lifetime;
			return $cacheTime > $notBefore;
		}
		
		/**
		 * Determines if a cached value exists and is valid.
		 * @param string $key The key for the cached value.
		 * @param int $lifetime The arbitrary lifetime of the cached value (in seconds).
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return bool
		 */
		public function exists(string $key, int $lifetime = self::DefaultLifetime, bool $prefixed = true): bool
		{
			return (($this->enabled || $this->forceCached)) && $this->isValid($this->filepath($key, $prefixed), $lifetime);
		}
		
		/**
		 * Stores a value in the file cache. The value must be serializable
		 * using the native serialize() function.
		 * @param string $key The key for the cached value.
		 * @param mixed $value The value to store in the cache.
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return bool
		 */
		public function store(string $key, $value, bool $prefixed = true): bool
		{
			return $this->storeText($key, serialize($value), $prefixed);
		}
		
		/**
		 * Stores a string value in the file cache. This does not serialize the value
		 * but will coerce its type to string.
		 * @param string $key The key for the cached value.
		 * @param mixed $value The value to store in the cache as text.
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return bool
		 */
		public function storeText(string $key, $value, bool $prefixed = true): bool
		{
			$filepath = $this->filepath($key, $prefixed);
			return $this->fileSystem->writeFile($filepath, strval($value));
		}
		
		/**
		 * Starts a caching buffer, otherwise storing the existing cached contents until end() is called.
		 * @param string $key The key for the cached value.
		 * @param int $lifetime The arbitrary lifetime of the cached value (in seconds).
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return bool Returns true if the output should be generated, false if the cache exists and is valid.
		 */
		public function start(string $key, int $lifetime = self::DefaultLifetime, bool $prefixed = true): bool
		{
			$this->buffers[] = $buffer =
				new BlockCacherOutputBuffer($key, $prefixed, $this->getText($key, $lifetime, $prefixed));
			
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
		public function end(bool $echo = true): BlockCacherOutputBuffer
		{
			if (empty($this->buffers))
				throw new Exception('No block cacher buffer has been started.');
			
			$buffer = array_pop($this->buffers);
			if (!$buffer->hit)
			{
				$buffer->contents = ob_get_clean();
				if (!$this->storeText($buffer->key, $buffer->contents, $buffer->prefixed))
				{
					$file = $this->filepath($buffer->key, $buffer->prefixed);
					throw new Exception("The buffer could not be stored to \"$file\" using file_put_contents().");
				}
			}
			
			if ($echo)
				echo $buffer->contents;
			return $buffer;
		}
		
		/**
		 * Generates and caches data using a generator function only if the data is not yet cached.
		 * @param string $key The key for the cached value.
		 * @param callable|Closure $generator A callback that will generate the data if the cached data does not exist.
		 * @param int $lifetime The arbitrary lifetime of the cached value (in seconds).
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return mixed|null
		 */
		public function generate(string $key, $generator, int $lifetime = self::DefaultLifetime, bool $prefixed = true)
		{
			if (($data = $this->get($key, $lifetime, $prefixed)) === null)
			{
				$data = $generator();
				if ($data !== null)
					$this->store($key, $data, $prefixed);
			}
			return $data;
		}
		
		/**
		 * Generates and caches text using a generator function only if the text is not yet cached.
		 * This can be used in place of the html() function where the contents must be returned, not
		 * echoed to the output buffer.
		 * @param string $key The key for the cached value.
		 * @param callable|Closure $generator A callback that will return the text if the cached
		 * text does not exist.
		 * @param int $lifetime The arbitrary lifetime of the cache (in seconds).
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @return string
		 * @throws Exception
		 */
		public function generateText(string $key, $generator, int $lifetime = self::DefaultLifetime, bool $prefixed = true): string
		{
			if (($text = $this->getText($key, $lifetime, $prefixed)) === null)
			{
				$text = $generator();
				if ($text !== null)
					$this->storeText($key, $text, $prefixed);
			}
			return $text;
		}
		
		/**
		 * Generates and caches HTML output using a generator function only if the HTML is not yet cached.
		 * @param string $key The key for the cached value.
		 * @param callable|Closure $outputGenerator A callback that will print the HTML to the output buffer
		 * if the cached HTML does not exist.
		 * @param int $lifetime The arbitrary lifetime of the cache (in seconds).
		 * @param bool $prefixed Whether to add the cacher's prefix to this key.
		 * @param bool $echo Whether to echo the HTML directly to the output buffer afterwards.
		 * @return string
		 * @throws Exception
		 */
		public function html(string $key, $outputGenerator, int $lifetime = self::DefaultLifetime, bool $prefixed = true, bool $echo = false): string
		{
			if ($this->start($key, $lifetime, $prefixed))
			{
				$outputGenerator();
			}
			$buffer = $this->end($echo);
			return $buffer->contents;
		}
		
		/**
		 * Creates a cache block for an item-style naming convention.
		 * @param string $itemType The name of the item being cache.
		 * @param string $itemId The ID of the item being cached.
		 * @param string $blockType The type of block being cached for the item.
		 * @param int|null $version The version of the cached data being generated.
		 * @param string $extension The file extension for the cached data.
		 * @param string $separator The separator used to join the components of the name.
		 * @return Block
		 */
		public function itemBlock(
			string $itemType,
			string $itemId,
			string $blockType = '',
			?int $version = 1,
			string $extension = '.cache',
			string $separator = '-'): Block
		{
			return (new Block($this))->namedForItem($itemType, $itemId, $blockType, $version, $extension, $separator);
		}
		
		/**
		 * Sets the cache block name from an array of parts joined by a separator.
		 * @param array $nameParts
		 * @param string $separator
		 * @param string $extension
		 * @return Block
		 */
		public function block(array $nameParts, string $separator = '-', string $extension = '.cache'): Block
		{
			return (new Block($this))->namedFromParts($nameParts, $separator, $extension);
		}
	}
