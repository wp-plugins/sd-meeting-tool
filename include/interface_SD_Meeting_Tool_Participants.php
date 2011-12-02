<?php
/**
	@brief		Provides participant handling.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
interface interface_SD_Meeting_Tool_Participants
{
	/**
		Deletes a participant.
		
		@wp_filter
		@param		$SD_Meeting_Tool_Participant		Participant to delete.
	**/
	public function sd_mt_delete_participant( $SD_Meeting_Tool_Participant );
	
	/**
		Return a list of all the participants.
		
		@return		Array of SD_Meeting_Tool_Participant.
	**/
	public function sd_mt_get_all_participants();
	
	/**
		Return a specific participant.
		
		@wp_filter
		@param		$participant_id					Participant to get.
		@return		An SD_Meeting_Tool_Participant.
	**/
	public function sd_mt_get_participant( $participant_id );
	
	/**
		Return a specific participant field.
		
		@wp_filter
		@param		$field_name								Participant field name to get.
		@return		An SD_Meeting_Tool_Participant_Field.
	**/
	public function sd_mt_get_participant_field( $field_name );
	
	/**
		Returns an array of SD_Meeting_Tool_Participant_Field that describe which fields exist in the participant database.
		
		@wp_filter
		@return		Array of SD_Meeting_Tool_Participant_Field.
	**/
	public function sd_mt_get_participant_fields();
	
	/**
		Creates or updates a participant.
		
		If the ID is null, a new participant will be created in the database.
		
		@wp_filter
		@param		$SD_Meeting_Tool_Participant		Participant to create or update.
		@return											The complete participant.
	**/
	public function sd_mt_update_participant( $SD_Meeting_Tool_Participant );
}
