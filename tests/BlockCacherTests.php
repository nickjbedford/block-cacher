<?php
	namespace BlockCacher;
	
	/**
	 * Project: BlockCache
	 * Created By: nickbedford
	 * Created: 2019-11-29 1:01 pm
	 */
	
	class BlockCacherTests extends \PHPUnit\Framework\TestCase
	{
		/** @var BlockCacher $cacher */
		private $cacher;
		
		const CachePrefix = 'test-';
		
		public function setUp()
		{
			parent::setUp();
			$cacheDirectory = __DIR__ . '/cache/';
			exec("rm -rf \"$cacheDirectory\"");
			$this->cacher = new BlockCacher($cacheDirectory . bin2hex(random_bytes(8)), self::CachePrefix);
		}
		
		public function tearDown()
		{
			$this->cacher->clear();
			$cacheDirectory = $this->cacher->directory();
			exec("rm -rf \"$cacheDirectory\"");
			parent::tearDown();
		}
		
		public function testGetAndStoreKey()
		{
			$cacher = $this->cacher;
			$this->assertNull($cacher->get('someKey'));
			$this->assertTrue($cacher->store('someKey', true));
			$this->assertTrue($cacher->get('someKey'));
			$this->assertNull($cacher->get('someKey', -1));
			
			$this->assertTrue($cacher->storeText('someKey', 'Hello, world!'));
			$this->assertEquals('Hello, world!', $cacher->getText('someKey'));
		}
		
		public function testKeyExists()
		{
			$cacher = $this->cacher;
			$this->assertFalse($cacher->exists('someKey'));
			$this->assertTrue($cacher->store('someKey', true));
			$this->assertTrue($cacher->exists('someKey'));
		}
		
		public function testStartAndEnd()
		{
			$cacher = $this->cacher;
		}
		
		public function testClear()
		{
			$cacher = $this->cacher;
			$cacher->store('other', true);
			for($i = 0; $i < 10; $i++)
				$cacher->store("key-$i", true);
			$results = $cacher->clear('key-*');
			$this->assertEquals(10, $results->count());
			$this->assertEquals(10, $results->total());
			$results = $cacher->clear('*');
			$this->assertEquals(1, $results->count());
		}
	}
