<?php
/**
	Lists are the basic communication builing blocks for inter-plugin communication and actions.
	
	Participants are placed in lists and the lists are then used to decide: who gets to register, who has registered,
	who gets to vote, etc.
	 
	@brief		Provides services for handling {SD_Meeting_Tool_List}s.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
interface interface_SD_Meeting_Tool_Lists
{
	/**
		Add a participant to a list.
		
		@wp_filter
		@param		SD_Meeting_Tool_List			$list				List to add to.
		@param		SD_Meeting_Tool_Participant		$participant		A SD_Meeting_Tool_Participant or participant id (int) to add to the list.
	**/
	public function sd_mt_add_list_participant( $list, $participant );
	
	/**
		Adds an array of participants to a list.
		
		@wp_filter
		@param		SD_Meeting_Tool_List		$SD_Meeting_Tool_List				List to add to.
		@param		array						$SD_Meeting_Tool_Participants		An array of SD_Meeting_Tool_Participant or participant ids (int) to add to the list.
	**/
	public function sd_mt_add_list_participants( $SD_Meeting_Tool_List, $SD_Meeting_Tool_Participants );
	
	/**
		Clones a list completely.
		
		@wp_filter
		@param		SD_Meeting_Tool_List		$list			An existing list to clone.
		@return		New list.
	**/
	public function sd_mt_clone_list( $list );
	
	/**
		Delete a list.
		
		@wp_filter
		@param		SD_Meeting_Tool_List		$list			List object to delete.
	**/
	public function sd_mt_delete_list( $list );
	
	/**
		Return an array of all available lists.
		
		@wp_filter
		@return											Array of SD_Meeting_Tool_List objects.
	**/
	public function sd_mt_get_all_lists();
	
	/**
		Return a list.
		
		@wp_filter
		@param		int		$list_id				List ID to get.
		@return		Returned SD_Meeting_Tool_List, or false if it doesn't exist. 
	**/
	public function sd_mt_get_list( $list_id );
	
	/**
		Remove a participant from a list.
		
		@wp_filter
		@param		SD_Meeting_Tool_List			$list				List to remove from.
		@param		SD_Meeting_Tool_Participant		$participant		Participant to remove.
	**/
	public function sd_mt_remove_list_participant( $list, $participant );

	/**
		Remove all participants from a list.
		
		@wp_filter
		@param		SD_Meeting_Tool_List			$list				List to remove from.
	**/
	public function sd_mt_remove_list_participants( $list );

	/**
		Lists the participants in a list.
		
		Assembles the list from all the includes and excludes.
		
		@wp_filter
		@param		SD_Meeting_Tool_List			$list				List to assemble.
		@return		Array of SD_Meeting_Tool_Participant objects.
	**/
	public function sd_mt_list_participants( $list );
	
	/**
		Creates or updates a list.
		
		If the ID is null, a new list will be created in the database.
		
		@wp_filter
		@param		SD_Meeting_Tool_List			$list				List to create or update.
		@return		The complete list.
	**/
	public function sd_mt_update_list( $list );
}