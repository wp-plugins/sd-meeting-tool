<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool Speakers
Plugin URI: https://it.sverigedemokraterna.se
Description: Provides speakers for each agenda item.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	@page		sd_meeting_tool_speakers					Plugin: Speakers
	
	@section	sd_meeting_tool_speakers_description		Description
	
	Speakers extends agenda items with speakers. The speakers can be queued and moved up and down, speaking time can be set
	and the current speaker and speaker list can be displayed to visitors using shortcodes.
	
	Speakers can reply to other speakers in the form of replies. Replies can be nested virtually without limit.
	
	@section	sd_meeting_tool_speakers_requirements		Requirements
	
	- @ref index
	- @ref SD_Meeting_Tool_Agendas
	- @ref SD_Meeting_Tool_Participants
	
	@section	sd_meeting_tool_speakers_installation		Installation
	
	Enable the plugin in Wordpress.
	
	The plugin will then create the following database tables:
	
	- @b prefix_sd_mt_speakers
		Speakers that belong to speaker lists.
	- @b prefix_sd_mt_speaker_displays
		Display templates for speaker shortcodes.
	- @b prefix_sd_mt_speaker_lists
		Serialized speaker lists.

	@subsection	sd_meeting_tool_speakers_installation_css	CSS
	
	Shortcodes can display the current speaker list and speaker to visitors.
	
<pre>/**
	We're not interested in who has spoken, nor who is speaking, since another
	shortcode shows who is speaking somewhere else.
\*\*\/
.current_speaker_list li.has_spoken,
.current_speaker_list li.speaking
{
    display: none;
}

/**
	When replying, the parents should not be hidden from view.
	Therefore, javascript inserts the child_is_speaking class.
\*\*\/
.current_speaker_list li.child_is_speaking,
.current_speaker_list li.child_is_speaking li.speaking
{
	display: list-item;
} 

/**
	Whoever is speaking deserves a nice background color.
\*\*\/
.current_speaker_list div.speaking
{
    background-color: #cceaf4;
}
</pre>

	@section	sd_meeting_tool_speakers_usage				Usage
	
	@par		Configuring the speaker list
	
	Each speaker list is bound to an agenda, so first create an agenda.
	
	The speaker list needs a list of possible speakers and an optional display format and list sort for the list of possible speakers.
	
	Below the possible speakers list are some default times. A shortcode to display the speaker list is available at the bottom. The
	shortcode automatically updates itself. See the above CSS selectors for styling of the list. 
	
	@par		Editing the speaker list
	
	The administration interface presents the admin with an empty speaker list on the left, a list of possible speakers on the right and
	an agenda item selector at the bottom.
	
	Select the agenda item that needs speakers. To add a speaker, double-click on a participant. The participant will be added to the left side.
	
	Clicking on the participants box will expand it and allow the administrator to adjust the speaker time and start the timer. The timer must be
	stopped before another speaker is allowed to begin speaking.
	
	Speakers can be dragged up and down but only if they haven't already spoken. After a speaker has spoken the speaker's box will contain
	data about when the speaker started speaking, stopped speaking, how long the speaker was allowed to speak and how long the speaker actually
	spoke.
	
	To create a reply, open the box of a speaker and then double-click on a participant. The participant's box will be added slightly indented
	relative to the parent speaker. This process can be repeated almost indefinitely.
	
	@par		Displays
	
	Displays control how the current speaker shortcode is displayed to the visitor. Similar to how the display formats are configured, each display
	replaces specific, hashed keywords. Valid keywords are:
	
	- @b \#participant\# The participant using the display format specified in the shortcode.
	- @b \#time_left\# 	A countdown timer of how much time the speaker has left.
	
	After a display is created a shortcode for the display can be generated. Each shortcode specifies which display format to use and
	whether to update the current speaker using AJAX.
	
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
*/

