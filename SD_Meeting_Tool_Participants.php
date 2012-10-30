<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool Participants
Plugin URI: https://it.sverigedemokraterna.se
Description: Provides participant storage and manipulation.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	@page		sd_meeting_tool_participants					Plugin: Participants
	
	@section	sd_meeting_tool_participants_description		Description
	
	Provides participant handling for the other modules.
	
	@section	sd_meeting_tool_participants_requirements		Requirements
	
	None.
	
	@section	sd_meeting_tool_participants_installation		Installation
	
	Enable the plugin in Wordpress.
	
	The plugin will then create the following database tables:
	
	- @b prefix_sd_mt_participants
		Serialized participants.
	
	@section	sd_meeting_tool_participants_usage				Usage
	
	Each participant may have a number of fields, with only one being mandatory: the ID.
	
	The participant table can be sorted by clicking a field heading.

	As of 2011-09-15 participants can only be created by the import function.
	
	@par Importing participants
	
	@sdmt can import participants from spreadsheets. The spreadsheet should have the top row as
	the field names and a new participant on each row below the top one.
	
	There must be at least one field named "ID" and it must contain the unique ID numbers of each participant.
	
	After the spreadsheet has been filled-in it can be copied and pasted into the plugin's import function.
	Existing participants are linked with the participants in the spreadsheet using the ID number.
	
	An option underneath the import box specifies what to do with existing participants: ignore them, update them
	or cancel the import.
	
	@par Updating participants
	
	When importing participants the admin can choose to update existing participants, which will update only
	the fields of the participant that exist in the import.
	
	In other words: to update only specific columns for the participants, make sure that only those columns are
	in the pasted spreadsheet.
	
	@par Editing participants
	
	Basic participant editing is available if the participant's ID number is clicked.
	
	The participant editor will allow dumb text editing of the participant's fields, except for the ID number.
	No checking is done if the data is valid: the admin can set the participants "date of birth"-field to "a long time ago"
	instead of a real date without any problems.
	
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
*/

