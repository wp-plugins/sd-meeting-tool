<?php
require_once('SD_Meeting_Tool_Base.php');

class SD_Meeting_Tool_0
	extends SD_Meeting_Tool_Base
{
	// Make all plugins use the same .pot
	protected $language_domain = 'SD_Meeting_Tool';
	
	/**
		@return		The complete cache directory for the plugin.
	**/
	protected function cache_directory()
	{
		$returnValue = trailingslashit( dirname( $this->paths['__FILE__'] ) );
		$returnValue .= 'cache/';
		return $returnValue;
	}
	
	/**
		Returns the name of the cache file for this session.
	**/
	protected function cache_file( $id )
	{
		return $id . '_' . md5( $id . AUTH_SALT );
	}
	
	/**
		Returns the complete URL to the cache directory.
	**/
	protected function cache_url()
	{
		$returnValue = trailingslashit( $this->paths['url'] );
		$returnValue .= 'cache/';
		return $returnValue;
	}
	
	/**
		@return		True if the cache directory exists, is a directory, and is writeable.
	**/
	protected function check_cache_directory()
	{
		$dir = $this->cache_directory();

		if ( ! file_exists( $dir ) )
			mkdir( $dir );

		if ( ! is_dir( $dir ) )
			return false;

		if ( ! is_writeable( $dir ) )
			return false;

		return true;
	}
}
