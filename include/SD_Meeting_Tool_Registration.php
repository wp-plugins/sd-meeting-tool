<?php
/**
	The container contains info about the registration.
	
	@brief		Container for a registration.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Registration
{
	/**
		ID of the registration.
		
		@var	$id
	**/
	public $id;
	
	/**
		Various data as a stdClass.
		
		Currently, the data has:
		- @b action_failure		Which action to execute on failure.
		- @b action_success		Which action to execute on success.
		- @b list_id			List from which to retrieve data.
		- @b name				Name of registration. About 200 chars.
		- @b ui					Which registration user interface to use.

		@var	$data
	**/
	public $data;
	
	/**
		Result of this registration as a SD_Meeting_Tool_Registration_Result.
		
		This value is given to us by our UI, so that we can decide what to do.
		
		@var	$result
	**/
	public $result;
	
	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->action_failure = null;
		$this->data->action_success = null;
		$this->data->list_id = null;
		$this->data->name = '';
		$this->data->ui = null;
	}
	
	/**
		Returns the UI set, if any.
		
		@return		The SD_Meeting_Tool_Registration_UI or null.
	**/
	public function get_ui()
	{
		return $this->data->ui;
	}
	
}

/**
	Contained in a SD_Meeting_Tool_Registration and filled in by a UI, the class helps the registration plugin
	decide what action to take, and with which participant.
	
	The participant ID can be successfully processed (ie: was allowed to register)
	or a failure (ie: was @b not allowed to register).
	
	Failures without participants should not be processed at all and ignored completely, since there's no
	useful information in a failed registration without a participant even @b trying to register.
	
	This class uses methods and private variables more than the other classes because we don't have
	public filters and actions to adhere to, and can therefore be more strict.
	
	@brief		Information about how the registration went.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Registration_Result
{
	/**
		Was this registration a complete success?
		@var	$success
	**/
	private $success = false;
	
	/**
		Participant ID that was processed.
		@var	$participant_id
	**/
	private $participant_id = null;
	
	/**
		Marks this result as successful.
		
		Result can then be queried via ->successful()
	**/
	public function success()
	{
		$this->success = true;
	}
	
	/**
		Queries whether the registration was a success.
		@return		Whether the registration was a success.
	**/
	public function successful()
	{
		return $this->success === true;
	}
	
	/**
		Returns the participant ID.
		@return		Participant ID that was processed.
	**/
	public function participant_id()
	{
		return $this->participant_id;
	}
	
	/**
		Sets the participant ID that was processed.
		@param		$participant_id		New participant ID.
	**/
	public function set_participant_id( $participant_id )
	{
		$this->participant_id = $participant_id;
	}
	
	/**
		Does this result have a participant ID set?
		@return		True, if the result has a participant ID set.
	**/
	public function has_participant_id()
	{
		return $this->participant_id !== null;
	}
}