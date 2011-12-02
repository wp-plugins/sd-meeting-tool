<?php
/**
	@brief		Container for a collection of {SD_Meeting_Tool_List_Sort_Order}s.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_List_Sort
{
	/**
		Unique ID integer.
		@var	$id
	**/
	public $id;
	
	/**
		Serialized data.
		Contains:
		
		- @b order					Array of SD_Meeting_Tool_List_Sort_Order. 
		- @b name					Human-readable name of the agenda. About 200 chars. 

		@var	$data
	**/ 
	public $data;
	
	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->name = '';
		$this->data->orders = array();
	}
}
