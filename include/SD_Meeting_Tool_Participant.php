<?php
/**
	In addition to $id and $registered, the class will contain several other fields as dictated by which fields
	exist in the participant database.
	
	@brief		Meeting participant. 
	@see		SD_Meeting_Tool_Lists
	@see		SD_Meeting_Tool_Participants
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Participant
{
	/**
		Unique ID of this participant.
		@var	$id
	**/
	public $id;
	
	/**
		Handled and inserted by SD_Meeting_Tool_Lists.
		@var	$registered;
		@see	SD_Meeting_Tool_List_Participant::$registered
	**/
	public $registered;
	
	/**
		Serialized data.
		@var	$data
	**/
	public $data;
}
