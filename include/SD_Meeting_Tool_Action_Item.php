<?php
/**
	An Action Item must have a short string that identifies the item internally and an order in which is it executed
	by the SD_Meeting_Tool_Actions plugin.
	
	Since Action Items are dynamically created by any plugins that provide Action Items, the $data variable can be
	anything the respective plugins desire. The data is serialized, stored and retrieved by the actions plugin.
	
	@brief		An actual command that is exectued by the plugins.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
	@see		SD_Meeting_Tool_Action
**/
class SD_Meeting_Tool_Action_Item
{
	/**
		Item's data, defined and used by the specific module that uses this type.
		@var	$data
	**/
	public $data = null;
	
	/**
		Human-readable string describing what this item does.
		Used mostly by SD_Meeting_Tool_Actions for display to the user.
		@var	$description
	**/
	public $description;
	
	/**
		Int ID of item.
		@var	$id
	**/
	public $id;

	/**
		Order of item in the action's list of items.
		
		Default is 1. Lower means first.
		
		@var	$order
	**/
	public $order;
	
	/**
		A string that identifies the item type.
		@var	$type
	**/
	public $type;
	
	/**
		Backup method to give the user a bare minimum of a string in case the plugins don't convert this item to a string themselves.
		@return	The item's type.
	**/
	public function __tostring()
	{
		return $this->type;
	}
}	
