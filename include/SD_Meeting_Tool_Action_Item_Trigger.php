<?php
/**
	The trigger is inserted from the original SD_Meeting_Tool_Action_Trigger.
	
	The trigger can be anything, mattering only to the plugin that handles this Action Item type.
	Mostly, the trigger will be a SD_Meeting_Tool_Participant.
	
	This object is passed between the plugins, hoping that some plugin will handle it.
	
	@brief		Contains data about how the action item was triggered.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
	@see		SD_Meeting_Tool_Action
**/
class SD_Meeting_Tool_Action_Item_Trigger
{
	/**
		The SD_Meeting_Tool_Action_Item to be triggered.
		@var	$action_item;
	**/
	public $action_item;
	
	/**
		What triggered this action? Mostly a SD_Meeting_Tool_Participant.
		@var	$trigger
	**/
	public $trigger;
}