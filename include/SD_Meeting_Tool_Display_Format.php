<?php
/**
	The container contains info about the id and the human-readable name of the format.
	
	The $data structure is internal to SD_Meeting_Tool_Display_Formats.

	@brief		Container for a display format.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Display_Format
{
	/**
		ID of the display format.
		
		@var	$id
	**/
	public $id;
	
	/**
		Various data as a stdClass.
		
		Stores:
		- @b display_format		Array of display data.
		- @b name				Name of agenda.
		  
		$var	$data
	**/
	public $data;
	
	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->display_format = array();
		$this->data->name = '';
	}
}
