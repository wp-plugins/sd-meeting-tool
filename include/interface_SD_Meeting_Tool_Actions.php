<?php
/**
	Provides storage and editing services for {SD_Meeting_Tool_Action}s and allows triggering / firing of said actions.
	
	Other modules are encouraged to use the following filters:
	
	@par		filter: sd_mt_configure_action_item
	
	Ask the plugins to configure an action item, as per @ref SD_Meeting_Tool_Actions::admin_edit_item.
	
	@par		filter: sd_mt_get_action_item_types
	
	Return a list all item types, which is an array of SD_Meeting_Tool_Action_Item. See @ref SD_Meeting_Tool_Lists::sd_mt_get_action_item_types. 
	
	@par		filter: sd_mt_get_action_item_description
	
	Asks the plugins to properly describe the item. Used when the item has configured data and
	other plugins would like to tell the user more specifically what the item is going to to.
	See @ref SD_Meeting_Tool_Lists::sd_mt_get_action_item_description.
	
	@brief		Interface for Action plugins.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/	
interface interface_SD_Meeting_Tool_Actions
{
	/**
		Delete an action.
		
		@wp_filter
		@param		SD_Meeting_Tool_Action		$action		SD_Meeting_Tool_Action object to delete.
	**/
	public function sd_mt_delete_action( $action );
	
	/**
		Return an action.
		
		@wp_filter
		@param		int		$action_id		Action ID to get.
		@return		Returned SD_Meeting_Tool_Action, or false if it doesn't exist. 
	**/
	public function sd_mt_get_action( $action_id );
	
	/**
		Return a list of all available actions.
		
		@wp_filter
		@return											Array of SD_Meeting_Tool_Action.
	**/
	public function sd_mt_get_all_actions();
	
	/**
		Returns an action item.
		
		@wp_filter
		@param		int		$item_id			Item ID to get.
		@return		Returned SD_Meeting_Tool_Action_Item item, else false if it doesn't exist.
	**/
	public function sd_mt_get_action_item( $item_id );
	
	/**
		Action: Triggers an action.
		
		Given an SD_Meeting_Tool_Action_Trigger it will go through the action's items and call each item action.
		
		@wp_action
		@param		SD_Meeting_Tool_Action_Trigger		$trigger		Action trigger to distribute amonst the plugins.
	**/
	public function sd_mt_trigger_action( $trigger );
	
	/**
		Creates or updates an action.
		
		If the ID is null, a new action will be created in the database.
		
		@wp_filter
		@param		SD_Meeting_Tool_Action		$action		Action to create or update.
		@return		The complete action.
	**/
	public function sd_mt_update_action( $action );
	
	/**
		Updates an action item.
		
		If the item does not have an ID, a new item will be created and the ID will be returned.
		
		@wp_filter
		@param		SD_Meeting_Tool_Action_Item		$item		The item to update.
		@return		Updated SD_Meeting_Tool_Action_Item item.
	**/
	public function sd_mt_update_action_item( $item );
}
