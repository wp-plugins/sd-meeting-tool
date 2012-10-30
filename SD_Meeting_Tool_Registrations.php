<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool Registrations
Plugin URI: https://it.sverigedemokraterna.se
Description: Provides participant registration
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	@page		sd_meeting_tool_registrations					Plugin: Registrations 
	
	@section	sd_meeting_tool_registrations_description		Description
	
	A registration is a multipurpose utility binds together a list, two actions and a user interface. A registration can be configured
	to
	
	- register participants at check in
	- register participants at check out
	- register participant votes at elections  
	
	@section	sd_meeting_tool_registrations_requirements		Requirements
	
	- @ref index
	- @ref SD_Meeting_Tool_Lists
	- @ref SD_Meeting_Tool_Participants
	- One or more registration UIs

	@section	sd_meeting_tool_registrations_installation		Installation
	
	Enable the plugin in Wordpress.
	
	The plugin will then create the following database tables:
	
	- @b prefix_sd_mt_registrations
		Serialized registration data.

	@section	sd_meeting_tool_registrations_usage				Usage
	
	Registrations by themselves do very little other than specificy a list, some actions and provide a place for the selected
	UI to store its settings. It is the selected action(s) that actually do the work.
	
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/

/**
	@brief		Plugin providing participant registrations.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se

	@par		Changelog

	@par		1.1
	- Version bump.
**/
class SD_Meeting_Tool_Registrations
	extends SD_Meeting_Tool_0
	implements interface_SD_Meeting_Tool_Registrations
{
	public function __construct()
	{
		parent::__construct( __FILE__ );

		// Internal filters
		add_filter( 'sd_mt_delete_registration',		array( &$this, 'sd_mt_delete_registration' ) );
		add_filter( 'sd_mt_display_registration',		array( &$this, 'sd_mt_display_registration' ) );
		add_filter( 'sd_mt_get_all_registrations',		array( &$this, 'sd_mt_get_all_registrations' ) );
		add_filter( 'sd_mt_get_registration',			array( &$this, 'sd_mt_get_registration' ) );
		add_filter( 'sd_mt_process_registration',		array( &$this, 'sd_mt_process_registration' ) );
		add_filter( 'sd_mt_update_registration',		array( &$this, 'sd_mt_update_registration' ) );

		// External actions
		add_filter( 'sd_mt_admin_menu',					array( &$this, 'sd_mt_admin_menu' ) );
	}

	public function activate()
	{
		parent::activate();

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_registrations` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `blog_id` int(11) NOT NULL,
		  `data` longtext NOT NULL COMMENT 'Serialized data',
		  PRIMARY KEY (`id`),
		  KEY `blog_id` (`blog_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
		");
	}
	
	public function uninstall()
	{
		parent::uninstall();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_registrations`");
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_admin_menu( $menus )
	{
		$this->load_language();

		$menus[ $this->_('Registrations') ] = array(
			'sd_mt',
			$this->_('Registrations'),
			$this->_('Registrations'),
			'read',
			'sd_mt_registrations',
			array( &$this, 'admin' )
		);
		return $menus;
	}
	
	public function admin()
	{
		$tab_data = array(
			'tabs'		=>	array(),
			'functions' =>	array(),
		);
				
		$tab_data['default'] = 'overview';

		$tab_data['tabs']['overview'] = $this->_( 'Overview');
		$tab_data['functions']['overview'] = 'admin_overview';

		if ( isset( $_GET['tab'] ) )
		{
			if ( $_GET['tab'] == 'register' )
			{
				$tab_data['tabs']['register'] = $this->_( 'Register' );
				$tab_data['functions']['register'] = 'admin_register';
				
				$registration = $this->filters( 'sd_mt_get_registration', $_GET['id'] );
				if ( $registration === false )
					wp_die( $this->_( 'Specified registration does not exist!' ) );
				
				$tab_data['page_titles']['register'] = $this->_( 'Registering: %s', $registration->data->name );
			}

			if ( $_GET['tab'] == 'edit' )
			{
				$tab_data['tabs']['edit'] = $this->_( 'Edit' );
				$tab_data['functions']['edit'] = 'admin_edit';
				
				$registration = $this->filters( 'sd_mt_get_registration', $_GET['id'] );
				if ( $registration === false )
					wp_die( $this->_( 'Specified registration does not exist!' ) );
				
				$tab_data['page_titles']['edit'] = $this->_( 'Editing registration: %s', $registration->data->name );
			}
		}

		$this->tabs($tab_data);
	}
	
	public function admin_overview()
	{
		if ( isset( $_POST['create_registration'] ) )
		{
			$registration = new SD_Meeting_Tool_Registration();
			$registration->data->name = $this->_( 'Registration created %s', $this->now() );
			$registration = $this->filters( 'sd_mt_update_registration', $registration );
			
			$edit_link = add_query_arg( array(
				'tab' => 'edit',
				'id' => $registration->id,
			) );
			
			$this->message( $this->_( 'Registration created! <a href="%s">Edit the newly-created registration</a>.', $edit_link ) );
		}

		if ( isset( $_POST['action_submit'] ) && isset( $_POST['registrations'] ) )
		{
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['registrations'] as $registration_id => $ignore )
				{
					$registration = $this->filters( 'sd_mt_get_registration', $registration_id );
					if ( $registration !== false )
					{
						$this->filters( 'sd_mt_delete_registration', $registration );
						$this->message( $this->_( 'Registration <em>%s</em> deleted.', $registration_id ) );
					}
				}
			}	// delete
		}
		
		$form = $this->form();
		$returnValue = $form->start();
		
		$registrations = $this->filters( 'sd_mt_get_all_registrations', array() );
		
		if ( count( $registrations ) < 1 )
		{
			$this->message( $this->_( 'No registrations found!' ) );
		}
		else
		{
			$t_body = '';
			foreach( $registrations as $registration )
			{
				$input_select_registration = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $registration->data->name,
					'name' => $registration->id,
					'nameprefix' => '[registrations]',
				);
				
				$edit_link = add_query_arg( array(
					'tab' => 'edit',
					'id' => $registration->id,
				) );
				
				// ACTION time.
				$row_actions = array();
				
				// Register
				$register_link = add_query_arg( array(
					'tab' => 'register',
					'id' => $registration->id,
				) );
				$row_actions[] = '<a title="' . $this->_('Use this registration'). '" href="'.$register_link.'">'. $this->_('Register') . '</a>';
				
				// Edit
				$row_actions[] = '<a href="'.$edit_link.'">'. $this->_('Edit') . '</a>';
				
				$row_actions = implode( '&emsp;<span class="sep">|</span>&emsp;', $row_actions );
				
				$info = array();
				if ( $registration->data->action_failure > 0 )
				{
					$action = $this->filters( 'sd_mt_get_action', $registration->data->action_failure ); 
					if ( $action === false )
						$action_name = $this->_('Unknown');
					else 
						$action_name = $action->data->name;
					$info[] = $this->_( 'Action on failure: <em>%s</em>', $action_name ); 
				}

				if ( $registration->data->action_success > 0 )
				{
					$action = $this->filters( 'sd_mt_get_action', $registration->data->action_success ); 
					if ( $action === false )
						$action_name = $this->_('Unknown');
					else 
						$action_name = $action->data->name;
					$info[] = $this->_( 'Action on success: <em>%s</em>', $action_name ); 
				}

				if ( $registration->data->list_id > 0 )
				{
					$list = $this->filters( 'sd_mt_get_list', $registration->data->list_id );
					if ( $list === false )
						$list_name = $this->_('Unknown');
					else 
						$list_name = $list->data->name;
					$info[] = $this->_( 'List: <em>%s</em>', $list_name );
				}

				if ( $registration->data->ui != '' )
				{
					$ui = $this->filters( 'sd_mt_get_registration_ui', $registration->data->ui );
					if ( is_a( $ui, 'SD_Meeting_Tool_Registration_UI' ) )
						$ui_name = $ui->name;
					else 
						$ui_name = $this->_('Unknown');
					$info[] = $this->_( 'User interface: <em>%s</em>', $ui_name ); 
				}
				
				$info = implode( '</div><div>', $info );
				
				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input( $input_select_registration ) . ' <span class="screen-reader-text">' . $form->make_label( $input_select_registration ) . '</span></th>
					<td>
						<div>
							<a title="' . $this->_( 'Use this registration' ) . '"	href="' . $register_link . '">
								' . $registration->data->name . '
							</a>
						</div>
						<div class="row-actions">' . $row_actions . '</a>
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
			
			$returnValue .= '
				<div>
					' . $form->make_label( $input_actions ) . '
					' . $form->make_input( $input_actions ) . '
					' . $form->make_input( $input_action_submit ) . '
				</div>
				<table class="widefat">
					<thead>
						<tr>
							<th class="check-column">' . $form->make_input( $selected ) . '<span class="screen-reader-text">' . $this->_('Selected') . '</span></th>
							<th>' . $this->_('Name') . '</th>
							<th>' . $this->_('Info') . '</th>
						</tr>
					</thead>
					<tbody>
						'.$t_body.'
					</tbody>
				</table>
			';
		}

		// Allow the user to create a new registration
		$input_registration_create = array(
			'type' => 'submit',
			'name' => 'create_registration',
			'value' => $this->_( 'Create a new registration' ),
			'css_class' => 'button-primary',
		);
		
		$returnValue .= '
			<p>
				' . $form->make_input( $input_registration_create ) . '
			</p>
		';

		$returnValue .= $form->stop();
		
		echo $returnValue;
	}
	
	public function admin_edit()
	{
		$id = $_GET['id'];
		$form = $this->form();
		$returnValue = $form->start();

		$inputs = array(
			'name' => array(
				'type' => 'text',
				'name' => 'name',
				'label' => $this->_( 'Name' ),
				'size' => 50,
				'maxlength' => 200,
			),
			'list_id' => array(
				'type' => 'select',
				'name' => 'list_id',
				'label' => $this->_( 'List' ),
				'description' => $this->_( 'Which list to use as the source of acceptable participants.' ),
				'options' => array(),
			),
			'ui' => array(
				'type' => 'select',
				'name' => 'ui',
				'label' => $this->_( 'User interface' ),
				'description' => $this->_( 'Which user interface to use.' ),
				'options' => array(),
			),
			'action_success' => array(
				'type' => 'select',
				'name' => 'action_success',
				'label' => $this->_( 'Success action' ),
				'description' => $this->_( 'Fire this action if registration succeeds.' ),
				'options' => array('' => $this->_( 'None' ) ),
			),
			'action_failure' => array(
				'type' => 'select',
				'name' => 'action_failure',
				'label' => $this->_( 'Failure action' ),
				'description' => $this->_( 'Fire this action if registration fails.' ),
				'options' => array('' => $this->_( 'None' ) ),
			),
		);
		
		if ( isset( $_POST['update'] ) )
		{
			$result = $form->validate_post( $inputs, array_keys( $inputs ), $_POST );

			if ($result === true)
			{
				$registration = $this->filters( 'sd_mt_get_registration', $id );
				$registration->data->name = $_POST['name'];
				$registration->data->action_failure = $_POST['action_failure'];
				$registration->data->action_success = $_POST['action_success'];
				$registration->data->list_id = $_POST['list_id'];
				
				// Need a new UI?
				if ( get_class($registration->data->ui) != $_POST['ui'] )
				{
					$ui_name = $_POST['ui'];
					$ui = new $ui_name();
					$registration->data->ui = $ui;
				}
				else
					$registration->data->ui = $this->filters( 'sd_mt_update_registration_ui', $registration->data->ui );
								
				$this->filters( 'sd_mt_update_registration', $registration );
				
				$this->message( $this->_('The registration has been updated!') );
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}

		$registration = $this->filters( 'sd_mt_get_registration', $id );
		
		// Lists
		$lists = $this->filters( 'sd_mt_get_all_lists', array() );
		foreach( $lists as $list )
			$inputs['list_id']['options'][ $list->id ] = $list->data->name;
		
		// UI
		$uis = $this->filters( 'sd_mt_get_all_registration_uis', array() );
		foreach( $uis as $ui )
			$inputs['ui']['options'][ get_class($ui) ] = $ui->name;
		
		// Actions
		$actions = $this->filters( 'sd_mt_get_all_actions', null );
		foreach( $actions as $action )
		{
			$inputs['action_failure']['options'][ $action->id ] = $action->data->name;
			$inputs['action_success']['options'][ $action->id ] = $action->data->name;
		}
		
		$inputs['name']['value'] = $registration->data->name;
		$inputs['list_id']['value'] = intval( $registration->data->list_id );
		$inputs['action_failure']['value'] = intval( $registration->data->action_failure );
		$inputs['action_success']['value'] = intval( $registration->data->action_success );
		$inputs['ui']['value'] = get_class( $registration->data->ui );
		
		$returnValue .= '<h3>' . $this->_('Registration settings') . '</h3>';

		$returnValue .= '
			' . $form->start() . '
			
			' . $this->display_form_table( $inputs ). '

		';
		
		if ( is_object( $registration->data->ui ) )
		{
			$returnValue .= '<h3>' . $this->_('Settings for %s', $registration->data->ui->name ) . '</h3>';
		
			$returnValue .= $this->filters( 'sd_mt_configure_registration_ui', $registration->data->ui );		
		}
		
		$input_update = array(
			'type' => 'submit',
			'name' => 'update',
			'value' => $this->_( 'Update registration' ),
			'css_class' => 'button-primary',
		);
		
		$returnValue .= '<p>' . $form->make_input( $input_update ) . '</p>';
		
		$returnValue .= $form->stop();
		
		echo $returnValue;
	}
	
	function admin_register()
	{
		$id = $_GET['id'];
		$registration = $this->filters( 'sd_mt_get_registration', $id );
		
		$this->filters( 'sd_mt_process_registration', $registration );
		$returnValue = $this->filters( 'sd_mt_display_registration', $registration );
		
		echo $returnValue; 
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------

 	public function sd_mt_delete_registration( $SD_Meeting_Tool_Registration )
	{
		global $blog_id;
		$id = $SD_Meeting_Tool_Registration->id;
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_registrations` WHERE `id` = '$id' AND `blog_id` = '".$blog_id."'";
		$this->query( $query );
	}
	
	public function sd_mt_get_registration( $registration_id )
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_registrations` WHERE `blog_id` = '".$blog_id."' AND id = '$registration_id'";
		$result = $this->query_single( $query );
		if ( $result === false )
			return false;
		
		return $this->registration_sql_to_object( $result );
	}

	public function sd_mt_get_all_registrations()
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_registrations` WHERE `blog_id` = '".$blog_id."'";
		$results = $this->query( $query );
		
		$returnValue = array();
		foreach( $results as $result )
			$returnValue[ $result['id'] ] = $this->registration_sql_to_object( $result );

		return SD_Meeting_Tool::sort_data_array( $returnValue, 'name' );
	}
	
	public function sd_mt_process_registration( $SD_Meeting_Tool_Registration )
	{
		$ui = $SD_Meeting_Tool_Registration->data->ui;
		if ( ! is_object( $ui ) )
			return $SD_Meeting_Tool_Registration;
		
		$SD_Meeting_Tool_Registration = $this->filters( 'sd_mt_process_registration_ui', $SD_Meeting_Tool_Registration );
		
		if ( $SD_Meeting_Tool_Registration->result !== null )
		{
			// Decide what to do
			$result = $SD_Meeting_Tool_Registration->result;
			
			// What's the point of doing anything if the plugin didn't give us a participant ID to work with?
			if ( ! $result->has_participant_id() )
				return $SD_Meeting_Tool_Registration;
			
			if ( $result->successful() )
				$action = $this->filters( 'sd_mt_get_action', $SD_Meeting_Tool_Registration->data->action_success );
			else
				$action = $this->filters( 'sd_mt_get_action', $SD_Meeting_Tool_Registration->data->action_failure );
				
			if( $action !== false )
			{
				// Trigger this action!
				$action_trigger = new SD_Meeting_Tool_Action_Trigger();
				$action_trigger->action = $action;
				$action_trigger->trigger = $this->filters( 'sd_mt_get_participant', $result->participant_id() );
				do_action( 'sd_mt_trigger_action', $action_trigger );
			}
		}

		return $SD_Meeting_Tool_Registration;
	}
	
	public function sd_mt_display_registration( $SD_Meeting_Tool_Registration )
	{
		$ui = $SD_Meeting_Tool_Registration->data->ui;
		if ( is_object( $ui ) )
			return $this->filters( 'sd_mt_display_registration_ui', $SD_Meeting_Tool_Registration );
	}
	
	public function sd_mt_update_registration( $SD_Meeting_Tool_Registration )
	{
		global $blog_id;
		
		$registration = $SD_Meeting_Tool_Registration;		// Convenience	
		$data = $this->sql_encode( $SD_Meeting_Tool_Registration->data );
		
		if ( $registration->id === null )
		{
			$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_registrations`
				(`blog_id`, `data`)
				VALUES
				('". $blog_id ."', '" . $data . "')
			";
			$registration->id = $this->query_insert_id( $query );
		}
		else
		{
			$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_registrations`
				SET
				`data` = '" . $data. "'
				WHERE `id` = '" . $registration->id . "'
				AND `blog_id` = '" . $blog_id . "'
			";
			$this->query( $query );
		}
		
		return $registration;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------

	/**
		Convert a list row from SQL to an SD_Meeting_Tool_Registration.
		
		@param		$sql		Row from the database as an array.
		@return					A complete SD_Meeting_Tool_Registration object.
	**/ 
	private function registration_sql_to_object( $sql )
	{
		$registration = new SD_Meeting_Tool_Registration();
		$registration->id = $sql['id'];
		$registration->data = (object) array_merge( (array)$registration->data, (array)$this->sql_decode( $sql['data'] ) );
		return $registration;
	}
}
$SD_Meeting_Tool_Registrations = new SD_Meeting_Tool_Registrations();