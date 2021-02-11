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
		private $applyFullPermissions;
		
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
			if ($this->applyFullPermissions)
			{
				$umask = umask();
				umask(0);
			}
			
			$result = mkdir($path, self::DirectoryPermissions, true);
			
			if ($this->applyFullPermissions)
				umask($umask);
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
			$tmp = fopen($path, 'r');
			@flock($tmp, LOCK_SH);
			$contents = file_get_contents($path);
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
			$exists = file_exists($path);
			if (file_put_contents($path, $contents, LOCK_EX) !== false)
			{
				if (!$exists)
				{
					if ($this->applyFullPermissions) {
						$umask = umask();
						umask(0);
					}
					@chmod($path, self::FilePermissions);
					if ($this->applyFullPermissions)
						umask($umask);
				}
				return true;
			}
			return false;
		}
		
		/**
		 * @inheritDoc
		 */
		public function getModifiedTime(string $path): int
		{
			return filemtime($path);
		}
		
		/**
		 * @inheritDoc
		 */
		public function searchFiles(string $globPattern): array
		{
			return glob($globPattern);
		}
	}
