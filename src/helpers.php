<?php
	use BlockCacher\BlockCacher;
	
	/**
	 * Gets the default block cacher instance or a named instance if specified.
	 * @param string|null $cacherName Optional. The name of a cacher, otherwise the default/
	 * @return BlockCacher|null
	 */
	function blockCacher(?string $cacherName = null): ?BlockCacher
	{
		return BlockCacher::instance($cacherName);
	}
