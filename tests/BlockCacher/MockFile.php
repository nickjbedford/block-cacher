<?php
	namespace BlockCacher;
	
	class MockFile
	{
		public string $contents;
		public int $modified;
		
		public function __construct(string $contents)
		{
			$this->modified = time();
			$this->contents = $contents;
		}
	}
