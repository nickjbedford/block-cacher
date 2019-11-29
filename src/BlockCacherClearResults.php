<?php
	namespace BlockCacher;
	
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
