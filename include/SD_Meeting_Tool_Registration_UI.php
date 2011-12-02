<?php
/**
	@brief		Registration User Interface 
	@see		SD_Meeting_Tool_Registration
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Registration_UI
{
	/**
		Name of the UI.
		
		"Quick Text Search"
		@var	$name
	**/
	public $name;
	
	/**
		Version as a string.
		
		"1.3a"
		@var	$version
	**/
	public $version;
	
	/**
		Various settings and such.
		@var $data
	**/
	public $data;
	
	public function __construct()
	{
		$this->data = new stdClass();
	}
}
