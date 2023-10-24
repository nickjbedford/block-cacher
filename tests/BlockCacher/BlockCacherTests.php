<?php
	/** @noinspection PhpUnhandledExceptionInspection */
	namespace
	{
		require_once(__DIR__ . '/MockFileSystem.php');
	}
	
	namespace BlockCacher
	{
		use Exception;
		use PHPUnit\Framework\TestCase;
		
		class BlockCacherTests extends TestCase
		{
			const RootCacheDirectory = __DIR__ . '/cache/';
			
			private array $cachers;
			private BlockCacher $native;
			private BlockCacher $mock;
			
			const CachePrefix = 'test-';
			
			public static function cacherProvider(): array
			{
				return [ [ 0 ], [ 1 ] ];
			}
			
			public function setUp(): void
			{
				parent::setUp();
				$this->cachers = [
					$this->native = new BlockCacher(self::RootCacheDirectory . bin2hex(random_bytes(8)), self::CachePrefix),
					$this->mock = new BlockCacher(self::RootCacheDirectory . bin2hex(random_bytes(8)), self::CachePrefix, true, new MockFileSystem()),
				];
				foreach($this->cachers as $cacher)
					$cacher->clear();
			}
			
			public function tearDown(): void
			{
				foreach($this->cachers as $cacher)
					$cacher->clear();
				$cacheDirectory = $this->cachers[0]->directory();
				@rmdir($cacheDirectory);
				@rmdir(self::RootCacheDirectory);
				parent::tearDown();
			}
			
			/**
			 * @throws Exception
			 */
			public function testNamedInstances()
			{
				$this->native->register('native');
				$this->mock->register('mock');
				
				$this->assertSame($this->native, blockCacher());
				$this->assertSame($this->native, blockCacher('native'));
				$this->assertSame($this->mock, blockCacher('mock'));
			}
			
			/**
			 * @dataProvider cacherProvider
			 * @param int $i
			 * @throws Exception
			 */
			public function testGetAndStoreKey(int $i)
			{
				$cacher = $this->cachers[$i];
				$this->assertNull($cacher->get('someKey'));
				$this->assertTrue($cacher->store('someKey', true));
				$this->assertTrue($cacher->get('someKey'));
				$this->assertNull($cacher->get('someKey', -1));
				
				$this->assertTrue($cacher->storeText('someKey', 'Hello, world!'));
				$this->assertEquals('Hello, world!', $cacher->getText('someKey'));
			}
			
			/**
			 * @dataProvider cacherProvider
			 * @param int $i
			 * @throws Exception
			 */
			public function testKeyExists(int $i)
			{
				$cacher = $this->cachers[$i];
				$this->assertFalse($cacher->exists('someKey'));
				$this->assertTrue($cacher->store('someKey', true));
				$this->assertTrue($cacher->exists('someKey'));
			}
			
			/**
			 * @dataProvider cacherProvider
			 * @param int $i
			 * @throws Exception
			 */
			public function testStartAndEnd(int $i)
			{
				$cacher = $this->cachers[$i];
				$value = 'Hello, world!';
				if ($cacher->start('buffer'))
					echo $value;
				$buffer = $cacher->end(false);
				
				$this->assertFalse($buffer->hit);
				$this->assertEquals($value, $buffer->contents);
				
				$hit = true;
				if ($cacher->start('buffer'))
					$hit = false;
				$buffer = $cacher->end(false);
				$this->assertTrue($hit);
				$this->assertEquals($value, $buffer->contents);
			}
			
			/**
			 * @dataProvider cacherProvider
			 * @param int $i
			 * @throws Exception
			 */
			public function testClear(int $i)
			{
				$cacher = $this->cachers[$i];
				
				$cacher->store('other', true);
				for($i = 0; $i < 10; $i++)
					$cacher->store("key-$i", true);
				
				$results = $cacher->clear('key-*');
				
				$this->assertEquals(10, $results->count());
				$this->assertEquals(10, $results->total());
				
				$results = $cacher->clear();
				$this->assertEquals(1, $results->count());
				
				$this->assertEmpty($cacher->getCacheFilePaths());
			}
			
			/**
			 * @dataProvider cacherProvider
			 * @param int $i
			 * @throws Exception
			 */
			public function testGenerate(int $i)
			{
				$cacher = $this->cachers[$i];
				$generated = false;
				$data = $cacher->generate('generated', function() use(&$generated)
				{
					$generated = true;
					return [ 'Data' ];
				});
				
				$this->assertEquals('Data', $data[0]);
				$this->assertTrue($generated);
				
				$generatedTwice = false;
				$data = $cacher->generate('generated', function() use(&$generatedTwice)
				{
					$generatedTwice = true;
					return [ 'New Data' ];
				});
				
				$this->assertEquals('Data', $data[0]);
				$this->assertFalse($generatedTwice);
			}
			
			/**
			 * @dataProvider cacherProvider
			 * @param int $i
			 * @throws Exception
			 */
			public function testGenerateText(int $i)
			{
				$cacher = $this->cachers[$i];
				$generated = false;
				$text = $cacher->generateText('generated', function() use(&$generated)
				{
					$generated = true;
					return 'Some text';
				});
				
				$this->assertEquals('Some text', $text);
				$this->assertTrue($generated);
				
				$generatedTwice = false;
				$text = $cacher->generateText('generated', function() use(&$generatedTwice)
				{
					$generatedTwice = true;
					return 'New text';
				});
				
				$this->assertEquals('Some text', $text);
				$this->assertFalse($generatedTwice);
			}
			
			/**
			 * @dataProvider cacherProvider
			 * @param int $i
			 * @throws Exception
			 */
			public function testGenerateHtml(int $i)
			{
				$cacher = $this->cachers[$i];
				$generated = false;
				
				ob_start();
				$html = $cacher->html('generated.html', function() use(&$generated)
				{
					$generated = true;
					?>This is some output.<?
				});
				$outerBuffer = ob_get_clean();
				
				$this->assertEquals('', $outerBuffer);
				$this->assertEquals('This is some output.', $html);
				$this->assertTrue($generated);
				
				$generatedTwice = false;
				$html = $cacher->html('generated.html', function() use(&$generatedTwice)
				{
					$generatedTwice = true;
					?>This is some other output.<?
				});
				
				$this->assertEquals('This is some output.', $html);
				$this->assertFalse($generatedTwice);
			}
			
			/**
			 * @throws Exception
			 */
			public function testNativeFileSystemWritablePermissions()
			{
				$cacher = $this->native;
				$name = bin2hex(random_bytes(16));
				$this->assertTrue($cacher->store($name, 'Test data.'));
				
				$filePermissions = fileperms($cacher->filepath($name)) & 0777;
				$directoryPermissions = fileperms($cacher->directory()) & 0777;
				
				$this->assertEquals(NativeFileSystem::FilePermissions, $filePermissions);
				$this->assertEquals(NativeFileSystem::DirectoryPermissions, $directoryPermissions);
			}
		}
	}
