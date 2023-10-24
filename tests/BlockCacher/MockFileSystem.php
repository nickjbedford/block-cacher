<?php
	namespace {
		require_once(__DIR__ . '/MockFile.php');
	}
	
	namespace BlockCacher
	{
		class MockFileSystem implements IFileSystem
		{
			/** @var MockFile[] $files */
			private array $files = [];
			
			/**
			 * @inheritDoc
			 */
			function pathExists(string $path): bool
			{
				return isset($this->files[$path]);
			}
			
			/**
			 * @inheritDoc
			 */
			function isFile(string $path): bool
			{
				return $this->pathExists($path);
			}
			
			/**
			 * @inheritDoc
			 */
			function createDirectory(string $path): bool
			{
				return true;
			}
			
			/**
			 * @inheritDoc
			 */
			function readFile(string $path): ?string
			{
				if ($file = $this->files[$path] ?? null)
					return $file->contents;
				return null;
			}
			
			/**
			 * @inheritDoc
			 */
			function writeFile(string $path, string $contents): bool
			{
				$this->files[$path] = new MockFile($contents);
				return true;
			}
			
			/**
			 * @inheritDoc
			 */
			function deleteFile(string $path): bool
			{
				if (isset($this->files[$path]))
				{
					unset($this->files[$path]);
					return true;
				}
				return false;
			}
			
			/**
			 * @inheritDoc
			 */
			function getModifiedTime(string $path): int
			{
				return $this->pathExists($path) ?
					$this->files[$path]->modified : 0;
			}
			
			/**
			 * @inheritDoc
			 */
			function searchFiles(string $globPattern): array
			{
				$found = [];
				foreach($this->files as $path=>$file)
					if (fnmatch($globPattern, $path))
						$found[] = $path;
				return $found;
			}
		}
	}
