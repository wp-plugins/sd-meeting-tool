<?php
/**
	Each list essentially contains a number of participants, optionally even a list of included and excluded lists and
	some other, internal, information such as display format and sort order.
	
	@brief		List class, acting as a participant container.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_List
{
	/**
		ID of list.
		@var	$id
	**/
	public $id;
	
	/**
		Array of included list IDs.
		@var	$includes
	**/
	public $includes;
	
	/**
		Array of excluded list IDs.
		@var	$excludes
	**/
	public $excludes;
	
	/**
		Serialized data.
		Contains:
		
		- @b display_format_id		Display format ID to use. 
		- @b list_sort_id			List sort to use. 
		- @b name				Human-readable name of the agenda. About 200 chars. 

		@var	$data
	**/ 
	public $data;
	
	/**
		Array of list_participant objects.
		@var	$participants
	**/
	public $participants = array();
	
	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->display_format_id = false;
		$this->data->list_sort_id = false;
		$this->data->name = '';
	}
	
	/**
		Returns the current display format.
		@return		The list's current display format.
	**/
	public function display_format_id()
	{
		return $this->data->display_format_id;
	}
	
	/**
		@brief		Returns the display format used by this list.
		@return		The SD_Meeting_Tool_Display_Format used by this list.
		@see		SD_Meeting_Tool_Display_Format
	**/
	public function get_display_format()
	{
		return apply_filters( 'sd_mt_get_display_format', $this->display_format_id() );
	}
	
	/**
		Returns the current list sort.
		@return		The list's current list sort.
	**/
	public function list_sort_id()
	{
		return $this->data->list_sort_id;
	}
}