/**
	Speakers plugin for SD Meeting Tool.
	
	@brief		Extends agendas with speaker services.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
	
	@par		Changelog
	
	@par		1.1
	
	- New: Restop: updates the time the user stopped speaking to the current time.
	- New: Delete button follows current speaker.
	- New: Added first setting: default speaker time.
	- New: Able to change current agenda item using a button.
	- New: Cursor keys can be used to select agenda item (after first selecting the select box).
	- Fix: Speaker list can be editing by several people simultaneously.
	- Fix: Size of management panel should be maximized without becoming too big.
	- Fix: Speaker log shortcode no longer returns description of log.
	- Fix: Prevent speaker from expanding when dragging.
**/
class SD_Meeting_Tool_Speakers
	extends SD_Meeting_Tool_0
{
	public function __construct()
	{
		parent::__construct( __FILE__ );

		// Internal filters
		add_filter( 'sd_mt_create_speaker',					array( &$this, 'sd_mt_create_speaker' ) );
		add_filter( 'sd_mt_create_speaker_list',			array( &$this, 'sd_mt_create_speaker_list' ) );
		add_filter( 'sd_mt_delete_speaker',					array( &$this, 'sd_mt_delete_speaker' ) );
		add_filter( 'sd_mt_delete_speaker_display',			array( &$this, 'sd_mt_delete_speaker_display' ) );
		add_filter( 'sd_mt_delete_speaker_list',			array( &$this, 'sd_mt_delete_speaker_list' ) );
		add_filter( 'sd_mt_empty_speaker_list',				array( &$this, 'sd_mt_empty_speaker_list' ) );
		add_filter( 'sd_mt_get_all_speaker_displays',		array( &$this, 'sd_mt_get_all_speaker_displays') );
		add_filter( 'sd_mt_get_all_speaker_lists',			array( &$this, 'sd_mt_get_all_speaker_lists') );
		add_filter( 'sd_mt_get_speaker',					array( &$this, 'sd_mt_get_speaker' ) );
		add_filter( 'sd_mt_get_speaker_display',			array( &$this, 'sd_mt_get_speaker_display' ) );
		add_filter( 'sd_mt_get_speaker_list',				array( &$this, 'sd_mt_get_speaker_list' ) );
		add_filter( 'sd_mt_get_speakers',					array( &$this, 'sd_mt_get_speakers' ), 10, 2 );
		add_filter( 'sd_mt_update_speaker',					array( &$this, 'sd_mt_update_speaker' ) );
		add_filter( 'sd_mt_update_speaker_display',			array( &$this, 'sd_mt_update_speaker_display' ) );
		add_filter( 'sd_mt_update_speaker_list',			array( &$this, 'sd_mt_update_speaker_list' ) );
		
		// Ajax
		add_action( 'wp_ajax_ajax_sd_mt_speakers_admin',			array( &$this, 'ajax_admin') );
		add_action( 'wp_ajax_ajax_sd_mt_speakers_user',				array( &$this, 'ajax_user') );
		add_action( 'wp_ajax_nopriv_ajax_sd_mt_speakers_user',		array( &$this, 'ajax_user') );

		// Shortcodes
		add_shortcode('current_speaker',					array( &$this, 'shortcode_current_speaker') );
		add_shortcode('current_speaker_list',				array( &$this, 'shortcode_current_speaker_list') );
		add_shortcode('speaker_list_log',					array( &$this, 'shortcode_speaker_list_log') );
		
		// External actions
		add_filter( 'sd_mt_admin_menu',						array( &$this, 'sd_mt_admin_menu' ) );
	}

	public function activate()
	{
		parent::activate();		

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_speaker_displays` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `blog_id` int(11) NOT NULL,
		  `data` longtext NOT NULL COMMENT 'Serialized data',
		  PRIMARY KEY (`id`),
		  KEY `blog_id` (`blog_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
		");

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_speaker_lists` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `blog_id` int(11) NOT NULL,
		  `agenda` int(11) NOT NULL,
		  `data` longtext NOT NULL COMMENT 'Serialized data',
		  PRIMARY KEY (`id`),
		  KEY `blog_id` (`blog_id`), 
		  KEY `agenda` (`agenda`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
		");

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_speakers` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `speaker_list_id` int(11) NOT NULL,
		  `agenda_item_id` int(11) NOT NULL,
		  `data` longtext NOT NULL COMMENT 'Serialized data',
		  PRIMARY KEY (`id`),
		  KEY `agenda_item_id` (`agenda_item_id`), 
		  KEY `speaker_list_id` (`speaker_list_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
		");

	}
	
	public function uninstall()
	{
		parent::uninstall();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_speaker_displays`");
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_speaker_lists`");
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_speakers`");
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_admin_menu( $menus )
	{
		$this->load_language();
		
		$menus[ $this->_('Speakers') ] = array(
			'sd_mt',
			$this->_('Speakers'),
			$this->_('Speakers'),
			'read',
			'sd_mt_speakers',
			array( &$this, 'admin' )
		);

		wp_enqueue_style( 'sd_mt_speakers', '/' . $this->paths['path_from_base_directory'] . '/css/SD_Meeting_Tool_Speakers.css', false, '1.0', 'screen' );
		wp_enqueue_style( 'sd_mt_speakers_jquery_ui', '/' . $this->paths['path_from_base_directory'] . '/css/jquery-ui-1.8.16.custom.css', false, '1.0', 'screen' );
		return $menus;
	}

	public function admin()
	{
		$tab_data = array(
			'tabs'		=>	array(),
			'functions' =>	array(),
		);
				
		$tab_data['default'] = 'overview';

		$tab_data['tabs']['overview'] = $this->_( 'Overview' );
		$tab_data['functions']['overview'] = 'admin_overview';

		if ( isset( $_GET['tab'] ) )
		{
			if ( $_GET['tab'] == 'manage' )
			{
				$tab_data['tabs']['manage'] = $this->_( 'Manage speakers' );
				$tab_data['functions']['manage'] = 'admin_manage';

				$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $_GET['id'] );
				if ( $speaker_list === false )
					wp_die( $this->_( 'Specified speaker list does not exist!' ) );
				
				$agenda = $this->filters( 'sd_mt_get_agenda', $speaker_list->data->agenda_id );

				$tab_data['page_titles']['manage'] = $this->_( 'Managing speakers for agenda: %s', $agenda->data->name );
			}	// manage

			if ( $_GET['tab'] == 'log' )
			{
				$tab_data['tabs']['log'] = $this->_( 'View log' );
				$tab_data['functions']['log'] = 'admin_view_log';

				$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $_GET['id'] );
				if ( $speaker_list === false )
					wp_die( $this->_( 'Specified speaker list does not exist!' ) );
				
				$agenda = $this->filters( 'sd_mt_get_agenda', $speaker_list->data->agenda_id );
				$tab_data['page_titles']['log'] = $this->_( 'Viewing speaker list log for %s', $agenda->data->name );
			}	// manage

			if ( $_GET['tab'] == 'edit' )
			{
				$tab_data['tabs']['edit'] = $this->_( 'Edit' );
				$tab_data['functions']['edit'] = 'admin_edit';

				$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $_GET['id'] );
				if ( $speaker_list === false )
					wp_die( $this->_( 'Specified speaker list does not exist!' ) );
				$agenda = $this->filters( 'sd_mt_get_agenda', $speaker_list->data->agenda_id );
				if ( $agenda === false )
					wp_die( $this->_( 'Specified agenda does not exist!' ) );

				$tab_data['page_titles']['edit'] = $this->_( 'Editing speaker list for agenda: %s', $agenda->data->name );
			}	// edit

			if ( $_GET['tab'] == 'edit_display' )
			{
				$tab_data['tabs']['edit_display'] = $this->_( 'Edit display' );
				$tab_data['functions']['edit_display'] = 'admin_display_edit';

				$speaker_display = $this->filters( 'sd_mt_get_speaker_display', $_GET['id'] );
				if ( $speaker_display === false )
					wp_die( $this->_( 'Specified speaker display does not exist!' ) );

				$tab_data['page_titles']['edit_display'] = $this->_( 'Editing speaker display: %s', $speaker_display->data->name );
			}	// edit

			if ( $_GET['tab'] == 'shortcodes' )
			{
				$tab_data['tabs']['shortcodes'] = $this->_( 'Shortcodes' );
				$tab_data['functions']['shortcodes'] = 'admin_display_shortcodes';

				$speaker_display = $this->filters( 'sd_mt_get_speaker_display', $_GET['id'] );
				if ( $speaker_display === false )
					wp_die( $this->_( 'Specified speaker display does not exist!' ) );

				$tab_data['page_titles']['shortcodes'] = $this->_( 'Editing shortcodes for the speaker list of agenda: %s', $speaker_display->data->name );
			}	// Shortcodes
		}

		$tab_data['tabs']['displays_overview'] = $this->_( 'Displays' );
		$tab_data['functions']['displays_overview'] = 'admin_displays_overview';

		$tab_data['tabs']['uninstall'] = $this->_( 'Uninstall' );
		$tab_data['functions']['uninstall'] = 'admin_uninstall';

		$this->tabs($tab_data);
	}

	public function admin_overview()
	{
		if ( isset( $_POST['action_submit'] ) && isset( $_POST['speaker_lists'] ) )
		{
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['speaker_lists'] as $speaker_list => $ignore )
				{
					$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $speaker_list );
					if ( $speaker_list !== false )
					{
						$this->filters( 'sd_mt_delete_speaker_list', $speaker_list );
						$this->message( $this->_( 'Speaker list <em>%s</em> deleted.', $speaker_list->id ) );
					}
				}
			}	// delete

			if ( $_POST['action'] == 'empty' )
			{
				foreach( $_POST['speaker_lists'] as $speaker_list => $ignore )
				{
					$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $speaker_list );
					if ( $speaker_list !== false )
					{
						$this->filters( 'sd_mt_empty_speaker_list', $speaker_list );
						$this->message( $this->_( 'Speaker list <em>%s</em> emptied.', $speaker_list->id ) );
					}
				}
			}	// empty
		}
		
		if ( isset( $_POST['create_speaker_list'] ) )
		{
			$speaker_list = new SD_Meeting_Tool_Speaker_List();
			$speaker_list->data->agenda_id = $_POST['agenda'];
			$speaker_list = $this->filters( 'sd_mt_create_speaker_list', $speaker_list );
			
			$edit_link = add_query_arg( array(
				'tab' => 'edit',
				'id' => $speaker_list->id,
			) );
			
			$this->message( $this->_( 'Speaker list created! <a href="%s">Edit the newly-created speaker list</a>.', $edit_link ) );
		}	// create speaker list

		$form = $this->form();
		$rv = $form->start();
		
		$speaker_lists = $this->filters( 'sd_mt_get_all_speaker_lists', array() );
		$all_agendas = $this->filters( 'sd_mt_get_all_agendas', array() );
		$agendas = array();
		foreach( $all_agendas as $agenda )
			$agendas[ $agenda->id ] = $agenda->data->name;
		
		if ( count( $speaker_lists ) < 1 )
		{
			$this->message( $this->_( 'No speaker lists found. Speaker lists require at least one agenda.' ) );
		}
		else
		{
			$t_body = '';
			foreach( $speaker_lists as $speaker_list )
			{
				$input_speaker_list_select = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $speaker_list->id,
					'name' => $speaker_list->id,
					'nameprefix' => '[speaker_lists]',
				);
				
				$default_action = array();		// What to do when the user clicks on the speaker list.
								
				// ACTION time.
				$actions = array();
				
				// Manage speakers
				$manage_action_url = add_query_arg( array(
					'tab' => 'manage',
					'id' => $speaker_list->id,
				) );
				$actions[] = '<a href="'.$manage_action_url.'">'. $this->_('Manage speakers') . '</a>';
				
				// View log
				$view_action_url = add_query_arg( array(
					'tab' => 'log',
					'id' => $speaker_list->id,
				) );
				$actions[] = '<a href="'.$view_action_url.'">'. $this->_('View log') . '</a>';
				
				// Edit speaker list
				$edit_action_url = add_query_arg( array(
					'tab' => 'edit',
					'id' => $speaker_list->id,
				) );
				$actions[] = '<a href="'.$edit_action_url.'">'. $this->_('Edit') . '</a>';
				
				if ( count( $default_action ) < 1 )
					$default_action = array(
						'title' => $this->_( 'Manage speakers' ),
						'url' => $manage_action_url,
					);
				
				$actions = implode( '&emsp;<span class="sep">|</span>&emsp;', $actions );
				
				// INFO time.
				$info = array();

				$info = implode( '</div><div>', $info );
				
				$speaker_list_name = $this->_( 'Speaker list for agenda: %s', $agendas[ $speaker_list->data->agenda_id ] );
				
				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input($input_speaker_list_select) . ' <span class="screen-reader-text">' . $form->make_label($input_speaker_list_select) . '</span></th>
					<td>
						<div>
							<a
							title="' . $default_action['title'] . '"
							href="'. $default_action['url'] .'">' . $speaker_list_name . '</a>
						</div>
						<div class="row-actions">' . $actions . '</a>
					</td>
					<td><div>' . $info . '</div></td>
				</tr>';
			}
			
			$input_actions = array(
				'type' => 'select',
				'name' => 'action',
				'label' => $this->_('With the selected rows'),
				'options' => array(
					array( 'value' => '', 'text' => $this->_('Do nothing') ),
					array( 'value' => 'delete', 'text' => $this->_('Delete') ),
					array( 'value' => 'empty', 'text' => $this->_('Empty') ),
				),
			);
			
			$input_action_submit = array(
				'type' => 'submit',
				'name' => 'action_submit',
				'value' => $this->_('Apply'),
				'css_class' => 'button-secondary',
			);
			
			$selected = array(
				'type' => 'checkbox',
				'name' => 'check',
			);
			
			$rv .= '
				<p>
					' . $form->make_label( $input_actions ) . '
					' . $form->make_input( $input_actions ) . '
					' . $form->make_input( $input_action_submit ) . '
				</p>
				<table class="widefat">
					<thead>
						<tr>
							<th class="check-column">' . $form->make_input( $selected ) . '<span class="screen-reader-text">' . $this->_('Selected') . '</span></th>
							<th>' . $this->_('Speaker list') . '</th>
							<th>' . $this->_('Info') . '</th>
						</tr>
					</thead>
					<tbody>
						'.$t_body.'
					</tbody>
				</table>
			';
		}
		
		if ( count ( $agendas ) > 0 )
		{
			// Create a new speaker list
			$inputs_create = array(
				'agenda' => array(
					'type' => 'select',
					'name' => 'agenda',
					'label' => $this->_( 'Agenda' ),
					'description' => $this->_( 'The speaker list will handle speakers for the selected agenda.' ),
					'options' => $agendas,
				),
				'create_speaker_list' => array(
					'type' => 'submit',
					'name' => 'create_speaker_list',
					'value' => $this->_( 'Create a new speaker list' ),
					'css_class' => 'button-primary',
				),
			);
	
			$rv .= '<h3>' . $this->_('Create a new speaker list')  . '</h3>';
			
			$rv .= $this->display_form_table( $inputs_create );
		}

		$rv .= $form->stop();
		
		echo $rv;
	}

	public function admin_view_log()
	{
		$id = $_GET['id'];
		$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $id );
		
		$rv = '
			<p>
				' . $this->_( "Each agenda item has a list of speakers. The speaker row is comma-separated into the following: The speaker's name, time allocated, start time, stop time and time spoken." ) . '
			</p>
		';
		
		$rv .= $this->get_speaker_list_log( $speaker_list );		
		
		echo $rv;
	}
	
	public function admin_edit()
	{
		$form = $this->form();
		$id = $_GET['id'];
		$rv = '';
		
		$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $id );
		
		$inputs = array(
			'default_time_speaker' => array(
				'name' => 'default_time_speaker',
				'type' => 'text',
				'label' => $this->_( 'Default speaker time' ),
				'description' => $this->_( 'The default amount of time for a speaker.' ),
				'size' => 5,
				'maxlength' => 10,
			),
			'default_time_reply' => array(
				'name' => 'default_time_reply',
				'type' => 'text',
				'label' => $this->_( 'Default reply time' ),
				'description' => $this->_( 'The default amount of time for a reply.' ),
				'size' => 5,
				'maxlength' => 10,
			),
			'list_id' => array(
				'name' => 'list_id',
				'type' => 'select',
				'label' => $this->_( 'Speaker list' ),
				'options' => array(),
			),
			'display_format_id' => array(
				'name' => 'display_format_id',
				'type' => 'select',
				'label' => $this->_( 'Display format' ),
				'options' => array( '' => $this->_( "Use the list's display format" ) ),
			),
			'list_sort_id' => array(
				'name' => 'list_sort_id',
				'type' => 'select',
				'label' => $this->_( 'List sort' ),
				'options' => array( '' => $this->_( "Use the list's sort" ) ),
			),
			'update' => array(
				'type' => 'submit',
				'name' => 'update',
				'value' => $this->_( 'Update speaker list settings' ),
				'css_class' => 'button-primary',
			),
			'current_speaker_list_shortcode_display_format' => array(
				'name' => 'current_speaker_list_shortcode_display_format',
				'type' => 'select',
				'label' => $this->_( 'Display format' ),
			),
			'create_current_speaker_list_shortcode' => array(
				'type' => 'submit',
				'name' => 'create_current_speaker_list_shortcode',
				'value' => $this->_( 'Create shortcode' ),
				'css_class' => 'button-primary',
			),
		);
		
		$all_lists = $this->filters( 'sd_mt_get_all_lists', array() );
		foreach( $all_lists as $list )
			$inputs[ 'list_id']['options'][ $list->id ] = $list->data->name;

		$display_formats = $this->filters( 'sd_mt_get_all_display_formats', array() );
		foreach( $display_formats as $display_format )
			$inputs[ 'display_format_id']['options'][ $display_format->id ] = $display_format->data->name;

		$list_sorts = $this->filters( 'sd_mt_get_all_list_sorts', array() );
		foreach( $list_sorts as $list_sort )
			$inputs[ 'list_sort_id']['options'][ $list_sort->id ] = $list_sort->data->name;

		if ( isset( $_POST['update'] ) )
		{
			$result = $form->validate_post( $inputs, array_keys( $inputs ), $_POST );

			if ($result === true)
			{
				$speaker_list->data->default_time_reply = $this->time_to_seconds( $_POST[ 'default_time_reply' ] );
				$speaker_list->data->default_time_speaker = $this->time_to_seconds( $_POST[ 'default_time_speaker' ] );
				$speaker_list->data->list_id = intval( $_POST[ 'list_id' ] );
				$speaker_list->data->display_format_id = intval( $_POST[ 'display_format_id' ] );
				$speaker_list->data->list_sort_id = intval( $_POST[ 'list_sort_id' ] );
				$this->filters( 'sd_mt_update_speaker_list', $speaker_list );
				
				$this->message( $this->_('The speaker list has been updated!') );
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}
		$inputs['default_time_reply']['value'] = $this->seconds_to_time( $speaker_list->data->default_time_reply );
		$inputs['default_time_speaker']['value'] = $this->seconds_to_time( $speaker_list->data->default_time_speaker );
		$inputs['list_id']['value'] = intval( $speaker_list->data->list_id );
		$inputs['display_format_id']['value'] = intval( $speaker_list->data->display_format_id );
		$inputs['list_sort_id']['value'] = intval( $speaker_list->data->list_sort_id );
		
		$inputs['current_speaker_list_shortcode_display_format']['options'] = array_slice( $inputs['display_format_id']['options'], 1, null, true );
		
		if ( isset( $_POST[ 'create_current_speaker_list_shortcode'] ) )
		{
			$agenda = $this->filters( 'sd_mt_get_agenda', $speaker_list->data->agenda_id );
			$post = new stdClass();
			$post->post_type = 'page';
			$user = wp_get_current_user();
			$page_id = wp_insert_post(array(
				'post_title' => $this->_( 'Speaker list for agenda: %s', $agenda->data->name ),
				'post_type' => 'page',
				'post_content' => '[current_speaker_list speaker_list_id="' . $speaker_list->id . '" display_format_id="'. $_POST['current_speaker_list_shortcode_display_format']. '"]',
				'post_status' => 'publish',
				'post_author' => $user->data->ID,
			));
				$this->message( $this->_( 'A new page has been created! You can now %sedit the page%s or %sview the page%s.',
					'<a href="' . add_query_arg( array( 'post' => $page_id), 'post.php?action=edit' ) . '">',
					'</a>',
					'<a href="' . add_query_arg( array( 'p' => $page_id), get_bloginfo('url') ) . '">',
					'</a>'
				) );
		}
		
		$rv .= '
			' . $form->start() . '
			
			<h3>' . $this->_( 'Speaker list and internal display' ). '</h3>
			
			' . $this->display_form_table( array(
					$inputs['list_id'],
					$inputs['display_format_id'],
					$inputs['list_sort_id'],
				) ). '

			<h3>' . $this->_( 'Times' ). '</h3>

			' . $this->display_form_table( array(
					$inputs['default_time_speaker'],
					$inputs['default_time_reply'],
				) ). '
			
			<p>
				' . $form->make_input( $inputs[ 'update' ] ) . '
			</p>
			
			' . $form->stop() . '
			' . $form->start() . '

			<h3>' . $this->_( 'Shortcodes' ). '</h3>
			
			<h2>' . $this->_( 'Speaker list' ). '</h2>
			
			' . $this->display_form_table( array(
					$inputs['current_speaker_list_shortcode_display_format'],
					$inputs['create_current_speaker_list_shortcode'],
				) ). '

			<h2>' . $this->_( 'Speaker list log' ). '</h2>
			
			' . $this->_( 'The speaker list log is the same log shown to the administrator. To include the log on a page, use the shortcode %s',
				'<code>[speaker_list_log speaker_list_id=' . $id . ']</code>'
			) . '
			
			' . $form->stop() . '
		';


		echo $rv;
	}

	public function admin_manage()
	{
		$form = $this->form();
		$id = $_GET['id'];

		$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $id );
		
		$agenda = $this->filters( 'sd_mt_get_agenda', $speaker_list->data->agenda_id );

		$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $id );
		
		$inputs = array(
			'change_agenda_item' => array(
				'css_class' => 'button-secondary',
				'name' => 'change_agenda_item',
				'title' => $this->_( 'Make this agenda item the current item' ),
				'type' => 'submit',
				'value' => $this->_( 'Make current' ),
			),
			'default_time' => array(
				'description' => $this->_( 'The speaking time for a speaker set when adding a speaker. Leave empty to use the default time. Specify the time in MM:SS.' ),
				'label' => $this->_( 'Speaker time' ),
				'maxlength' => 6,
				'name' => 'default_time',
				'size' => 6,
				'type' => 'text',
				'validation' => array( 'empty' => true ),
				'value' => '',		// Leave empty else all newly created speakers will have this time, even if they're replies and what not.
			),
			'participant_search' => array(
				'type' => 'text',
				'name' => 'participant_search',
				'size' => 50,
				'maxlength' => 100,
				'label' => $this->_( 'Quicksearch' ),
				'validation' => array( 'empty' => true ),
			),
			'delete_speaker' => array(
				'css_class' => 'button-secondary',
				'type' => 'submit',
				'name' => 'delete_speaker',
				'value' => $this->_( 'Delete speaker' ),
			),
			'agenda_items' => array(
				'type' => 'select',
				'name' => 'agenda_items',
				'label' => '', 
				'options' => array(),
			),
		);
		
		$speakers_label = $this->_( 'Speakers for <strong>%s</strong>', $agenda->data->name );

		wp_enqueue_script( 'jquery-ui-dialog' );
		wp_enqueue_script( 'jquery-ui-sortable' );
		
		$rv = '
			<div class="manage_speakers metabox-holder">
				<div class="overview_division overview_left">
					<div class="overview_division_content overview_left_content">
						<div id="speakers" class="speakers stuffbox">
							<div class="heading">
								<h3 class="hndle"><span>' . $speakers_label . '</span></h3>
							</div>
							<div class="inside">
								<div class="container">
								</div>
								' . $form->make_input( $inputs['delete_speaker'] ) . '
							</div>
						</div>

						<div class="bottom">
	
							<div id="agenda" class="agenda stuffbox">
								<h3 class="hndle"><span>' . $this->_( 'Agenda' ) . '</span></h3>
								<div class="inside">
								<p>
									' . $this->get_admin_agenda( $speaker_list ) . '
									' . $form->make_input( $inputs['change_agenda_item'] ) . '
								</p>
								</div>
							</div>
						</div>
					
					</div>
				</div>
				<div class="overview_division overview_right">
					<div class="overview_division_content overview_right_content">

						<div id="participant_list" class="participant_list stuffbox">
							<h3 class="hndle"><span>' . $this->_( 'Participants' ) . '</span></h3>
							<div class="inside">
								<p id="participant_search">
									' . $form->make_label( $inputs[ 'participant_search' ] ) . '
									' . $form->make_input( $inputs[ 'participant_search' ] ) . '
								</p>
								<ul>
								</ul>
							</div>
						</div>
						
						<div id="settings" class="settings stuffbox">
							<h3 class="hndle"><span>' . $this->_( 'Settings' ) . '</span></h3>
							<div class="inside">
								<div id="settings_dialog" style="display: none;" title="' . $this->_( 'Settings' ) . '">
									<p>
										' . $this->display_form_table( array( $inputs[ 'default_time' ] ) ). '
									</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

			'.$form->start().'
			<script type="text/javascript" src="'. $this->paths['url'] . '/js/sd_meeting_tool_speakers.js' .'"></script>
			<script type="text/javascript" >
				jQuery(document).ready(function($){
					speakers_ajax = new sd_meeting_tool_speakers();
					speakers_ajax.init_admin({
						"action" : "ajax_sd_mt_speakers_admin", 
						"ajaxnonce" : "' . wp_create_nonce( 'ajax_sd_mt_speakers' ) . '",
						"ajaxurl" : "'. admin_url('admin-ajax.php') . '",
						"speaker_list" : "'. $speaker_list->id . '",
					});
				});
			</script>
			'.$form->stop().'
		';
		echo $rv;
