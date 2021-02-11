<?php
	namespace BlockCacher;
	
	class MockFile
	{
		public $contents;
		public $modified;
		
		public function __construct(string $contents)
		{
			$this->modified = time();
			$this->contents = $contents;
		}
	}
