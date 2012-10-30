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
		@param		SD_Meeting_Tool_Registration		$reg		Registration to delete.
	**/
 	public function sd_mt_delete_registration( $reg );
 	
	/**
		Displays a registration + ui.
		
		@wp_filter
		@param		SD_Meeting_Tool_Registration		$reg		Registration to show.
	**/
 	public function sd_mt_display_registration( $reg );

	/**
		Returns an array of SD_Meeting_Tool_Registration.
		
		@wp_filter
		@return		Array of SD_Meeting_Tool_Registration.
	**/
	public function sd_mt_get_all_registrations();

	/**
		Returns a registration.
		
		@wp_filter
		@param		int									$id			ID of a registration.
		@return		SD_Meeting_Tool_Registration or false.
	**/
	public function sd_mt_get_registration( $id );

	/**
		Processes any _POST input from a registration.
		
		@wp_filter
		@param		SD_Meeting_Tool_Registration		$reg		Registration to process.
	**/
 	public function sd_mt_process_registration( $reg );

	/**
		Creates or updates a registration.
		
		If the ID is null, a new registration will be created in the database.
		
		@wp_filter
		@param		SD_Meeting_Tool_Registration		$reg		Registration to create or update.
		@return		The complete registration.
	**/
 	public function sd_mt_update_registration( $reg );
}
