<?php
/**
	@brief		A specific talking point on an SD_Meeting_Tool_Agenda.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
	@see		interface_SD_Meeting_Tool_Actions
	@see		SD_Meeting_Tool_Agenda_Item
**/
class SD_Meeting_Tool_Agenda_Item
{
	/**
		Int ID of item.
		@var	$id
	**/
	public $id;

	/**
		Order of item in the agenda.
		
		Default is 1. Higher means later.
		
		@var	$order
	**/
	public $order;
	
	/**
		Various data in a stdClass.
		
		Currently stores
		
		- @b name Name of the agenda item.
		- @b link Optional url the agenda item points to.
		@var	$data;
	**/
	public $data;
	
	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->name = date('Y-m-d H:i:s');
		$this->data->link = '';
	}
}	
