<?php
	/** @noinspection PhpUnused */
	
	namespace BlockCacher;
	
	/**
	 * Represents a file system that can be used to read, write,
	 * determine if files exist and when they were last modified.
	 * This interface allows other storage systems to be used in
	 * place of the actual system file system.
	 *
	 * The file system should handle the necessary permissions of
	 * paths it creates to ensure read/write capability.
	 * @package BlockCacher
	 */
	interface IFileSystem
	{
		/**
		 * Determines if a file or directory exists.
		 * @param string $path
		 * @return bool
		 */
		function pathExists(string $path): bool;
		
		/**
		 * Determines if a path points to a file.
		 * @param string $path
		 * @return bool
		 */
		function isFile(string $path): bool;
		
		/**
		 * Creates a directory path recursively.
		 * @param string $path
		 * @return bool
		 */
		function createDirectory(string $path): bool;
		
		/**
		 * Reads a file's contents from the storage medium. If the file
		 * does not exist, null should be returned.
		 * @param string $path
		 * @return string|null
		 */
		function readFile(string $path): ?string;
		
		/**
		 * Writes a file's contents to the storage medium.
		 * @param string $path
		 * @param string $contents
		 * @return bool
		 */
		function writeFile(string $path, string $contents): bool;
		
		/**
		 * Delete's a file from the storage medium.
		 * @param string $path
		 * @return bool
		 */
		function deleteFile(string $path): bool;
		
		/**
		 * Gets the modified time of a file. This is required to
		 * determine the age of a cached file.
		 * @param string $path
		 * @return int
		 */
		function getModifiedTime(string $path): int;
		
		/**
		 * Searches for files in the file systems using a glob
		 * pattern matching path.
		 * @param string $globPattern
		 * @return array
		 */
		function searchFiles(string $globPattern): array;
	}
