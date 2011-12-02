<?php
/**
	@brief		An agenda, containing talking points for the day in the form of {SD_Meeting_Tool_Agenda_Item}s.
	@see		interface_SD_Meeting_Tool_Actions
	@see		SD_Meeting_Tool_Agenda_Item
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Agenda
{
	/**
		ID of agenda.
		@var	$id
	**/
	public $id;

	/**
		Array of {SD_Meeting_Tool_Agenda_Item}s.
		@var	$items
	**/
	public $items = array();
	
	/**
		Various data as a stdClass.
		
		Stores:
		- @b current_item_id	ID of the currently selected agenda item, if any. Else false.
		- @b name				Name of agenda.  
		$var	$data
	**/
	public $data;
	
	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->name = '';
		$this->data->current_item_id = false;
	}
	
	/**
		@return		The ID of the current agenda item, or false if no item is selected.
	**/
	public function get_current_item_id()
	{
		return $this->data->current_item_id;
	}

	/**
		Sets a new value for the current item id.
		
		@param		$new_item_id		The ID of the agenda item to set as the current.
	**/
	public function set_current_item_id($new_item_id)
	{
		$this->data->current_item_id = $new_item_id;
	}
	
	/**
		@param		$item_id		ID to check.
		@return		True if $item_id is the currently selected item.
	**/
	public function is_current_item_id( $item_id )
	{
		return $this->data->current_item_id == $item_id;
	}
}