/**
	Participant plugin for SD Meeting Tool.
	
	@brief		Plugin providing participant storage and manipulation filters for SD Meeting Tool.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se

	@par		Changelog
	
	@par		1.1
	
	- New: Clear participants function.
	
**/
class SD_Meeting_Tool_Participants
	extends SD_Meeting_Tool_0
	implements interface_SD_Meeting_Tool_Participants
{
	public $local_options = array(
		'fields' => array(),
	);
	
	public function __construct()
	{
		parent::__construct( __FILE__ );

		// Internal filters
		add_filter( 'sd_mt_clear_participants',					array( &$this, 'sd_mt_clear_participants' ) );
		add_filter( 'sd_mt_delete_participant',					array( &$this, 'sd_mt_delete_participant' ) );
		add_filter( 'sd_mt_get_participant',					array( &$this, 'sd_mt_get_participant' ) );
		add_filter( 'sd_mt_get_all_participants',				array( &$this, 'sd_mt_get_all_participants' ) );
		add_filter( 'sd_mt_get_participant_field',				array( &$this, 'sd_mt_get_participant_field' ) );
		add_filter( 'sd_mt_get_participant_fields',				array( &$this, 'sd_mt_get_participant_fields' ) );
		add_filter( 'sd_mt_update_participant',					array( &$this, 'sd_mt_update_participant' ) );
		
		// External actions
		add_filter( 'sd_mt_admin_menu',							array( &$this, 'sd_mt_admin_menu' ) );
	}

	public function activate()
	{
		parent::activate();

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_participants` (
		  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID of participant',
		  `blog_id` int(11) NOT NULL COMMENT 'Blog ID this participant belongs to',
		  `data` longtext NOT NULL COMMENT 'Serialized data stdclass',
		  KEY (`id`),
		  KEY `blog_id` (`blog_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
		");
	}
	
	public function uninstall()
	{
		parent::uninstall();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_participants`");
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_admin_menu( $menus )
	{
		$this->load_language();

		$menus[ $this->_('Participants') ] = array(
			'sd_mt',
			$this->_('Participants'),
			$this->_('Participants'),
			'read',
			'sd_mt_participants',
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
			if ( $_GET['tab'] == 'edit' )
			{
				$tab_data['tabs']['edit'] = $this->_( 'Edit' );
				$tab_data['functions']['edit'] = 'admin_edit';

				$participant = $this->filters( 'sd_mt_get_participant', $_GET['id'] );
				if ( $participant === false )
					wp_die( $this->_( 'Specified participant does not exist!' ) );

				$tab_data['page_titles']['edit'] = $this->_( 'Editing participant: %s', $participant->id );
			}	// edit
		}

		$tab_data['tabs']['clear'] = $this->_( 'Clear' );
		$tab_data['functions']['clear'] = 'admin_clear';

		$tab_data['tabs']['import'] = $this->_( 'Import' );
		$tab_data['functions']['import'] = 'admin_import';

		$this->tabs($tab_data);
	}
	
	public function admin_overview()
	{
		if ( isset( $_POST['action_submit'] ) && isset( $_POST['participants'] ) )
		{
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['participants'] as $participant_id => $ignore )
				{
					$participant = $this->filters( 'sd_mt_get_participant', $participant_id );
					if ( $participant !== false )
					{
						$this->filters( 'sd_mt_delete_participant', $participant );
						$this->message( $this->_( 'Participant <em>%s</em> deleted.', $participant_id ) );
					}
				}
			}	// delete
		}
		
		$form = $this->form();
		$rv = $form->start();
		
		$participants = $this->filters( 'sd_mt_get_all_participants', null );
		
		if ( count( $participants ) < 1 )
		{
			$this->message( $this->_( 'No participants found! Perhaps you want to import new participants from a spreadsheet?' ) );
		}
		else
		{
			$fields = $this->filters( 'sd_mt_get_participant_fields', array() );
			$t_head = '';
			foreach( $fields as $field )
			{
				// We only want fields that are native to ... us.
				if ( ! $field->is_native )
					continue;
					
				$t_head .= '
					<th>' . $field->description. '</th>
				';
			}
			$t_body = '';
			foreach( $participants as $participant )
			{
				$input_participant_select = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $participant->id,
					'name' => $participant->id,
					'nameprefix' => '[participants]',
				);
				
				$t_body .= '
					<tr>
						<th scope="row" class="check-column">' . $form->make_input($input_participant_select) . ' <span class="screen-reader-text">' . $form->make_label($input_participant_select) . '</span></th>
				';
				
				foreach( $fields as $field )
				{
					if ( ! $field->is_native )
						continue;
					
					if ( $field->name == 'id' )
					{
						// Make the ID linkable and editable
						$url = add_query_arg( array(
							'tab' => 'edit',
							'id' => $participant->id,
						) );
						$participant->id = sprintf(
							'<a href="%s">%s</a>',
							$url,
							$participant->id
						);
					}
						
					$field_name = $field->name;
					$t_body .= '
						<td>' . ($participant->$field_name). '</td>
					';
				}

				$t_body .= '
					</tr>
				';
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
				<table class="widefat sd_mt_participants">
					<thead>
						<tr>
							<th class="check-column">' . $form->make_input( $selected ) . '<span class="screen-reader-text">' . $this->_('Selected') . '</span></th>
							' . $t_head .'
						</tr>
					</thead>
					<tbody>
						'.$t_body.'
					</tbody>
				</table>
				<p>
					' . $this->_('%s participants.', count($participants) ) . '
				</p>
			';
		}

		$rv .= $form->stop();

		wp_enqueue_script( 'jquery-ui' );
		$rv .= '
			<script type="text/javascript" src="'. $this->paths["url"] . "/js/jquery.tablesorter.min.js" .'"></script>
			<script type="text/javascript">
				jQuery(document).ready(function($)
				{
					$("table.sd_mt_participants").tablesorter();
				}); 
			</script>
		';
		echo $rv;
	}
	
	public function admin_clear()
	{
		$rv = '';
		$form = $this->form();
		
		if ( isset( $_POST[ 'clear' ] ) )
		{
			$this->filters( 'sd_mt_clear_participants' );
			$this->message_( 'All participants have been cleared from the database.' );
		}
		
		$inputs = array(
			'clear' => array(
				'css_class' => 'button-primary',
				'name' => 'clear',
				'type' => 'submit',
				'value' => $this->_( 'Clear all participants' ),
			),
		);
		
		$rv .= $this->p_( 'Clearing the participants will completely remove them and clear the field data, making the Meeting Tool ready to import new participants from scratch.' );
		
		$rv .= $form->start();
		$rv .= $this->display_form_table( $inputs );
		$rv .= $form->stop();
		
		echo $rv;
	}
	
	public function admin_edit()
	{
		$form = $this->form();
		$rv = $form->start();
		$id = $_GET['id'];
		$participant = $this->filters( 'sd_mt_get_participant', $_GET['id'] );
		$fields = $this->filters( 'sd_mt_get_participant_fields', array() );
		
		if ( isset( $_POST['update'] ) )
		{
			foreach( $fields as $field )
			{
				$field_name = $field->name;

				if ( ! $field->is_native )
					$continue;
				
				if ( $field_name == 'id' )
					continue;
				
				$participant->$field_name = trim( stripslashes( $_POST[ $field_name ] ) );
			}
			
			$this->filters( 'sd_mt_update_participant', $participant );
			$this->message( $this->_('The participant has been updated!') );
		}
		
		$inputs = array();
		
		foreach( $fields as $field )
		{
			$field_name = $field->name;

			if ( ! $field->is_native )
				$continue;
			
			if ( $field_name == 'id' )
				continue;
			
			
			$inputs[ $field->name ] = array(
				'name' => $field->name,
				'type' => 'text',
				'size' => 50,
				'label' => $field->description,
				'validation' => array( 'empty' => true ),
				'value' => $participant->$field_name,
			);
		}
		
		// And finally the update input!
		$inputs[] = array(
			'type' => 'submit',
			'name' => 'update',
			'value' => $this->_( 'Update participant' ),
			'css_class' => 'button-primary',
		);
		
		$rv .= $this->display_form_table( $inputs )
		. $form->stop();
		
		echo $rv;
	}
	
	public function admin_import()
	{
		$form = $this->form();
		$rv = $form->start();
		
		if ( isset( $_POST['submit'] ) )
			$this->import_users( $_POST );
		
		$inputs = array(
			'calc' => array(
				'type' => 'textarea',
				'name' => 'calc',
				'label' => $this->_( 'Pasted text from a spreadsheet.' ),
				'rows' => 20,
				'cols' => 80,
			),
			'existing' => array(
				'type' => 'radio',
				'name' => 'existing',
				'label' => $this->_( 'What to do with existing users?' ),
				'options' => array(
					'cancel' => $this->_( 'Cancel import' ),
					'ignore' => $this->_( 'Ignore' ),
					'update' => $this->_( 'Update' ),
				),
				'value' => 'cancel',
			),
			'submit' => array(
				'name' => 'submit',
				'type' => 'submit',
				'value' => $this->_( 'Import' ),
				'css_class' => 'button-primary',
			),
		);
		
		$rv .= '
			<p>
				' . $form->make_label( $inputs['calc'] ) . '<br />
				' . $form->make_input( $inputs['calc'] ) . '
			</p>

			<p>
				' . $form->make_label( $inputs['existing'] ) . '<br />
				' . $form->make_input( $inputs['existing'] ) . '
			</p>

			<p>
			' . $form->make_input( $inputs['submit'] ) . '
			</p>
		';
		
		echo $rv;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------

	public function sd_mt_clear_participants()
	{
		global $blog_id;

		// Delete the participants
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_participants` WHERE `blog_id` = '".$blog_id."'";
		$this->query( $query );
		
		// And reset the fields.
		$this->update_local_option( 'fields', array() );
	}
	
 	public function sd_mt_delete_participant( $SD_Meeting_Tool_Participant )
	{
		global $blog_id;
		$id = $SD_Meeting_Tool_Participant->id;
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_participants` WHERE `id` = '$id' AND `blog_id` = '".$blog_id."'";
		$this->query( $query );
	}
	
	public function sd_mt_get_all_participants()
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_participants` WHERE `blog_id` = '".$blog_id."'";
		$results = $this->query( $query );
		
		$rv = array();
		foreach( $results as $result )
			$rv[ $result['id'] ] = $this->participant_sql_to_object( $result );
		
		ksort( $rv );
		return $rv;
	}
	
	public function sd_mt_get_participant( $participant_id )
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_participants` WHERE `id` = '".$participant_id."' AND `blog_id` = '$blog_id'";
		
		$result = $this->query_single( $query );
		if ( $result === false )
			return false;
		
		return $this->participant_sql_to_object( $result );
	}

	public function sd_mt_get_participant_field( $field_name )
	{
		$fields = $this->get_local_option( 'fields' );
		foreach( $fields as $field )
			if ( $field->name == $field_name )
				return $field;
		return false;
	}
	
	public function sd_mt_get_participant_fields()
	{
		return $this->get_local_option( 'fields' );
	}
	
	public function sd_mt_update_participant( $SD_Meeting_Tool_Participant )
	{
		$participant = $this->filters( 'sd_mt_get_participant', $SD_Meeting_Tool_Participant->id );
		$insert = false;
		
		if ( $participant === false )
			$insert = true;
		
		if ( !isset( $SD_Meeting_Tool_Participant->id ) )
			$insert = true;
		
		// Prepare the fields for saving into the data column.
		$data = array();
		foreach( (array)$SD_Meeting_Tool_Participant as $key => $value )
		{
			if ( $key == 'id' )
				continue;
			$data[ $key ] = $value;
		}
		$data = $this->sql_encode( $data );
		
		if ( $insert )
		{
			global $blog_id;
			
			if ( $SD_Meeting_Tool_Participant->id < 0 )
			{
				$query = "INSERT INTO `".$this->wpdb->base_prefix."sd_mt_participants`
					(`blog_id`, `data`) VALUES ('$blog_id', '$data')  
				";
				$SD_Meeting_Tool_Participant->id = $this->query_insert_id( $query );
			}
			else
			{
				$id = $SD_Meeting_Tool_Participant->id;
				$query = "INSERT INTO `".$this->wpdb->base_prefix."sd_mt_participants`
					(`id`, `blog_id`, `data`) VALUES ('$id', '$blog_id', '$data')  
				";
				$this->query_insert_id( $query );
			}
		}
		else
		{
			global $blog_id;
			$id = $SD_Meeting_Tool_Participant->id;
			$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_participants`
				SET `data` = '$data'
				WHERE `id` = '$id'
				AND `blog_id` = '$blog_id'
			";
			$this->query( $query );
		}
		return $SD_Meeting_Tool_Participant;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------
	
	/**
		Convert a list row from SQL to an SD_Meeting_Tool_Participant.
		
		@param		$sql		Row from the database as an array.
		@return					A complete SD_Meeting_Tool_Participant object.
	**/ 
	private function participant_sql_to_object( $sql )
	{
		$participant = new SD_Meeting_Tool_Participant();
		$participant->id = $sql['id'];
		$data = (object) array_merge( (array)$participant->data, (array)$this->sql_decode( $sql['data'] ) );
		foreach( $data as $key => $value )
			$participant->$key = $value;
			
		return $participant;
	}
	
	/**
		Imports users.
		
		@param		$POST		The $_POST data.
	**/
	private function import_users( $POST )
	{
		$participants = array();		// Participant data;
		$count = array(					// We keep track of how many users we modify.
			'created' => 0,
			'updated' => 0,
			'ignored' => 0,
		);
		$calc = $_POST['calc'];
		$calc = trim( $calc );
		
		$rows = explode( "\n", $calc );
		
		if ( $rows < 2 )
		{
			$this->error( $this->_( 'You need to paste at least two lines for the import function to work!' ) );
			return;
		}
		
		// Extract the headers
		$headers = explode( "\t", $rows[0] );
		if ( count( $headers ) < 2 )
		{
			$this->error( $this->_( 'You need to paste at least two columns for the import function to work!' ) );
			return;
		}
		
		// Find which column is the ID column
		$id_column = -1;
		foreach( $headers as $column_index => $column_name )
			if ( $this->strtolower($column_name) == 'id' )
			{
				$id_column = $column_index;
				break;
			}
		if ( $id_column == -1 )
		{
			$this->error( $this->_( 'One of the columns needs to have the text: id' ) );
			return;
		}
		
		$fields = array();
		
		// Convert the headings to fields.
		foreach( $headers as $index => $header )
		{
			$header = trim( $header );
			$field = new SD_Meeting_Tool_Participant_Field();
			$field->name = SD_Meeting_Tool_Participant_Field::slug( $header );
			$field->description = $header;
			$field->type = 'text';
			$fields[] = $field;
		}
		
		array_shift( $rows );		// The first line was the headers. Now only the data is left.
		
		if ( $POST['existing'] == 'cancel' )
			$existing_users = $this->filters( 'sd_mt_get_all_participants', null );
		else
			$existing_users = array();
		
		$update = ($POST['existing'] == 'update');
		
		// Begin parsing!
		foreach( $rows as $index => $row )
		{
			$columns = explode( "\t", $row );
			if ( count($columns) != count($headers ) )
			{
				$this->error( $this->_( 'Row %s does not have the same amount of columns as the first row!', $index ) );
				return;
			}
			
			$participant = new SD_Meeting_Tool_Participant();
			foreach( $fields as $field_index => $field )
			{
				$field_slug = $field->name;
				$participant->$field_slug = trim( $columns[ $field_index ] );
			}
			
			// Now decide what to do with this imported user.
			$save = true;
			
			// Does this user exist?
			$old_participant = $this->filters( 'sd_mt_get_participant', $participant->id );
			if ( $old_participant !== false )
			{
				// User already exists. What now?
				switch ( $POST['existing'] )
				{
					case 'ignore':
						$count['ignored']++;
						$save = false;
						break;
					case 'cancel':
						$this->error( $this->_( 'User %s already exists! Aborting import.', $participant->id ) );
						return;
						break;
					case 'update':
						$count['updated']++;
						break;
				}
			}
			else
			{
				$count['created']++;
				$save = true;
			}
			
			if ( $save )
			{
				// Update only the fields of the participant that are in the import.
				foreach( $participant as $field => $value )
					$old_participant->$field = $value;
				$this->filters( 'sd_mt_update_participant', $old_participant );
			}
		}
		
		if ( ! $update )
			$this->update_local_option( 'fields', $fields );
		
		$this->message( $this->_('Import comlete. %s users created. %s users updated. %s users ignored.',
			$count['created'],
			$count['updated'],
			$count['ignored']
		) );
	}
}
$SD_Meeting_Tool_Participants = new SD_Meeting_Tool_Participants();
