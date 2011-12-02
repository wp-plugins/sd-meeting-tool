<?php
/**
	After being created, SD_Meeting_Tool_Actions will then pass a smaller SD_Meeting_Tool_Action_Item_Trigger around the
	plugins.
	
	@brief		Contains what action was triggered and by what.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
	@see		SD_Meeting_Tool_Action
	@see		SD_Meeting_Tool_Action_Item_Trigger
**/
class SD_Meeting_Tool_Action_Trigger
{
	/**
		The SD_Meeting_Tool_Action that was triggered.
		@var	$action
	**/
	public $action;
	
	/**
		What triggered this action?
		
		Will most probably be a SD_Meeting_Tool_Participant.
		
		@var	$trigger
	**/
	public $trigger;
}
