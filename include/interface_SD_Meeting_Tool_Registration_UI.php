<?php
/**
	@brief		Registration User Interface
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
interface interface_SD_Meeting_Registration_UI
{
	/**
		Configure a UI.
		
		This filter is called by the registration plguin, requesting the UI to display its configuration options, if any.
		
		The form is already started by the registration plugin. Return anything you want: a string, some form inputs.
		
		@param		$SD_Meeting_Tool_Registration_UI		A SD_Meeting_Tool_Registration_UI.
		@return		The necessary inputs to configure the UI.
	**/
	public function sd_mt_configure_registration_ui( $SD_Meeting_Tool_Registration_UI );

	/**
		Displays the registration UI.
		
		Extract the UI from the registration using registration->ui.
		
		@wp_filter
		@param		$SD_Meeting_Tool_Registration		The SD_Meeting_Tool_Registration contains the UI to show to the user.
		@return		A string containing all necessary HTML for display to the user.
	**/
	public function sd_mt_display_registration_ui( $SD_Meeting_Tool_Registration );

	/**
		Return a list of available reg UIs.
		
		@wp_filter
		@param		$SD_Meeting_Tool_Registration_UIs		Array of SD_Meeting_Tool_Registration_UI.
	**/
	public function sd_mt_get_all_registration_uis( $SD_Meeting_Tool_Registration_UIs );

	/**
		Return a specific UI.
		
		@wp_filter
		@param		$class_name								Class name of the requested UI.
	**/
	public function sd_mt_get_registration_ui( $class_name );

	/**
		Processes any _POST input from this registration UI.
		
		@wp_filter
		@param		$SD_Meeting_Tool_Registration		Registration to process.
	**/
 	public function sd_mt_process_registration_ui( $SD_Meeting_Tool_Registration );

	/**
		Creates or updates a registration UI.
		
		If the ID is null, a new registration UI will be created in the database.
		
		@wp_filter
		@param		$SD_Meeting_Tool_Registration_UI	Registration UI to create or update.
		@return											The complete registration UI.
	**/
	public function sd_mt_update_registration_ui( $SD_Meeting_Tool_Registration_UI );
}
