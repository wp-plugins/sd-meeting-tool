<?php
/**
	Actions can be fired by using interface_SD_Meeting_Tool_Actions::sd_mt_trigger_action action.
	
	Actions are stored by the @ref SD_Meeting_Tool_Actions plugin.
	
	@brief		A container class for several (SD_Meeting_Tool_Action_Item)s.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
	@see		interface_SD_Meeting_Tool_Actions
	@see		SD_Meeting_Tool_Actions
**/
class SD_Meeting_Tool_Action
{
	/**
		@var	$id
		ID of action.
	**/
	public $id;
	
	/**
		Serialized data.
		Contains:
		
		- @b name Human-readable name of the agenda. About 200 chars. 

		@var	$data
	**/ 
	public $data;

	/**
		@var	$items
		Array of SD_Meeting_Tool_Action_Item.
	**/
	public $items = array();
	
	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->name = '';
	}
}
