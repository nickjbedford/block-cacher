<?php
	use BlockCacher\BlockCacher;
	
	/**
	 * Gets the default block cacher.
	 * @return BlockCacher
	 */
	function blockCacher()
	{
		return BlockCacher::default();
	}
