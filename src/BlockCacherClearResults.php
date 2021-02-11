<?php
	namespace BlockCacher;
	
	/**
	 * Represents the results of a BlockCacher::clear() procedure.
	 */
	class BlockCacherClearResults
	{
		/** @var string[] $allFiles Specifies the list of files determined to be cleared. */
		public $allFiles;
		
		/** @var string[] $clearedFiles Specifies the list of files that were cleared successfully. */
		public $clearedFiles;
		
		/**
		 * Initialises a new instance of the clear results.
		 * @param string[] $allFiles The list of cache files to be cleared.
		 * @param string[] $clearedFiles The list of cache files that were successfully cleared.
		 */
		public function __construct(array $allFiles, array $clearedFiles)
		{
			$this->allFiles = $allFiles;
			$this->clearedFiles = $clearedFiles;
		}
		
		/**
		 * Gets the number of cache files successfully cleared.
		 * @return int
		 */
		public function count(): int
		{
			return count($this->clearedFiles);
		}
		
		/**
		 * Gets the number of cache files to be cleared.
		 * @return int
		 */
		public function total(): int
		{
			return count($this->allFiles);
		}
	}
