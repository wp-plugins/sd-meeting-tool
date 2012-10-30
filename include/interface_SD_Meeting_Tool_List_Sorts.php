<?php
/**
	A list sort is as the name implies: a means of sorting lists of participants according to their fields.
	
	A list sort plugin stores the various SD_Meeting_Tool_List_Sort and provides sorting services to the other plugins.

	@brief		Handle list sorting.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
interface interface_SD_Meeting_Tool_List_Sorts
{
	/**
		Deletes a sort.
		
		@wp_filter
		@param		SD_Meeting_Tool_List_Sort		$sort		List sort order to delete.
	**/
	public function sd_mt_delete_list_sort( $sort );
	
	/**
		Return an array of all available list sorts.
		
		@wp_filter
		@return		Array of SD_Meeting_Tool_List_Sort objects.
	**/
	public function sd_mt_get_all_list_sorts();
	
	/**
		Return a list sort.
		
		@wp_filter
		@param		integer							$id			List sort order ID to get.
		@return		SD_Meeting_Tool_list_sort, or false if it doesn't exist. 
	**/
	public function sd_mt_get_list_sort( $id );
	
	/**
		Sorts a list given a specific List Sort.
		
		@wp_filter
		@param		SD_Meeting_Tool_List			$list		List to sort.
		@param		SD_Meeting_Tool_List_Sort		$sort		$SD_Meeting_Tool_List_Sort
		@return		The sorted SD_Meeting_Tool_List.
	**/
	public function sd_mt_sort_list( $list, $sort );
	
	/**
		Creates or updates a list sort.
		
		If the ID is null, a new list sort will be created in the database.
		
		@wp_filter
		@param		SD_Meeting_Tool_List_Sort		$sort		List sort to create or update.
		@return		The complete list sort.
	**/
	public function sd_mt_update_list_sort( $sort );
}
