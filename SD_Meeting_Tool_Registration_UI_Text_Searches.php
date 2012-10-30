<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool Registration UI Text Searches
Plugin URI: https://it.sverigedemokraterna.se
Description: Registration UI that works by searching for text strings.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	@page		SD_Meeting_Tool_Registration_UI_Text_Searches					Registration UI: Text Searches
	
	@section	SD_Meeting_Tool_Registration_UI_Text_Searches_introduction		Introduction
	
	The text searches registration UI registers users by searching through the list for text strings.
	
	Searching is handled using Ajax and list refreshes are automatic thanks to Ajax again.

	@section	SD_Meeting_Tool_Registration_UI_Text_Searches_requirements		Requirements
	
	- @ref index
	- @ref sd_meeting_tool_display_formats
	- @ref sd_meeting_tool_lists
	- @ref sd_meeting_tool_list_sorts
	- @ref sd_meeting_tool_participants

	@section	SD_Meeting_Tool_Registration_UI_Text_Searches_installation		Installation
	
	- Enable the plugin.
	
	The settings for this plugin, like all registration UIs, are stored in the registration data.
	
	@section	SD_Meeting_Tool_Registration_UI_Text_Searches_usage		Usage
	
	@par		Settings
	
	- @b "Ajax submit" Submit the data using ajax. If not selected will actually press the enter button, causing a page reload.
	- @b "Disabled color" Which color to fade the text input to when it is disabled.
	- @b "Latest list" Optionally displays a list underneath the select input. This is useful for the functionary to keep track of who just checked in.
	Sorting of the latest list should be already configured in the list.
	- @b "Latest count" Specify how many participants to display at a time. 
	- @b "Input list refresh" How often, in milliseconds, to refresh the input list. 
	- @b "Latest list refresh" How often, in milliseconds, to refresh the latest list.
	- @b "Enter delay" How long, in milliseconds, to keep the text input green before allowing more registrations.
	
	@par		Keyboard shortcuts
	
	The input field works as a normal input field.
	
	Use the @b cursor @b keys to navigate up and down in the participant list.
	
	Pressing @b enter will either accept what is in the input field (if there only one hit in the participant list), or whatever is selected from the participant list.
	If the input field is empty and nothing has been selected from the participant list, nothing will happen. If there is more than
	one hit in the participant list, nothing will happen when enter is pressed.
	
	Pressing @b shift-backspace will empty the input field. 
	
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/

