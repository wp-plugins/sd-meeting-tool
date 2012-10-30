<?php
/**
	When a plugin needs to display a participant to the admin, it can ask the Display Format plugin to
	display the participant in the way the admin chooses.
	
	@brief		Handles how a participant is displayed internally.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
interface interface_SD_Meeting_Tool_Display_Formats
{
	/**
		Delete a display format.
		
		@wp_filter
		@param		SD_Meeting_Tool_Display_Format		$format				Display format to delete.
	**/
 	public function sd_mt_delete_display_format( $format );
 	
 	/**
 		Displays a participant using a specific display format.
 		
		@wp_filter
		@param		SD_Meeting_Tool_Participant			$participant		Participant to display.
		@param		SD_Meeting_Tool_Display_Format		$format				Display format to use.
		@return		Participant as a string.
 	**/
 	public function sd_mt_display_participant( $participant, $format );
 	
	/**
		Returns an array of SD_Meeting_Tool_Display_Format that decide how to display the participants.
		
		@wp_filter
		@return		Array of SD_Meeting_Tool_Display_Format.
	**/
	public function sd_mt_get_all_display_formats();

	/**
		Returns a display format.
		
		@wp_filter
		@param		int									$id					ID of a display format.
		@return		SD_Meeting_Tool_Display_Format or false.
	**/
	public function sd_mt_get_display_format( $id );

	/**
		Creates or updates a display format.
		
		If the ID is null, a new display format will be created in the database.
		
		@wp_filter
		@param		SD_Meeting_Tool_Display_Format		$format		Display format to create or update.
		@return											The complete display format.
	**/
	public function sd_mt_update_display_format( $format );
}

