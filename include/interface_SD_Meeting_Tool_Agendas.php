<?php
/**
	Provides storage and editing services for {SD_Meeting_Tool_Agenda}s and the subsequent {SD_Meeting_Tool_Agenda_Item}s.
	
	@brief		Interface for Agenda plugins.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
interface interface_SD_Meeting_Tool_Agendas
{
	/**
		Delete an agenda.
		
		@wp_filter
		@param		SD_Meeting_Tool_Agenda			$agenda		Agenda object to delete.
	**/
	public function sd_mt_delete_agenda( $agenda );
	
	/**
		Return a specific agenda.
		
		@wp_filter
		@param		int								$id			ID of agenda to get.
		@return		SD_Meeting_Tool_Agenda.
	**/
	public function sd_mt_get_agenda( $id );
	
	/**
		Return a list of all agendas.
		
		They are sorted by name.
		
		@wp_filter
		@return		Array of SD_Meeting_Tool_Agenda.
	**/
	public function sd_mt_get_all_agendas();
	
	/**
		Creates or updates an agenda.
		
		If the ID is null, a new agenda will be created in the database.
		
		@wp_filter
		@param		SD_Meeting_Tool_Agenda			$agenda		Agenda to create or update.
		@return		The complete agenda.
	**/
	public function sd_mt_update_agenda( $agenda );
	
	/**
		Updates a specific agenda item.
		
		@wp_filter
		@param		SD_Meeting_Tool_Agenda_Item		$item		The item to update.
		@return		Updated item.
	**/
	public function sd_mt_update_agenda_item( $item );
}
