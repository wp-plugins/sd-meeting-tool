<?php
/**
	@brief		Provides participant handling.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
interface interface_SD_Meeting_Tool_Participants
{
	/**
		@brief		Completely clears the participants.
		
		Deletes all participants and resets the fields. 
		
		@wp_filter
	**/
	public function sd_mt_clear_participants();
	
	/**
		@brief		Deletes a participant.
		
		@wp_filter
		@param		SD_Meeting_Tool_Participant		$participant		Participant to delete.
	**/
	public function sd_mt_delete_participant( $participant );
	
	/**
		@brief		Return a list of all the participants.
		
		@return		Array of SD_Meeting_Tool_Participant.
	**/
	public function sd_mt_get_all_participants();
	
	/**
		@brief		Return a specific participant.
		
		@wp_filter
		@param		int								$id					Participant to get.
		@return		An SD_Meeting_Tool_Participant.
	**/
	public function sd_mt_get_participant( $id );
	
	/**
		@brief		Return a specific participant field.
		
		@wp_filter
		@param		string							$field_name			Participant field name to get.
		@return		An SD_Meeting_Tool_Participant_Field.
	**/
	public function sd_mt_get_participant_field( $field_name );
	
	/**
		@brief		Returns an array of SD_Meeting_Tool_Participant_Field that describe which fields exist in the participant database.
		
		@wp_filter
		@return		Array of SD_Meeting_Tool_Participant_Field.
	**/
	public function sd_mt_get_participant_fields();
	
	/**
		@brief		Creates or updates a participant.
		
		If the ID is null, a new participant will be created in the database.
		
		@wp_filter
		@param		SD_Meeting_Tool_Participant		$participant		Participant to create or update.
	**/
	public function sd_mt_update_participant( $participant );
}
