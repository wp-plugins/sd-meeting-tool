<?php
/**
	A registration modifies list participants, using a registration UI and some actions.
	
	@brief		Provides participant registration services.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
interface interface_SD_Meeting_Tool_Registrations
{
	/**
		Delete a registration.
		
		@wp_filter
		@param		$SD_Meeting_Tool_Registration		Registration to delete.
	**/
 	public function sd_mt_delete_registration( $SD_Meeting_Tool_Registration );
 	
	/**
		Displays a registration + ui.
		
		@wp_filter
		@param		$SD_Meeting_Tool_Registration		Registration to show.
	**/
 	public function sd_mt_display_registration( $SD_Meeting_Tool_Registration );

	/**
		Returns an array of SD_Meeting_Tool_Registration.
		
		@wp_filter
		@return		Array of SD_Meeting_Tool_Registration.
	**/
	public function sd_mt_get_all_registrations();

	/**
		Returns a registration.
		
		@wp_filter
		@param		$registration_id				ID of a registration.
		@return		SD_Meeting_Tool_Registration or false.
	**/
	public function sd_mt_get_registration( $registration_id );

	/**
		Processes any _POST input from a registration.
		
		@wp_filter
		@param		$SD_Meeting_Tool_Registration		Registration to process.
	**/
 	public function sd_mt_process_registration( $SD_Meeting_Tool_Registration );

	/**
		Creates or updates a registration.
		
		If the ID is null, a new registration will be created in the database.
		
		@wp_filter
		@param		$SD_Meeting_Tool_Registration		Registration to create or update.
		@return											The complete registration.
	**/
 	public function sd_mt_update_registration( $SD_Meeting_Tool_Registration );
}
