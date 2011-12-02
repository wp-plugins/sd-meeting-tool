<?php
/**
	@brief		Which field to sort by, and in which direction. 
	@see		SD_Meeting_Tool_List_Sort
	@see		SD_Meeting_Tool_List_Sorts
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_List_Sort_Order
{
	/**
		$SD_Meeting_Tool_Participant_Field to use as key.
		@var	$field
	**/
	public $field;
	
	/**
		Sort the list ascending?
		@var	$ascending
	**/
	public $ascending = true;
	
	/**
		A new list sort, using field as the key and order by ascending.
		@param		$SD_Meeting_Tool_Participant_Field		Field to use as key.
		@param		$ascending								Sort ascendingly?
	**/
	public function __construct( $SD_Meeting_Tool_Participant_Field, $ascending = true )
	{
		$this->field = $SD_Meeting_Tool_Participant_Field;
		$this->ascending = $ascending;
	}
}
