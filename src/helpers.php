<?php
	/** @noinspection PhpUnused */
	
	use BlockCacher\BlockCacher;
	
	/**
	 * Gets the default block cacher instance.
	 * @return BlockCacher
	 */
	function blockCacher()
	{
		return BlockCacher::default();
	}