/*		
										' . $form->make_label( $inputs[ 'default_time' ] ) . '
										' . $form->make_input( $inputs[ 'default_time' ] ) . '
										' . $form->make_description( $inputs[ 'default_time' ] ) . '
*/										
	}

	public function admin_displays_overview()
	{
		if ( isset( $_POST['action_submit'] ) && isset( $_POST['speaker_displays'] ) )
		{
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['speaker_displays'] as $speaker_display => $ignore )
				{
					$speaker_display = $this->filters( 'sd_mt_get_speaker_display', $speaker_display );
					if ( $speaker_display !== false )
					{
						$this->filters( 'sd_mt_delete_speaker_display', $speaker_display );
						$this->message( $this->_( 'Speaker display <em>%s</em> deleted.', $speaker_display->id ) );
					}
				}
			}	// delete

		}
		
		if ( isset( $_POST['create_speaker_display'] ) )
		{
			$speaker_display = new SD_Meeting_Tool_Speaker_Display();
			$speaker_display->data->name = $this->_( 'Speaker display created %s', date( 'Y-m-d H:i:s' ) );
			$speaker_display = $this->filters( 'sd_mt_update_speaker_display', $speaker_display );
			
			$edit_link = add_query_arg( array(
				'tab' => 'edit',
				'id' => $speaker_display->id,
			) );
			
			$this->message( $this->_( 'Speaker display created! <a href="%s">Edit the newly-created speaker display</a>.', $edit_link ) );
		}	// create display

		$form = $this->form();
		$rv = $form->start();
		
		$speaker_displays = $this->filters( 'sd_mt_get_all_speaker_displays', array() );
		
		if ( count( $speaker_displays ) < 1 )
		{
			$this->message( $this->_( 'No speaker displays found.' ) );
		}
		else
		{
			$t_body = '';
			foreach( $speaker_displays as $speaker_display )
			{
				$input_speaker_display_select = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $speaker_display->id,
					'name' => $speaker_display->id,
					'nameprefix' => '[speaker_displays]',
				);
				
				// ACTION time.
				$actions = array();
				
				// Shortcodes
				$shortcode_action_url = add_query_arg( array(
					'tab' => 'shortcodes',
					'id' => $speaker_display->id,
				) );
				$actions[] = '<a href="'.$shortcode_action_url.'">'. $this->_('Shortcodes') . '</a>';
				
				// Edit speaker list
				$edit_action_url = add_query_arg( array(
					'tab' => 'edit_display',
					'id' => $speaker_display->id,
				) );
				$actions[] = '<a href="'.$edit_action_url.'">'. $this->_('Edit display') . '</a>';
				
				$actions = implode( '&emsp;<span class="sep">|</span>&emsp;', $actions );
				
				// INFO time.
				$info = array();

				$info = implode( '</div><div>', $info );
				
				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input($input_speaker_display_select) . ' <span class="screen-reader-text">' . $form->make_label($input_speaker_display_select) . '</span></th>
					<td>
						<div>
							<a
							title="' . $this->_( 'Make a shortcode for this display') . '"
							href="'. $shortcode_action_url .'">' . $speaker_display->data->name . '</a>
						</div>
						<div class="row-actions">' . $actions . '</a>
					</td>
					<td><div>' . $info . '</div></td>
				</tr>';
			}
			
			$input_actions = array(
				'type' => 'select',
				'name' => 'action',
				'label' => $this->_('With the selected rows'),
				'options' => array(
					array( 'value' => '', 'text' => $this->_('Do nothing') ),
					array( 'value' => 'delete', 'text' => $this->_('Delete') ),
				),
			);
			
			$input_action_submit = array(
				'type' => 'submit',
				'name' => 'action_submit',
				'value' => $this->_('Apply'),
				'css_class' => 'button-secondary',
			);
			
			$selected = array(
				'type' => 'checkbox',
				'name' => 'check',
			);
			
			$rv .= '
				<p>
					' . $form->make_label( $input_actions ) . '
					' . $form->make_input( $input_actions ) . '
					' . $form->make_input( $input_action_submit ) . '
				</p>
				<table class="widefat">
					<thead>
						<tr>
							<th class="check-column">' . $form->make_input( $selected ) . '<span class="screen-reader-text">' . $this->_('Selected') . '</span></th>
							<th>' . $this->_('Speaker display') . '</th>
							<th>' . $this->_('Info') . '</th>
						</tr>
					</thead>
					<tbody>
						'.$t_body.'
					</tbody>
				</table>
			';
		}

		// Allow the user to create a new list
		$input_speaker_display_create = array(
			'type' => 'submit',
			'name' => 'create_speaker_display',
			'value' => $this->_( 'Create a new speaker display' ),
			'css_class' => 'button-primary',
		);
		
		$rv .= '<h3>' . $this->_('Create a new speaker display')  . '</h3>';

		$rv .= '<p>' . $form->make_input( $input_speaker_display_create ) . '</p>';

		$rv .= $form->stop();
		
		echo $rv;
	}

	public function admin_display_edit()
	{
		$form = $this->form();
		$id = $_GET['id'];
		$rv = '';
		
		$speaker_display = $this->filters( 'sd_mt_get_speaker_display', $id );
		
		$inputs = array(
			'name' => array(
				'name' => 'name',
				'type' => 'text',
				'label' => $this->_( 'Name' ),
				'size' => 50,
				'maxlength' => 200,
			),
			'format' => array(
				'name' => 'format',
				'type' => 'textarea',
				'label' => $this->_( 'Format' ),
				'cols' => 40,
				'rows' => 10,
			),
			'update' => array(
				'type' => 'submit',
				'name' => 'update',
				'value' => $this->_( 'Update speaker display settings' ),
				'css_class' => 'button-primary',
			),
		);
		
		if ( isset( $_POST['update'] ) )
		{
			$result = $form->validate_post( $inputs, array_keys( $inputs ), $_POST );

			if ($result === true)
			{
				$speaker_display->data->name = $_POST[ 'name' ];
				$speaker_display->data->format = stripslashes( $_POST[ 'format' ] );
				$this->filters( 'sd_mt_update_speaker_display', $speaker_display );
				
				$this->message( $this->_('The speaker display has been updated!') );
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}
		$inputs['name']['value'] = $speaker_display->data->name;
		$inputs['format']['value'] = $speaker_display->data->format;
		
		$rv .= '
			' . $form->start() . '
			
			' . $this->display_form_table( array(
					$inputs['name'],
					$inputs['format'],
				) ). '

			<p>
				' . $form->make_input( $inputs[ 'update' ] ) . '
			</p>

			' . $form->stop() . '
			
			<h3>' . $this->_( 'Keywords' ). '</h3>

			<table class="widefat keywords">
				<thead>
					<tr>
						<th>' . $this->_('Keyword') . '</th>
						<th>' . $this->_('Meaning') . '</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>#participant#</td>
						<td>' . $this->_( 'The participant using the display format specified in the shortcode.' ). '</td>
					</tr>
					<tr>
						<td>#time_left#</td>
						<td>' . $this->_( 'A countdown timer of how much time the speaker has left.' ). '</td>
					</tr>
				</tbody>
			</table>
		';

		echo $rv;
	}
	
	public function admin_display_shortcodes()
	{
		$form = $this->form();
		$id = $_GET['id'];
		$rv = '';
		
		$speaker_display = $this->filters( 'sd_mt_get_speaker_display', $id );
		
		$inputs = array(
			'speaker_list' => array(
				'name' => 'speaker_list',
				'type' => 'select',
				'label' => $this->_( 'Speaker list' ),
				'options' => array(),
			),
			'display_format' => array(
				'name' => 'display_format',
				'type' => 'select',
				'label' => $this->_( 'Display format' ),
				'options' => array(),
			),
			'autorefresh' => array(
				'name' => 'autorefresh',
				'type' => 'checkbox',
				'label' => $this->_( 'Automatically refresh' ),
			),
			'submit' => array(
				'type' => 'submit',
				'name' => 'submit',
				'value' => $this->_( 'Create the shortcode with the above settings.' ),
				'css_class' => 'button-primary',
			),
		);
		
		$all_agendas = $this->filters( 'sd_mt_get_all_agendas', array() );
					
		$speaker_lists = $this->filters( 'sd_mt_get_all_speaker_lists', array() );
		foreach( $speaker_lists as $speaker_list )
		{
			$agenda = $all_agendas[ $speaker_list->data->agenda_id ];
			$inputs['speaker_list']['options'][ "0" . $speaker_list->id ] = $this->_( 'Editing speaker list for agenda: %s', $agenda->data->name );
		}

		$display_formats = $this->filters( 'sd_mt_get_all_display_formats', array() );
		foreach( $display_formats as $display_format )
			$inputs['display_format']['options'][ "0" . $display_format->id ] = $display_format->data->name;

		$inputs_to_display = array(
			'speaker_list',
			'display_format',
			'autorefresh',
			'submit'
		);
			
		if ( count( $_POST ) > 0 )
		{
			foreach( $inputs as $index => $input )
				$form->use_post_value( $inputs[$index], $_POST, $index );
			
			// Build the shortcode
			$shortcode = sprintf(
				'[current_speaker speaker_display="%s" speaker_list="%s" display_format="%s" autorefresh="%s"]',
				$speaker_display->id,
				intval( $_POST[ 'speaker_list' ] ),
				intval( $_POST[ 'display_format' ] ),
				isset( $_POST[ 'autorefresh'] ) ? 'yes' : 'no'
			);
			
			if ( isset( $_POST['shortcode_create'] ) )
			{
				$post = new stdClass();
				$post->post_type = 'page';
				$user = wp_get_current_user();
				$page_id = wp_insert_post(array(
					'post_title' => $this->_( 'Current speaker' ),
					'post_type' => 'page',
					'post_content' => $shortcode,
					'post_status' => 'publish',
					'post_author' => $user->data->ID,
				));
				$this->message( $this->_( 'A new page has been created! You can now %sedit the page%s or %sview the page%s.',
					'<a href="' . add_query_arg( array( 'post' => $page_id), 'post.php?action=edit' ) . '">',
					'</a>',
					'<a href="' . add_query_arg( array( 'p' => $page_id), get_bloginfo('url') ) . '">',
					'</a>'
				) );
			}
			
			$inputs['shortcode'] = array(
				'name' => 'shortcode',
				'type' => 'text',
				'label' => $this->_( 'Generated shortcode' ),
				'size' => 50,
				'maxlength' => 200,
				'value' => $shortcode,
			);
			$inputs['shortcode_create'] = array(
				'name' => 'shortcode_create',
				'type' => 'submit',
				'value' => $this->_( 'Create a page with this shortcode' ),
				'css_class' => 'button-secondary',
			);
			$inputs_to_display[] = 'shortcode';
			$inputs_to_display[] = 'shortcode_create';
		}
		
		$form_inputs = array();
		foreach( $inputs_to_display as $input_name )
			$form_inputs[] = $inputs[ $input_name ];
		
		$rv .= '
			
			' . $form->start() . '
			
			' . $this->display_form_table( $form_inputs ) . '

			' . $form->stop() . '
		';
		
		echo $rv;
	}
		
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Ajax
	// --------------------------------------------------------------------------------------------

	public function ajax_admin()
	{
		if ( ! SD_Meeting_Tool::check_admin_referrer( 'ajax_sd_mt_speakers' ) )
			die();
		
		$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $_POST['speaker_list'] );
		if ( $speaker_list === false )
			die();
		
		switch( $_POST['type'] )
		{
			case 'change_agenda_item':
				$agenda_item_id = intval( $_POST[ 'agenda_item_id' ] );
				$agenda = $this->filters( 'sd_mt_get_agenda', $speaker_list->data->agenda_id );
				
				if ( ! isset( $agenda->items[ $agenda_item_id ] ) )
					break;
				
				$agenda->set_current_item_id( $agenda_item_id );
				$this->filters( 'sd_mt_update_agenda', $agenda );

				$response['result'] = 'ok';
				break;
			case 'create_speaker':
				$speaker = new SD_Meeting_Tool_Speaker();
				$speaker->data->participant_id = $_POST['participant_id'];
				$speaker->data->speaker_list_id = $speaker_list->id;
				$speaker->data->agenda_item_id = $_POST[ 'agenda_item_id' ];
				$speaker->data->order = PHP_INT_MAX - rand(0, 100000);
				$parent = intval( $_POST['parent'] );
				if ( $parent > 0 )
				{
					$speaker->data->parent = $parent;
					$speaker->data->time_to_speak = $speaker_list->data->default_time_reply;
				}
				else
					$speaker->data->time_to_speak = $speaker_list->data->default_time_speaker;

				if ( isset( $_POST[ 'time_to_speak' ] ) )
					$speaker->data->time_to_speak = $this->time_to_seconds( $_POST[ 'time_to_speak' ] );
				
				$this->filters( 'sd_mt_create_speaker', $speaker );

				$response['result'] = 'ok';
				break;
			case 'delete_speaker':
				$speaker = $this->filters( 'sd_mt_get_speaker', $_POST['speaker_id'] );
				
				if ( $speaker === false )
					die();
					
				$this->filters( 'sd_mt_delete_speaker', $speaker );

				$response['result'] = 'ok';
				break;
			case 'get_agenda':
				$this->load_language();
				$agenda = $this->filters( 'sd_mt_get_agenda', $speaker_list->data->agenda_id );
				$response['data'] = array(
					'current_item_id' => $agenda->data->current_item_id,
					'html' => $this->get_admin_agenda( $speaker_list ),
				);
				SD_Meeting_Tool::optimize_response( $response, $_POST['hash'] );
				break;
			case 'get_participants':
				$list = $this->filters( 'sd_mt_get_list', $speaker_list->data->list_id );
				$display_format = $this->get_preferred_display_format( $speaker_list, $list );

				$list_sort_id = $speaker_list->data->list_sort_id > 0 ? $speaker_list->data->list_sort_id : $list->data->list_sort_id;
				$list->data->list_sort = $list_sort_id;
				
				$list = $this->filters( 'sd_mt_list_participants', $list );

				$response[ 'data' ] = array();
				foreach( $list->participants as $participant )
					$response[ 'data' ] [ $participant->id ] = $this->filters( 'sd_mt_display_participant', $participant, $display_format );
				
				SD_Meeting_Tool::optimize_response( $response, $_POST['hash'] );
				break;
			case 'get_speaker':
				if ( $speaker_list->data->current_speaker < 1 )
					break;
					
				$speaker = $this->filters( 'sd_mt_get_speaker', $speaker_list->data->current_speaker );
				
				$response = array(
					'time_start' => $speaker->data->time_start,
					'time_to_speak' => $speaker->data->time_to_speak,
					'time' => $this->time(),
				);
				break;
			case 'get_speakers':
				$list = $this->filters( 'sd_mt_get_list', $speaker_list->data->list_id );
				if ( $list === false )
					break;
				
				$display_format = $this->get_preferred_display_format( $speaker_list, $list );

				$speakers = $this->get_sorted_speaker_list( $speaker_list, $_POST[ 'agenda_item_id' ], $display_format );

				// Convert to html.
				$response['data'] = $this->speakers_to_html( $speaker_list, $speakers );
				SD_Meeting_Tool::optimize_response( $response, $_POST['hash'] );
				break;
			case 'get_seconds_left':
				if ($speaker_list->data->current_speaker < 0 )
					break;

				$speaker = $this->filters( 'sd_mt_get_speaker', $_POST['speaker_id'] );
				if ( $speaker === false )
					break;
				
				$response = array(
					'result' => 'ok',
					'seconds_left' => $speaker->data->time_to_speak - ( $this->time() - $speaker->data->time_start ),
				);
				break;
			case 'modify_time':
				$speaker = $this->filters( 'sd_mt_get_speaker', $_POST['speaker_id'] );
				if ( $speaker === false )
					break;
				
				// Speaker must not have spoken yet!
				if ( $speaker->data->time_stop != '' )
					break;
				
				$time = $_POST['time'];
				if ( $time[0] == '+' || $time[0] == '-' )
				{
					$time = intval( $time );
					$speaker->data->time_to_speak += $time;
					if ( $speaker->data->time_to_speak < 0 )
						$speaker->data->time_to_speak = 0;
				}
				else
				{
					$speaker->data->time_to_speak = $this->time_to_seconds( $_POST[ 'time' ] );
				}
				
				$this->filters( 'sd_mt_update_speaker', $speaker );
				$response = array(
					'result' => 'ok',
					'time' => $this->seconds_to_time( $speaker->data->time_to_speak ),
				);
				break;
			case 'restop':
				$speaker = $this->filters( 'sd_mt_get_speaker', $_POST['speaker_id'] );
				if ( $speaker === false )
					break;
				
				// Can't restop someone who has not stopped speaking
				if ( ! $speaker->data->time_stop > 0 )
				{
					echo json_encode( array( 'result' => 'not speaking' ) );
					break;
				}
				
				$speaker->data->time_stop = $this->time();
				$this->filters( 'sd_mt_update_speaker', $speaker );
				
				$response['result'] = 'ok';
				break;
			case 'start_speaking':
				$speaker = $this->filters( 'sd_mt_get_speaker', $_POST['speaker_id'] );
				if ( $speaker === false )
					break;
				
				$speaker->data->time_start = $this->time();
				$this->filters( 'sd_mt_update_speaker', $speaker );

				$speaker_list->data->current_speaker = $speaker->id;
				$speaker_list->data->speaker_agenda_item_id = $_POST[ 'agenda_item_id' ];
				$this->filters( 'sd_mt_update_speaker_list', $speaker_list );
				
				$response['result'] = 'ok';
				break;
			case 'stop_speaking':
				$speaker = $this->filters( 'sd_mt_get_speaker', $_POST['speaker_id'] );
				if ( $speaker === false )
					break;
				
				// Can't stop someone who has finished. Or isn't speaking to start off with.
				if (
					( $speaker->data->time_start < 1 ) ||
					( $speaker->data->time_stop > 0 )
				)
				{
					echo json_encode( array( 'result' => 'not speaking' ) );
					break;
				}
				
				$speaker->data->time_stop = $this->time();
				$this->filters( 'sd_mt_update_speaker', $speaker );
				
				$speaker_list->data->current_speaker = -1;
				$speaker_list->data->speaker_agenda_item_id = -1;
				$this->filters( 'sd_mt_update_speaker_list', $speaker_list );

				$response['result'] = 'ok';
				break;
			case 'save_order':
				foreach( $_POST['order'] as $index => $speaker_id )
				{
					$speaker = $this->filters( 'sd_mt_get_speaker', $speaker_id );
					$speaker->data->order = $index;
					$this->filters( 'sd_mt_update_speaker', $speaker );
				}
				$response['result'] = 'ok';
				break;
		}
		echo json_encode( $response );
		die();
	}

	public function ajax_user()
	{
		$response = array();
		switch( $_POST['type'] )
		{
			case 'get_current_speaker' :
				$options = array(
					'speaker_display_id' => $_POST[ 'speaker_display_id' ],
					'speaker_list_id' => $_POST[ 'speaker_list_id' ],
					'display_format_id' => $_POST[ 'display_format_id' ],
				);
				$display = $this->display_current_speaker( $options );
				if ( $display === false )
					break;
				$response['data'] = $display;
				
				SD_Meeting_Tool::optimize_response( $response, $_POST['hash'] );
				
				// Someone new must be speaking in order for us to return more info about the speaker.
				if ( isset( $response['data'] ) )
				{
					$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $options[ 'speaker_list_id' ] );
					$speaker_id = $speaker_list->data->current_speaker;
					// Someone has to be speaking in order to return the time left.
					if ( $speaker_id > 0 )
					{
						$speaker = $this->filters( 'sd_mt_get_speaker', $speaker_id );
						if ( $speaker->data->time_to_speak > 0 )
						{
							$response['time_left'] = $speaker->data->time_to_speak -
								( $this->time() - $speaker->data->time_start );
						}
						else
						{
							$response['time_left'] = 0;
						}
					}
				}

				break;
			case 'get_current_speaker_list' :
				$options = array(
					'speaker_list_id' => $_POST[ 'speaker_list_id' ],
					'display_format_id' => $_POST[ 'display_format_id' ],
				);
				$display = $this->display_current_speaker_list( $options );
				if ( $display === false )
					break;
				$response['data'] = $display;
				SD_Meeting_Tool::optimize_response( $response, $_POST['hash'] );
				break;
		}
		echo json_encode( $response );
		die();
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------

	public function sd_mt_create_speaker( $SD_Meeting_Tool_Speaker )
	{
		$data = $this->sql_encode( $SD_Meeting_Tool_Speaker->data );
		$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_speakers`
			(`speaker_list_id`, `agenda_item_id`, `data`)
			VALUES
			('". $SD_Meeting_Tool_Speaker->data->speaker_list_id ."',
			'". $SD_Meeting_Tool_Speaker->data->agenda_item_id ."',
			'" . $data . "')
		";
		$SD_Meeting_Tool_Speaker->id = $this->query_insert_id( $query );
		return $SD_Meeting_Tool_Speaker;
	}
	
	public function sd_mt_create_speaker_list( $SD_Meeting_Tool_Speaker_List )
	{
		global $blog_id;
		
		$data = $this->sql_encode( $SD_Meeting_Tool_Speaker_List->data );
		$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_speaker_lists`
			(`blog_id`, `data`)
			VALUES
			('". $blog_id ."', '" . $data . "')
		";
		$SD_Meeting_Tool_Speaker_List->id = $this->query_insert_id( $query );
		return $SD_Meeting_Tool_Speaker_List;
	}
	
	public function sd_mt_delete_speaker( $SD_Meeting_Tool_Speaker )
	{
		$speaker_parent = 
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_speakers`
			WHERE `id` = '" . $SD_Meeting_Tool_Speaker->id . "'
		";
		$this->query( $query );

		// Find all the speakers that have this speaker as a parent.
		// Means we'll first have to find all speakers for this list and the agenda item id.
		$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $SD_Meeting_Tool_Speaker->data->speaker_list_id );
		$speakers = $this->filters( 'sd_mt_get_speakers', $speaker_list, $SD_Meeting_Tool_Speaker->data->agenda_item_id );
		foreach( $speakers as $speaker )
		{
			if ( $speaker->data->parent == $SD_Meeting_Tool_Speaker->id )
			{
				$speaker->data->parent = $SD_Meeting_Tool_Speaker->data->parent;
				$this->filters( 'sd_mt_update_speaker', $speaker );
			}
		}
	}
	
	public function sd_mt_delete_speaker_display( $SD_Meeting_Tool_Speaker_Display )
	{
		global $blog_id;
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_speaker_displays`
			WHERE `id` = '" . $SD_Meeting_Tool_Speaker_Display->id . "'
			AND `blog_id` = '$blog_id'
		";
		$this->query( $query );
	}
	
	public function sd_mt_delete_speaker_list( $SD_Meeting_Tool_Speaker_List )
	{
		global $blog_id;
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_speaker_lists`
			WHERE `id` = '" . $SD_Meeting_Tool_Speaker_List->id . "'
			AND `blog_id` = '$blog_id'
		";
		$this->query( $query );

		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_speakers`
			WHERE `speaker_list_id` = '" . $SD_Meeting_Tool_Speaker_List->id . "'
		";
		$this->query( $query );
	}
	
	public function sd_mt_empty_speaker_list( $SD_Meeting_Tool_Speaker_List )
	{
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_speakers`
			WHERE `speaker_list_id` = '" . $SD_Meeting_Tool_Speaker_List->id . "'
		";
		$this->query( $query );
	}
	
	public function sd_mt_get_all_speaker_displays()
	{
		global $blog_id;
		$query = "SELECT id FROM `".$this->wpdb->base_prefix."sd_mt_speaker_displays` WHERE `blog_id` = '$blog_id'";
		$results = $this->query( $query );
		$rv = array();
		
		foreach( $results as $result )
			$rv[ $result['id'] ] = $this->sd_mt_get_speaker_display( $result['id'] );
		
		return SD_Meeting_Tool::sort_data_array( $rv, 'name' );
	}
	
	public function sd_mt_get_all_speaker_lists()
	{
		global $blog_id;
		$query = "SELECT id FROM `".$this->wpdb->base_prefix."sd_mt_speaker_lists` WHERE `blog_id` = '$blog_id'";
		$results = $this->query( $query );
		$rv = array();
		
		foreach( $results as $result )
			$rv[ $result['id'] ] = $this->sd_mt_get_speaker_list( $result['id'] );

		return $rv;
	}
	
	public function sd_mt_get_speaker( $SD_Meeting_Tool_Speaker_id )
	{
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_speakers` WHERE `id` = '".$SD_Meeting_Tool_Speaker_id."'";
		$result = $this->query_single( $query );
		
		if ( $result === false )
			return false;
		
		return $this->sql_to_speaker( $result );
	}
	
	public function sd_mt_get_speaker_display( $SD_Meeting_Tool_Speaker_Display )
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_speaker_displays` WHERE `id` = '$SD_Meeting_Tool_Speaker_Display' AND `blog_id` = '$blog_id'";
		$result = $this->query_single( $query );
		if ( $result === false )
			return false;
		
		return $this->sql_to_speaker_display( $result );
	}
	
	public function sd_mt_get_speaker_list( $SD_Meeting_Tool_Speaker_List )
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_speaker_lists` WHERE `id` = '$SD_Meeting_Tool_Speaker_List' AND `blog_id` = '$blog_id'";
		$result = $this->query_single( $query );
		if ( $result === false )
			return false;

		return $this->sql_to_speaker_list( $result );
	}
	
	public function sd_mt_get_speakers( $SD_Meeting_Tool_Speaker_List, $agenda_item_id )
	{
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_speakers` WHERE `speaker_list_id` = '".$SD_Meeting_Tool_Speaker_List->id."' AND `agenda_item_id` = '$agenda_item_id'";
		$results = $this->query( $query );
		
		$rv = array();
		foreach( $results as $result )
			$rv[ $result['id'] ] = $this->sql_to_speaker( $result );
		
		return SD_Meeting_Tool::sort_data_array( $rv, 'order' );
	}
	
	public function sd_mt_update_speaker_display( $SD_Meeting_Tool_Speaker_Display )
	{
		global $blog_id;
		$data = $this->sql_encode( $SD_Meeting_Tool_Speaker_Display->data );
		
		if ( $SD_Meeting_Tool_Speaker_Display->id === null )
		{
			$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_speaker_displays`
				(`blog_id`, `data`)
				VALUES
				('". $blog_id ."', '" . $data . "')
			";
			$SD_Meeting_Tool_Speaker_Display->id = $this->query_insert_id( $query );
		}
		else
		{
			$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_speaker_displays`
				SET
				`data` = '" . $data. "'
				WHERE `id` = '" . $SD_Meeting_Tool_Speaker_Display->id . "'
				AND `blog_id` = '$blog_id'
			";
		
			$this->query( $query );
		}
		return $SD_Meeting_Tool_Speaker_Display;
	}

	public function sd_mt_update_speaker_list( $SD_Meeting_Tool_Speaker_List )
	{
		global $blog_id;
		$data = $this->sql_encode( $SD_Meeting_Tool_Speaker_List->data );
		 
		$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_speaker_lists`
			SET
			`data` = '" . $data. "'
			WHERE `id` = '" . $SD_Meeting_Tool_Speaker_List->id . "'
			AND `blog_id` = '$blog_id'
		";
		
		$this->query( $query );
	}

	public function sd_mt_update_speaker( $SD_Meeting_Tool_Speaker )
	{
		$data = $this->sql_encode( $SD_Meeting_Tool_Speaker->data );
		 
		$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_speakers`
			SET
			`data` = '" . $data. "',
			`speaker_list_id` = '" . $SD_Meeting_Tool_Speaker->data->speaker_list_id . "',
			`agenda_item_id` = '" . $SD_Meeting_Tool_Speaker->data->agenda_item_id . "'
			WHERE `id` = '" . $SD_Meeting_Tool_Speaker->id . "'
		";

		$this->query( $query );
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------
	
	private function delete_cache( $SD_MT_Speaker_List )
	{
		$files = glob( $this->cache_directory() . 'speakers_' . $SD_MT_Speaker_List . '_*' );
		foreach( $files as $file )
			unlink( $file );
	}
	
	/**
		@brief		Converts a time string, [HH:]MM:SS, to a bunch of seconds.
		
		If the time cannot be parse, a default value of 30 will be returned.
		
		@param		string		$string		String to be converted.
		@return		Number of seconds in that string.
	**/
	private function time_to_seconds( $string )
	{
		if ( substr_count( $string, ':' ) < 1 )
			$string = '00:' . $string;
		if ( substr_count( $string, ':' ) < 2 )
			$string = '00:' . $string;
		$time = strtotime( $string );
		$time = $time - strtotime( date('Y-m-d 00:00:00') );
		
		// Bullshit value? Assume 30.
		if ( $time < 0 )
			$time = 30;
		return $time;
	}
	
	/**
		Converts a bunch of seconds to a [HH:]MM:SS time.
		@param	$seconds	Seconds to convert.
		@return				A string in [HH:]MM:SS format.
	**/
	private function seconds_to_time( $seconds )
	{
		if ( $seconds > 3600 )
		{
			$hours = floor( $seconds / 3600 );
			$minutes = ($seconds - ( $hours * 3600 ) ) / 60;
			return sprintf( '%1$01d:%2$02d:%3$02d', $hours , $minutes, $seconds % 60 );
		}
		else
			return sprintf( '%1$02d:%2$02d', $seconds / 60, $seconds % 60 );
	}
	
	private function sql_to_speaker( $sql )
	{
		$speaker = new SD_Meeting_Tool_Speaker();
		$speaker->id = $sql[ 'id' ];
		$speaker->data = (object) array_merge( (array)$speaker->data, (array)$this->sql_decode( $sql['data'] ) );
		return $speaker;
	}
	
	private function sql_to_speaker_display( $sql )
	{
		$display = new SD_Meeting_Tool_Speaker_Display();
		$display->id = $sql[ 'id' ];
		$display->data = (object) array_merge( (array)$display->data, (array)$this->sql_decode( $sql['data'] ) );
		return $display;
	}
	
	private function sql_to_speaker_list( $sql )
	{
		$list = new SD_Meeting_Tool_Speaker_List();
		$list->id = $sql[ 'id' ];
		$list->data = (object) array_merge( (array)$list->data, (array)$this->sql_decode( $sql['data'] ) );
		return $list;
	}
	
	/**
		Convert an array of speakers to html.
		
		@param		$speaker_list		A speaker list object.
		@param		$speakers			Array of {SD_Meeting_Tool_Speaker}s to be convert into a html list.
		
		@return		A HTML string containing the list of speakers.
	**/
	private function speakers_to_html( $speaker_list, $speakers )
	{
		$rv = '';
		
		// Insert the speakers into a tree.
		require_once( 'include/sd_tree.php' );
		$tree = new sd_tree();
		foreach( $speakers as $speaker )
		{
			$speaker_id = intval( $speaker->id );
			$parent = $speaker->data->parent > 0 ? $speaker->data->parent : null ;
			$tree->add( $speaker_id, $speaker, $parent );
		}
		
		$options = new stdClass();
		$options->speaker_list = $speaker_list;
		$options->tree = $tree;
		$options->depth = 1;
		$options->keys = $tree->get_subnodes();
		
		$rv .= '<ul class="speaker_group speaker_group_depth_1">' . $this->display_speakers( $options ) .'</ul>';
		
		return $rv;
	}
	
	private function display_speakers( $options )
	{
		$this->load_language();
		$rv = '';
		$tree = $options->tree;
		foreach( $options->keys as $key )
		{
			$speaker = $tree->get_data( $key );

			$rv .= $this->display_speaker( $options->speaker_list, $speaker );
						
			$subnodes = $tree->get_subnodes( $key );
			if ( count( $subnodes ) > 0 )
			{
				$rv .= '<ul class="speaker_group speaker_group_depth_">';
				foreach( $subnodes as $subnode )
				{
					$temp_options = clone( $options );
					$temp_options->keys = array($subnode);
					$temp_options->depth++;
					$rv .= $this->display_speakers( $temp_options );
				}
				$rv .= '</ul>';
			}
			$rv .= '</li>';
		}
		return $rv;
	}
	
	private function display_speaker( $speaker_list, $speaker )
	{
		$rv = '';
		$classes = array( 'speaker' );
		$rows = array(); 
		$form = $this->form();
		$data = $speaker->data;		// Convenience. 

		$rows[] = '
			<div class="header">
				<div class="quick_add" title="' . $this->_( 'Quick-add this speaker again' ) . '">+</div>
				<div class="participant_name">' . $speaker->participant_name . '</div>
			</div>
		';

		$rows[] = '<div class="extra_rows">';
		
		$inputs = array(
			'time' => array(
				'name' => 'time',
				'type' => 'text',
				'title' => $this->_( 'Speaker time' ),
				'size' => 7,
				'maxlength' => 7,
				'value' => $this->seconds_to_time( $data->time_to_speak ),
				'css_class' => 'time',
			),
			'time_add' => array(
				'name' => 'time_add',
				'type' => 'submit',
				'value' => $this->_( '+' ),
				'title' => $this->_( 'Add 30 seconds' ),
				'css_class' => 'time_add button-secondary',
			),
			'time_subtract' => array(
				'name' => 'time_subtract',
				'type' => 'submit',
				'value' => $this->_( '-' ),
				'title' => $this->_( 'Remove 30 seconds' ),
				'css_class' => 'time_subtract button-secondary',
			),
			'time_start' => array(
				'name' => 'time_start',
				'type' => 'submit',
				'value' => $this->_( 'Start' ),
				'css_class' => 'time_start button-secondary',
			),
			'time_left' => array(
				'name' => 'time_left',
				'type' => 'text',
				'title' => $this->_( 'Time left' ),
				'size' => 6,
				'maxlength' => 6,
				'value' => '',
				'css_class' => 'time_left',
			),
			'time_stop' => array(
				'name' => 'time_stop',
				'type' => 'submit',
				'value' => $this->_( 'Stop' ),
				'css_class' => 'time_stop button-secondary',
			),
			'time_restop' => array(
				'name' => 'time_restop',
				'type' => 'submit',
				'value' => $this->_( 'Restop' ),
				'title' => $this->_( 'Update the time spoken of the participant' ),
				'css_class' => 'time_restop button-secondary',
			),
		);

		$classes[] = 'clickable';
		$controls_started = '
			<div class="time_controls">
				<div class="time_controls_started screen-reader-text">
					<div class="time_left ">
						' . $form->make_input( $inputs['time_left'] ) . '
					</div>
					<div class="time_stop">
						' . $form->make_input( $inputs['time_stop'] ) . '
					</div>
				</div>
			</div>
		';
		if ( $data->time_start !== false )
		{
			if ( $data->time_stop !== false )
			{
				$classes[] = 'spoken';
				
				$rows[] .= '
					<div class="time_control time_restop">
						' . $form->make_input( $inputs['time_restop'] ) . '
					</div>
				';
				
				$time_spoken = $this->seconds_to_time( $data->time_stop - $data->time_start );
				$time_to_speak = $this->seconds_to_time( $data->time_to_speak );
				$time_start = date('H:i', $data->time_start );
				$time_stop = date('H:i', $data->time_stop );
				$rows[] .= sprintf( '%s / %s / %s / %s',
					$time_start,
					$time_stop,
					$time_to_speak,
					$time_spoken
				);
			}
			else
			{
				$classes[] = 'speaking';
				$rows[] = $controls_started;
			}
		}
		else
		{
			$classes[] = 'sortable';

			$rows[] = '
				<div class="time_controls time_controls_stopped">
					<div class="time_control time_subtract">
						' . $form->make_input( $inputs['time_subtract'] ) . '
					</div>
					<div class="time_control time">
						' . $form->make_input( $inputs['time'] ) . '
					</div>
					<div class="time_control time_add">
						' . $form->make_input( $inputs['time_add'] ) . '
					</div>
					<div class="time_control time_start">
						' . $form->make_input( $inputs['time_start'] ) . '
					</div>
				</div>
			';

			$rows[] = $controls_started;
		}
		
		$rows[] = '</div>';
		$rows[] = '</div>';
		
		$classes = implode( ' ', $classes );
		$rv .= '<li speaker_id="' . $speaker->id. '" participant_id="' . $speaker->data->participant_id . '" class="' . $classes . ' speaker speaker_id_' . $speaker->id. '">';
		$rv .= '<div class="' . $classes . ' speaker speaker_id_' . $speaker->id. '">';
		$rv .= implode( "\n", $rows );
		return $rv;
	}
	
	private function get_admin_agenda( $speaker_list )
	{
		$form = $this->form();
		$inputs = array(
			'agenda_items' => array(
				'name' => 'agenda_items',
				'type' => 'select',
				'label' => $this->_( 'Agenda item'),
				'options' => array(),
			),
		);

		// Fill the agenda items
		$agenda = $this->filters( 'sd_mt_get_agenda', $speaker_list->data->agenda_id );
		$current_item_id = $agenda->data->current_item_id;
		
		foreach( $agenda->items as $item )
		{
			$item_data = array();
			
			if ( $item->id == $current_item_id )
				$item_data[] = $this->_( 'Current' );
			
			$selected_item = ( $speaker_list->data->speaker_agenda_item_id == $item->id );
			if ( $selected_item )
				$item_data[] = $this->_( 'Speaking' );
			
			// Count the speakers
			$item_speakers = $this->filters( 'sd_mt_get_speakers', $speaker_list, $item->id );
			$count = count( $item_speakers );
			if ( $count > 0 )
			{
				if ( $count == 1 )
					$item_data[] = count( $item_speakers ) . ' ' . $this->_( 'speaker' );
				else
					$item_data[] = count( $item_speakers ) . ' ' . $this->_( 'speakers' );
			}
			
			if ( count( $item_data ) > 0 )
				$item_data = implode( ', ', $item_data ) . ': ';
			else
				$item_data = '';
			
			// We want a counter of how many speakers each item has.
			$inputs[ 'agenda_items' ]['options'][ $item->id ] = $item_data . $item->data->name;
		}
		return $form->make_input( $inputs[ 'agenda_items' ] );
	}
	
	private function get_preferred_display_format( $SD_Speaker_List, $list = null )
	{
		if ( $SD_Speaker_List->data->display_format_id > 0 )
		{
			$id = $SD_Speaker_List->data->display_format_id;
		}
		else
		{
			if ( $list === null )
				$list = $this->filters( 'sd_mt_get_list', $SD_Speaker_List->data->list_id );
			$id = $list->data->display_format_id;
		}
		return $this->filters( 'sd_mt_get_display_format', $id );
	}
	
	private function get_sorted_speaker_list( $SD_Meeting_Tool_Speaker_List, $agenda_item_id, $SD_Meeting_Tool_Display_Format )
	{
		$rv = array();
		$speakers = $this->filters( 'sd_mt_get_speakers', $SD_Meeting_Tool_Speaker_List, $agenda_item_id );
		foreach( $speakers as $speaker_id => $speaker )
		{
			$speaker->participant = $this->filters( 'sd_mt_get_participant', $speaker->data->participant_id );
			$speaker->participant_name = $this->filters( 'sd_mt_display_participant', $speaker->participant, $SD_Meeting_Tool_Display_Format );
			$rv[ $speaker->data->order ] = $speaker;
		}
		ksort( $rv );
		return $rv;
	}
	
	/**
		@brief		Returns the speaker list log of a speaker list.
		@param		$speaker_list
					The speaker list object, whose log we will display.
		
		@return		The speaker list log, in HTML format.
	**/
	public function get_speaker_list_log( $speaker_list )
	{
		$agenda = $this->filters( 'sd_mt_get_agenda', $speaker_list->data->agenda_id );
		$rv = '';

		foreach( $agenda->items as $agenda_item )
		{
			$rv .= '<h3>' . $agenda_item->data->name . '</h3>';
			
			$speakers = $this->filters( 'sd_mt_get_speakers', $speaker_list, $agenda_item->id );
			$speakers = SD_Meeting_Tool::sort_data_array( $speakers, 'order' );
			// Put the speakers in a tree. K-i-s-s-i-n-g...
			require_once( 'include/sd_tree.php' );
			$tree = new sd_tree();
			foreach( $speakers as $speaker )
			{
				$speaker_id = intval( $speaker->id );
				$parent = $speaker->data->parent > 0 ? $speaker->data->parent : null ;
				$tree->add( $speaker_id, $speaker, $parent );
			}
			$options = new stdClass();
			$options->speaker_list = $speaker_list;
			$options->display_format = $this->get_preferred_display_format( $speaker_list );
			$options->tree = $tree;
			$options->depth = 1;
			$options->keys = $tree->get_subnodes();
				
			$rv .= '<ul class="log">';
			$rv .= $this->display_logged_speakers( $options );
			$rv .= '</ul>';
		}
		return $rv;
	}
		
	private function display_current_speaker( $options )
	{
		$speaker_display = $this->filters( 'sd_mt_get_speaker_display', $options[ 'speaker_display_id' ] );
		if ( $speaker_display === false )
			return;
		
		$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $options[ 'speaker_list_id' ] );
		if ( $speaker_list === false )
			return;
		
		$display_format = $options['display_format_id'];
		$display_format = $this->filters( 'sd_mt_get_display_format', $display_format );
		if ( $display_format === false )
			return;
		
		$speaker = $speaker_list->data->current_speaker;
		$speaker = $this->filters( 'sd_mt_get_speaker', $speaker );
		if ( $speaker === false )
			return;
			
		$participant = $speaker->data->participant_id;
		$participant = $this->filters( 'sd_mt_get_participant', $participant );
		if ( $participant === false )
			return;
		
		$replace = array(
			'#participant#' => $this->filters( 'sd_mt_display_participant', $participant, $display_format ),
			'#time_left#' => '<span class="time_left">00:00</span>',
		);
		
		$format = $speaker_display->data->format;
		$format = str_replace( array_keys( $replace ), array_values( $replace ), $format );
		return $format;
	}
	
	/**
		Returns the current speaker list as HTML.
		
		The options are:
		- @b display_format		The ID of the display format to use.
		- @b speaker_list		The ID of the speaker list.
		
		@param	$options	Array of options.
		@return				The speaker list as HTMl.
	**/
	private function display_current_speaker_list( $options )
	{
		$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $options[ 'speaker_list_id' ] );
		if ( $speaker_list === false )
			return;
		
		$display_format = $options['display_format_id'];
		$display_format = $this->filters( 'sd_mt_get_display_format', $display_format );
		if ( $display_format === false )
			return;
		
		$agenda = $this->filters( 'sd_mt_get_agenda', $speaker_list->data->agenda_id );
		if ( $agenda->data->current_item_id < 1 )
			return;

		$speakers = $this->get_sorted_speaker_list( $speaker_list, $agenda->data->current_item_id, $display_format );
		
		// Insert the speakers into a tree.
		require_once( 'include/sd_tree.php' );
		$tree = new sd_tree();
		foreach( $speakers as $speaker )
		{
			$speaker_id = intval( $speaker->id );
			$parent = $speaker->data->parent > 0 ? $speaker->data->parent : null ;
			$tree->add( $speaker_id, $speaker, $parent );
		}
		
		// And now display that tree.
		$options = new stdClass();
		$options->display_format = $display_format;
		$options->depth = 1;
		$options->keys = $tree->get_subnodes();
		$options->speaker_list = $speaker_list;
		$options->tree = $tree;
		$options->counter_has_not_spoken = 1;
		$options->counter_has_spoken = 1;
		
		$rv = '<ul class="speaker_group speaker_group_depth_1">' . $this->display_current_speakers( $options ) .'</ul>';
		
		return $rv;
	}

	private function display_current_speakers( &$options )
	{
		$rv = '';
		$tree = $options->tree;		// Convenience
		foreach( $options->keys as $key )
		{
			$speaker = $tree->get_data( $key );
			
			$p_id = $speaker->data->participant_id;
			$participant = $this->filters( 'sd_mt_get_participant', $p_id );
			
			$classes = array();
			$classes[] = 'participant';
			$classes[] = 'participant_id_' . $p_id;

			if ( $speaker->data->time_start < 1 )
			{
				$classes[] = 'has_not_spoken';
				$classes[] = 'has_not_spoken_' . $options->counter_has_not_spoken++;
			}
			else
			{
				if ( $speaker->data->time_stop < 1 )
					$classes[] = 'speaking';
				else
				{
					$classes[] = 'has_spoken';
					$classes[] = 'has_spoken_' . $options->counter_has_spoken++;
				}
			}
			
			$classes = implode( ' ', $classes );
			$rv .= '<li class="' . $classes . '">';
			$rv .= '<div class="' . $classes . '">';
			
			$rv .= $this->filters( 'sd_mt_display_participant', $participant, $options->display_format );

			$rv .= '</div>';
						
			$subnodes = $tree->get_subnodes( $key );
			if ( count( $subnodes ) > 0 )
			{
				$rv .= '<ul class="speaker_group speaker_group_depth_">';
				foreach( $subnodes as $subnode )
				{
					$temp_options = clone( $options );
					$temp_options->keys = array($subnode);
					$temp_options->depth++;
					$rv .= $this->display_current_speakers( $temp_options );
					$options->counter_has_not_spoken = $temp_options->counter_has_not_spoken;
				}
				$rv .= '</ul>';
			}
			$rv .= '</li>';
		}
		return $rv;
	}
	
	private function display_logged_speakers( $options )
	{
		$rv = '';
		$tree = $options->tree;		// Convenience
		foreach( $options->keys as $key )
		{
			$speaker = $tree->get_data( $key );
			
			$p_id = $speaker->data->participant_id;
			$participant = $this->filters( 'sd_mt_get_participant', $p_id );
			
			$rv .= '<li>';
			$rv .= '<div>';
			
			$display = $this->filters( 'sd_mt_display_participant', $participant, $options->display_format );
			
			$columns = 5;
			$string = array();
			for( $counter=0; $counter < $columns; $counter ++ )
				$string[] = '<span class="column">%s';
			$string = implode( '<span class="comma">,</span></span>', $string ) . '</span>';
			
			$rv .= sprintf( $string,
				$display,
				$this->seconds_to_time( $speaker->data->time_to_speak ),
				date( 'H:i:s', $speaker->data->time_start ),
				date( 'H:i:s', $speaker->data->time_stop ),
				$this->seconds_to_time( $speaker->data->time_stop - $speaker->data->time_start )
			);

			$rv .= '</div>';
						
			$subnodes = $tree->get_subnodes( $key );
			if ( count( $subnodes ) > 0 )
			{
				$rv .= '<ul>';
				foreach( $subnodes as $subnode )
				{
					$temp_options = clone( $options );
					$temp_options->keys = array($subnode);
					$temp_options->depth++;
					$rv .= $this->display_logged_speakers( $temp_options );
				}
				$rv .= '</ul>';
			}
			$rv .= '</li>';
		}
		return $rv;
	}

	/**
		Write the current speaker list and speaker to the cache.

		@param	$SD_MT_Speaker_List		Speaker list data to write.
	**/
	private function write_cache( $SD_MT_Speaker_List )
	{
		// The speaker list
		$file = $this->cache_directory() . $this->cache_file( 'speakers_' . $SD_MT_Speaker_List->id );
		$options = array( 'agenda' => $SD_MT_Agenda );
		$data = $this->display_agenda( $options );
		file_put_contents( $file, $data );
		
		return;
		
		// And the speaker
		$tf = array( true, false );
		foreach( $tf as $link )
			foreach( $tf as $number )
				foreach( $tf as $text )
				{
					$file = $this->cache_directory() . $this->cache_file( 'agendas_' . $SD_MT_Agenda->id . '_current_item_'
						. intval($link)
						. intval($number)
						. intval($text)
					);
					$data = $this->display_agenda_item( array(
						'agenda' => $SD_MT_Agenda,
						'link' => $link,
						'number' => $number,
						'text' => $text,
					) );
					file_put_contents( $file, $data );
				}
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Shortcodes
	// --------------------------------------------------------------------------------------------

	/**
		Shows the current speaker.
		
		@par		Attributes
		
		- speaker_list		Speaker list ID from which to get the current speaker. Required.
		- autorefresh		Refresh the agenda automatically using javacsript. Default "yes".
		
		@param		$attr		Attributes array.
		@return					Speaker HTML string to display.
	**/
	public function shortcode_current_speaker( $attr )
	{
		if ( !isset( $attr['speaker_list_id'] ) )
			return;
		
		$options = $attr;

		$display = $this->display_current_speaker( $options );
		
		if ( $display === false )
			return; 
		
		$div_id = rand(0, PHP_INT_MAX);
		
		$rv = '<div id="current_speaker_' . $div_id . '" class="speaker_list_'.$attr['speaker_list_id'].' current_speaker">
			' . $display . '
			</div>';		
		
		if ( isset( $attr['autorefresh'] ) && $attr['autorefresh'] == 'yes' )
		{
			$rv .= '
				<script type="text/javascript" src="'. $this->paths['url'] . '/js/sd_meeting_tool_speakers.js' .'"></script>
				<script type="text/javascript" >
					jQuery(document).ready(function($){
						var current_speaker_' . $div_id . ' = new sd_meeting_tool_speakers();
						current_speaker_' . $div_id . '.init_current_speaker({
							"action" : "ajax_sd_mt_speakers_user", 
							"ajaxurl" : "'. admin_url('admin-ajax.php') . '",
							"div_id" : "' . $div_id . '",
							"display_format_id" : "' . $attr['display_format_id'] . '",
							"speaker_display_id" : "' . $attr['speaker_display_id'] . '",
							"speaker_list_id" : "'. $attr['speaker_list_id'] . '",
						});
					});
				</script>
			';
		}
		return $rv;
	}
	
	/**
		Shows the current speaker list.
		
		@par		Attributes
		
		- speaker_list		Speaker list ID from which to get the current speaker. Required.
		
		@param		$attr		Attributes array.
		@return					Speaker list HTML string to display.
	**/
	public function shortcode_current_speaker_list( $attr )
	{
		if ( !isset( $attr['speaker_list_id'] ) )
			return;
		
		$options = $attr;

		$display = $this->display_current_speaker_list( $options );
		
		if ( $display === false )
			return; 
		
		$div_id = rand(0, PHP_INT_MAX);
		
		$rv = '<div id="current_speaker_list_' . $div_id . '" class="speaker_list_'.$attr['speaker_list_id'].' current_speaker_list">
			' . $display . '
			</div>		
		
			<script type="text/javascript" src="'. $this->paths['url'] . '/js/sd_meeting_tool_speakers.js' .'"></script>
			<script type="text/javascript" >
					jQuery(document).ready(function($){
						var current_speaker_list_' . $div_id . ' = new sd_meeting_tool_speakers();
						current_speaker_list_' . $div_id . '.init_current_speaker_list({
							"action" : "ajax_sd_mt_speakers_user", 
							"ajaxurl" : "'. admin_url('admin-ajax.php') . '",
							"div_id" : "' . $div_id . '",
							"display_format_id" : "'. $attr['display_format_id'] . '",
							"speaker_list_id" : "'. $attr['speaker_list_id'] . '",
						});
					});
			</script>
		';

		return $rv;
	}	

	/**
		@brief		Shows a speaker list log for a speaker list.
		
		@par		Attributes
		
		- speaker_list_id	ID of speaker list.
		
		@param		$attr		Attributes array.
		@return					Speaker list log HTML.
	**/
	public function shortcode_speaker_list_log( $attr )
	{
		if ( !isset( $attr['speaker_list_id'] ) )
			return;
		
		$speaker_list_id = intval( $attr[ 'speaker_list_id' ] );
		$speaker_list = $this->filters( 'sd_mt_get_speaker_list', $speaker_list_id );
		if ( $speaker_list === false )
			return;
		
		$this->load_language();
		
		return $this->get_speaker_list_log( $speaker_list );
	}
	
}
$SD_Meeting_Tool_Speakers = new SD_Meeting_Tool_Speakers();

// --------------------------------------------------------------------------------------------
// ----------------------------------------- class SD_Meeting_Tool_Speaker_List
// --------------------------------------------------------------------------------------------
/**
	@brief		Speaker list class.
	@see		SD_Meeting_Tool_Speakers
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Speaker_List
{
	/**
		Serialized data.
		Contains:
		
		- @b agenda ID of agenda to base speakers on.
		- @b display_format ID of display format to use, if other than the list's default display format.
		- @b current_speaker ID of current speaker.
		- @b default_time_reply Default time, in seconds, of a reply.
		- @b default_time_speaker Default time, in seconds, of a speaker.
		- @b display_format ID of display format to override list with. < 0 if none.
		- @b list List ID of list of available speakers.
		- @b list_sort ID of sort to override with. < 0 if none.
		- @b speaker_agenda_item_id If someone is speaking, the agenda item ID is here.
		
		@var	$data
	**/ 
	public $data;

	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->agenda_id = -1;
		$this->data->current_speaker = -1;
		$this->data->default_time_reply = 30;
		$this->data->default_time_speaker = 60 * 2;
		$this->data->display_format_id = false;
		$this->data->list_id = false;
		$this->data->list_sort_id = false;
		$this->data->speaker_agenda_item_id = false;
	}
}

// --------------------------------------------------------------------------------------------
// ----------------------------------------- class SD_Meeting_Tool_Speaker
// --------------------------------------------------------------------------------------------
/**
	@brief		Speaker class.
	@see		SD_Meeting_Tool_Speaker_List
	@see		SD_Meeting_Tool_Speakers
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Speaker
{
	/**
		Serialized data.
		Contains:
		
		- @b agenda_item_id ID of agenda item this speaker is bound to. 
		- @b order Order in speaker list. 
		- @b parent Speaker ID of speaker we are replying to. 
		- @b participant_id ID of speaker as a participant.
		- @b speaker_list_id ID of speaker list this speaker belongs to.
		- @b time_start Unix time of when the speaker started speaking.
		- @b time_stop Unix time of when the speaker stopped speaking.
		- @b time_to_speak How long, in seconds, the speaker spoke.
		
		@var	$data
	**/ 
	public $data;
	
	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->agenda_item_id = false;
		$this->data->order = 1;
		$this->data->parent = false;
		$this->data->participant_id = -1;
		$this->data->speaker_list_id = -1;
		$this->data->time_start = false;
		$this->data->time_stop = false;
		$this->data->time_to_speak = 30;
	}
}

// --------------------------------------------------------------------------------------------
// ----------------------------------------- class SD_Meeting_Tool_Speaker_Display
// --------------------------------------------------------------------------------------------
/**
	@brief		Speaker display class.
	@see		SD_Meeting_Tool_Speakers
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Speaker_Display
{
	/**
		Serialized data.
		Contains:
		
		- @b speaker_agenda_item_id If someone is speaking, the agenda item ID is here.
		
		@var	$data
	**/ 
	public $data;

	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->name = "";
		$this->data->format = '<div class="participant">#participant#</div>
<div class="time_left">#time_left#</div>
';
	}
}
