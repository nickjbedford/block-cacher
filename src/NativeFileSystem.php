<?php
	namespace BlockCacher;
	
	/**
	 * Represents the native file system interface.
	 * @package BlockCacher
	 */
	class NativeFileSystem implements IFileSystem
	{
		const DirectoryPermissions = 0775;
		const FilePermissions = 0664;
		private bool $applyFullPermissions;
		
		/** @var int $writeRetryCount Specifies the number of write attempts that will be taken on the file (in the case of file locking issues). */
		public int $writeRetryCount = 3;
		
		/** @var int $writeRetryDelay Specifies the number of milliseconds to wait between write attempts (in the case of file locking issues). */
		public int $writeRetryDelay = 50;
		
		/**
		 * NativeFileSystem constructor.
		 * @param bool $applyFullPermissions If this is true, the umask will be
		 * reset to zero during any chmod() and mkdir() calls.
		 */
		public function __construct(bool $applyFullPermissions = true)
		{
			$this->applyFullPermissions = $applyFullPermissions;
		}
		
		/**
		 * @inheritDoc
		 */
		public function pathExists(string $path): bool
		{
			return file_exists($path);
		}
		
		/**
		 * @inheritDoc
		 */
		public function isFile(string $path): bool
		{
			return is_file($path);
		}
		
		/**
		 * @inheritDoc
		 * @noinspection PhpUndefinedVariableInspection
		 */
		public function createDirectory(string $path): bool
		{
			$tries = $this->writeRetryCount;
			while ($tries--)
			{
				if ($this->applyFullPermissions)
				{
					$umask = umask();
					umask(0);
				}
				
				$result = @mkdir($path, self::DirectoryPermissions, true);
				
				if ($this->applyFullPermissions)
					umask($umask);
				
				if ($result)
					break;
				
				usleep($this->writeRetryDelay * 1000);
			}
			
			return $result;
		}
		
		/**
		 * @inheritDoc
		 */
		public function deleteFile(string $path): bool
		{
			return @unlink($path);
		}
		
		/**
		 * @inheritDoc
		 */
		public function readFile(string $path): ?string
		{
			if (($tmp = @fopen($path, 'r')) === false)
				return null;
			
			$locked = @flock($tmp, LOCK_SH);
			
			$contents = @file_get_contents($path);
			
			if ($locked)
				@flock($tmp, LOCK_UN);
			
			fclose($tmp);
			return $contents !== false ? $contents : null;
		}
		
		/**
		 * @inheritDoc
		 * @noinspection PhpUndefinedVariableInspection
		 */
		public function writeFile(string $path, string $contents): bool
		{
			$tries = $this->writeRetryCount;
			while ($tries--)
			{
				$exists = file_exists($path);
				if (file_put_contents($path, $contents, LOCK_EX) !== false)
				{
					if (!$exists)
					{
						if ($this->applyFullPermissions)
						{
							$umask = umask();
							umask(0);
						}
						
						@chmod($path, self::FilePermissions);
						
						if ($this->applyFullPermissions)
							umask($umask);
					}
					return true;
				}
				
				usleep($this->writeRetryDelay * 1000);
			}
			return false;
		}
		
		/**
		 * @inheritDoc
		 */
		public function getModifiedTime(string $path): int
		{
			if (!file_exists($path))
				return 0;
			return filemtime($path) ?: 0;
		}
		
		/**
		 * @inheritDoc
		 */
		public function searchFiles(string $globPattern): array
		{
			return glob($globPattern);
		}
	}