/**
	The searching is handled in realtime and the list is refreshed via AJAX calls.
	
	Any text can be searched for, which means that things like barcodes can be searched for also.
	
	@brief		Plugin providing a registration UI that works by searching for text strings.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se

	@par		Changelog
	
	@par		1.1
	
	- Change: UUID is now an MD5, not a SHA512. It appears that inputs can't have names that long.
	- Change: Selected text row is shown instead of ID number when registration is successful.
	
**/
class SD_Meeting_Tool_Registration_UI_Text_Searches
	extends SD_Meeting_Tool_0
	implements interface_SD_Meeting_Registration_UI
{
	public function __construct()
	{
		parent::__construct( __FILE__ );

		// Internal filters
		add_filter( 'sd_mt_configure_registration_ui',					array( &$this, 'sd_mt_configure_registration_ui' ) );
		add_filter( 'sd_mt_display_registration_ui',					array( &$this, 'sd_mt_display_registration_ui' ) );
		add_filter( 'sd_mt_get_all_registration_uis',					array( &$this, 'sd_mt_get_all_registration_uis' ) );
		add_filter( 'sd_mt_get_registration_ui',						array( &$this, 'sd_mt_get_registration_ui' ) );
		add_filter( 'sd_mt_process_registration_ui',					array( &$this, 'sd_mt_process_registration_ui' ) );
		add_filter( 'sd_mt_update_registration_ui',						array( &$this, 'sd_mt_update_registration_ui' ) );

		// Ajax
		add_action( 'wp_ajax_ajax_sd_mt_registration_ui_text_searches',	array( &$this, 'ajax_sd_mt_registration_ui_text_searches') );
		
		// Misc
		wp_enqueue_script( 'jquery' );
		wp_enqueue_style( 'sd_mt_registration_ui_text_searches', '/' . $this->paths['path_from_base_directory'] . '/css/SD_Meeting_Tool_Registration_UI_Text_Searches.css', false, '1.0', 'screen' );
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Ajax
	// --------------------------------------------------------------------------------------------

	public function ajax_sd_mt_registration_ui_text_searches()
	{
		if ( ! SD_Meeting_Tool::check_admin_referrer( 'ajax_sd_mt_registration_ui_text_searches' ) )
			die();
		
		$registration = $this->filters( 'sd_mt_get_registration', $_POST['registration_id'] );
		if ( $registration === false )
			die();
		
		switch( $_POST['type'] )
		{
			case 'fetch_participants':
				$list_id = $registration->data->list_id;
				$list = $this->filters( 'sd_mt_get_list', $list_id );
				if ( $list === false )
					die();
				$list = $this->filters( 'sd_mt_list_participants', $list );
				
				$display_format_id = $list->data->display_format_id;
				$display_format = $this->filters( 'sd_mt_get_display_format', $display_format_id );

				$returnValue = array(
					'hash' => '',
					'participants' => array(),
				);
				
				foreach( $list->participants as $participant )
					$returnValue['participants'][ $participant->id ] = $this->filters( 'sd_mt_display_participant', $participant, $display_format );
				
				$returnValue['hash'] = $this->hash( serialize( $returnValue['participants'] ) );
				
				if ( $returnValue['hash'] == $_POST['list_hash'] )
					$returnValue['participants'] = array();
				echo json_encode( $returnValue );
				break;
			case 'fetch_latest_list':
				$ui = $registration->data->ui;
				
				if ( $ui->data->latest_list_count < 1 )
					die();

				$latest_list = $ui->data->latest_list;
				if ( $latest_list < 1 )
					die();
				
				$list = $this->filters( 'sd_mt_get_list', $latest_list );
				if ( $list === false )
					die();
				
				$list = $this->filters( 'sd_mt_list_participants', $list );
				$participants = $list->participants;
				
				$display_format = $this->filters( 'sd_mt_get_display_format', $list->data->display_format_id );
				
				// We want the first xx amount
				$participants = array_splice( $participants, 0, $ui->data->latest_list_count );
				if ( count( $participants ) < 1 )
					die();
				
				$t_body = '';
				foreach( $participants as $participant )
				{
					$time = date('Y-m-d H:i:s', $participant->registered );
					$display_name = $this->filters( 'sd_mt_display_participant', $participant, $display_format );
					$t_body .= '
						<tr>
							<td>' . $display_name . '</td>
							<td>' . $time . '</td>
						</tr>
					';
				}
				$returnValue = '
					<table class="widefat">
						<thead>
							<tr>
								<th>' . $this->_('Name') . '</th>
								<th>' . $this->_('Registered') . '</th>
							</tr>
						</thead>
						<tbody>
							'.$t_body.'
						</tbody>
					</table>
				';
				echo $returnValue;
				break;
			case 'submit_registration':
				$this->filters( 'sd_mt_process_registration', $registration );
				break;
		}
		die();
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------

	public function sd_mt_configure_registration_ui( $SD_Meeting_Tool_Registration_UI )
	{
		// Not a text search? Don't bother.
		if ( ! is_a( $SD_Meeting_Tool_Registration_UI, 'SD_Meeting_Tool_Registration_UI_Text_Search' ) )
			return $SD_Meeting_Tool_Registration_UI;

		$this->load_language();

		$uuid = $SD_Meeting_Tool_Registration_UI->data->uuid;		// Convenience
		$form = $this->form();
		$nameprefix = '[' . $uuid . ']';
		$updated = false;
		
		$lists = $this->filters( 'sd_mt_get_all_lists', array() );
		$lists_as_options = array();
		foreach( $lists as $list )
			$lists_as_options[ $list->id ] = $list->data->name;

		$inputs = array();
		
		$inputs['general'] = array(
			'uuid' => array(
				'name' => 'uuid',
				'nameprefix' => $nameprefix,
				'type' => 'hidden',
				'value' => $uuid,
			),
			'ajax_submit' => array(
				'name' => 'ajax_submit',
				'nameprefix' => $nameprefix,
				'type' => 'checkbox',
				'checked' => $SD_Meeting_Tool_Registration_UI->data->ajax_submit,
				'label' => $this->_( 'Ajax submit' ),
				'description' => $this->_( 'Use AJAX to submit the registration.' ),
			),
			'disabled_color' => array(
				'name' => 'disabled_color',
				'nameprefix' => $nameprefix,
				'type' => 'text',
				'value' => $SD_Meeting_Tool_Registration_UI->data->disabled_color,
				'label' => $this->_( 'Disabled color' ),
				'description' => $this->_( 'CSS color to fade to when the input is disabled. Default is <em>#999</em>.' ),
				'size' => 30,
			),
			'latest_list' => array(
				'name' => 'latest_list',
				'nameprefix' => $nameprefix,
				'type' => 'select',
				'value' => intval( $SD_Meeting_Tool_Registration_UI->data->latest_list ),
				'label' => $this->_( 'Latest list' ),
				'description' => $this->_( 'Show the first %s registrations of a specified list. Can be used to show the latest registrations.', $SD_Meeting_Tool_Registration_UI->data->latest_list_count ),
				'options' => array( '' => $this->_('Do not show a list') ) + $lists_as_options,
			),
			'latest_list_count' => array(
				'name' => 'latest_list_count',
				'nameprefix' => $nameprefix,
				'type' => 'text',
				'value' => $SD_Meeting_Tool_Registration_UI->data->latest_list_count,
				'label' => $this->_( 'Latest count' ),
				'description' => $this->_( 'How many in the latest list to display.' ),
				'size' => 3,
			),
		);
		
		$inputs['timing'] = array(
			'input_list_refresh' => array(
				'name' => 'input_list_refresh',
				'nameprefix' => $nameprefix,
				'type' => 'text',
				'value' => intval( $SD_Meeting_Tool_Registration_UI->data->input_list_refresh ),
				'label' => $this->_( 'Input list refresh' ),
				'description' => $this->_( 'How many milliseconds to wait between input list refreshes. Default is 5000, minimum is 500.' ),
				'size' => 4,
			),
			'latest_list_refresh' => array(
				'name' => 'latest_list_refresh',
				'nameprefix' => $nameprefix,
				'type' => 'text',
				'value' => intval( $SD_Meeting_Tool_Registration_UI->data->latest_list_refresh ),
				'label' => $this->_( 'Latest list refresh' ),
				'description' => $this->_( 'How many milliseconds to wait between latest list refreshes. Default is 5000, minimum is 500.' ),
				'size' => 4,
			),
			'enter_delay' => array(
				'name' => 'enter_delay',
				'nameprefix' => $nameprefix,
				'type' => 'text',
				'value' => intval( $SD_Meeting_Tool_Registration_UI->data->enter_delay ),
				'label' => $this->_( 'Enter delay' ),
				'description' => $this->_( 'How many milliseconds to wait after a participant is successfully entered. Default is 1000.' ),
				'size' => 4,
			),
		);
		
		$returnValue = '';
		$returnValue .= '<h4>' . $this->_('General settings') . '</h4>';
		$returnValue .= $this->display_form_table( $inputs['general'] );
		$returnValue .= '<h4>' . $this->_('Timing settings') . '</h4>';
		$returnValue .= $this->display_form_table( $inputs['timing'] );
		
		return $returnValue;
	}

	public function sd_mt_get_all_registration_uis( $SD_Meeting_Tool_Registration_UIs )
	{
		$SD_Meeting_Tool_Registration_UIs[] = $this->our_ui();
		return $SD_Meeting_Tool_Registration_UIs;
	}

	public function sd_mt_get_registration_ui( $class_name )
	{
		if ( $class_name != 'SD_Meeting_Tool_Registration_UI_Text_Search' )
			return $class_name;
		return $this->our_ui();
	}
	
	public function sd_mt_process_registration_ui( $SD_Meeting_Tool_Registration )
	{
		$ui = $SD_Meeting_Tool_Registration->get_ui();
		// The UI must be of a type we handle.
		if ( get_class($ui) != 'SD_Meeting_Tool_Registration_UI_Text_Search' )
			return $SD_Meeting_Tool_Registration;
			
		if ( !isset( $_POST['uuid'] ) )
			return $SD_Meeting_Tool_Registration;
		
		if ( $_POST['uuid'] != $ui->data->uuid )
			return $SD_Meeting_Tool_Registration;
		
		// Does the participant exist?
		$participant_id = intval( $_POST['text'] );
		$participant = $this->filters( 'sd_mt_get_participant', $participant_id );
		if ( ! is_object( $participant ) )
			return $SD_Meeting_Tool_Registration;
		
		$list_id = $SD_Meeting_Tool_Registration->data->list_id;
		$list = $this->filters( 'sd_mt_get_list', $list_id );
		if ( $list === false )
			return $SD_Meeting_Tool_Registration;
		$list = $this->filters( 'sd_mt_list_participants', $list );
		
		$exists = false;
		foreach( $list->participants as $participant )
			if ( $participant->id == $participant_id )
			{
				$exists = true;
				break;
			}
		
		$result = new SD_Meeting_Tool_Registration_Result();
		$result->set_participant_id( $participant_id );
		if ( $exists )
			$result->success();
		
		$SD_Meeting_Tool_Registration->result = $result;
		
		return $SD_Meeting_Tool_Registration;
	}

	public function sd_mt_display_registration_ui( $SD_Meeting_Tool_Registration )
	{
		$ui = $SD_Meeting_Tool_Registration->get_ui();
		// The UI must be of a type we handle.
		if ( get_class($ui) != 'SD_Meeting_Tool_Registration_UI_Text_Search' )
			return $SD_Meeting_Tool_Registration;
		
		$this->load_language();

		$uuid = $ui->data->uuid;			// Conv
		
		$form = $this->form();
		
		$inputs = array(
			'text' => array(
				'type' => 'text',
				'name' => 'text',
				'size' => 50,
				'maxlength' => 512,
				'label' => $this->_( 'Participant' ),
			),
			'participant_lookup' => array(
				'type' => 'select',
				'name' => 'participant_lookup',
				'size' => 10,
				'label' => $this->_( 'All participants' ),
				'options' => array(),
				'css_style' => 'height: auto;',
			),
			'registration_id' => array(
				'type' => 'hidden',
				'name' => 'registration_id',
				'value' => $SD_Meeting_Tool_Registration->id,
			),
			'uuid' => array(
				'type' => 'hidden',
				'name' => 'uuid',
				'value' => $uuid,
			),
		);
		
		$display_latest_list = ( $ui->data->latest_list > 0 ) && ( $ui->data->latest_list_count > 0 ); 
		$latest_list_div = $display_latest_list ? '<div class="latest_list"></div>' : '';
		
		$returnValue = '<div class="ui_text_searches ui_text_searches_' . $uuid . '">';
		$returnValue .= $form->start();
		
		$returnValue .= '
			' . $form->make_input($inputs['registration_id']) . '
			' . $form->make_input($inputs['uuid']) . '
			
			<div class="text">
				<div class="label">
					' . $form->make_label($inputs['text']) . '
				</div>
				<div class="input">
					' . $form->make_input($inputs['text']) . '
				</div>
			</div>

			<div class="participant_lookup">
				<div class="label">
					' . $form->make_label($inputs['participant_lookup']) . '
				</div>
				<div class="input">
					' . $form->make_input($inputs['participant_lookup']) . '
				</div>
			</div>
			
			' . $latest_list_div . '

			<script type="text/javascript" src="'. $this->paths['url'] . '/js/sd_meeting_tool_registration_ui_text_searches.js' .'"></script>
			<script type="text/javascript">
				settings = {
				};
				jQuery(document).ready(function($){ sd_meeting_tool_registration_ui_text_searches.init(
				{
					"action" : "ajax_sd_mt_registration_ui_text_searches", 
					"ajaxnonce" : "' . wp_create_nonce( 'ajax_sd_mt_registration_ui_text_searches' ) . '",
					"ajaxurl" : "'. admin_url('admin-ajax.php') . '",
					"registration_id" : "'. $SD_Meeting_Tool_Registration->id . '",
					"uuid" : "' . $uuid . '",
				},
				{
					"ajax_submit" : "' . $ui->data->ajax_submit. '",
					"disabled_color" : "' . $ui->data->disabled_color. '",
					"enter_delay" : "' . $ui->data->enter_delay. '",
					"input_list_refresh" : "' . max( 1000, $ui->data->input_list_refresh ) . '",
					"latest_list_refresh" : "' . max( 1000, $ui->data->latest_list_refresh ). '"
				} ); });
			</script>
		';
		$returnValue .= $form->stop() . '</div>';
		
		return $returnValue;;
	}

	public function sd_mt_update_registration_ui( $SD_Meeting_Tool_Registration_UI )
	{
		// The UI must be of a type we handle.
		if ( get_class($SD_Meeting_Tool_Registration_UI) != 'SD_Meeting_Tool_Registration_UI_Text_Search' )
			return $SD_Meeting_Tool_Registration_UI;
		
		// And we must be updated exactly the specific Text Search UI that was in the post.
		$uuid = $SD_Meeting_Tool_Registration_UI->data->uuid;		// Convenience
		if ( !isset( $_POST[ $uuid ] ) )
			return $SD_Meeting_Tool_Registration_UI;
		
		$post = $_POST[ $uuid ];									// Convenience

		$SD_Meeting_Tool_Registration_UI->data->ajax_submit = isset($post[ 'ajax_submit' ]);
		$SD_Meeting_Tool_Registration_UI->data->disabled_color = $post[ 'disabled_color' ];
		$SD_Meeting_Tool_Registration_UI->data->enter_delay = intval($post[ 'enter_delay' ]);
		$SD_Meeting_Tool_Registration_UI->data->input_list_refresh = max( 1000, intval( $post[ 'input_list_refresh' ] ) );
		$SD_Meeting_Tool_Registration_UI->data->latest_list = intval( $post[ 'latest_list' ] );
		$SD_Meeting_Tool_Registration_UI->data->latest_list_count = intval( $post[ 'latest_list_count' ] );
		$SD_Meeting_Tool_Registration_UI->data->latest_list_refresh = max( 1000, intval( $post[ 'latest_list_refresh' ] ) );
		
		return $SD_Meeting_Tool_Registration_UI;
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------
	
	/**
		Returns a base Text Search UI class.

		@return		An empty, simple SD_Meeting_Tool_Registration_UI_Text_Search.
	**/
	private function our_ui()
	{
		$ui = new SD_Meeting_Tool_Registration_UI_Text_Search();
		$ui->name = $this->_('Text Searches');
		$ui->version = '1.0';
		return $ui;
	}
	
}
$SD_Meeting_Tool_Registration_UI_Text_Searches = new SD_Meeting_Tool_Registration_UI_Text_Searches();

// --------------------------------------------------------------------------------------------
// ----------------------------------------- class SD_Meeting_Tool_Registration_UI
// --------------------------------------------------------------------------------------------

/**
	@brief		Text Search registration UI 
	@see		SD_Meeting_Tool_Registration
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Registration_UI_Text_Search
	extends SD_Meeting_Tool_Registration_UI
{
	public function __construct()
	{
		parent::__construct();
		$this->name = 'Text Search';
		$this->data->version = '1.0';
		$this->data->uuid = md5( rand(0, PHP_INT_MAX ) );
		$this->data->ajax_submit = true;
		$this->data->disabled_color = '#999';
		$this->data->enter_delay = 1000;
		$this->data->input_list_refresh = 5000;
		$this->data->latest_list = false;
		$this->data->latest_list_refresh = 5000;
		$this->data->latest_list_count = 10;
		$this->data->updated = 0;
	}
}
