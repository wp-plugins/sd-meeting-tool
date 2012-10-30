<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool Display Formats
Plugin URI: https://it.sverigedemokraterna.se
Description: Controls how plugins display participants.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	@page		sd_meeting_tool_display_formats					Plugin: Display Formats
	
	@section	sd_meeting_tool_display_formats_description		Description
	
	Displays participants in various ways. Displays formats are used internally and externally.
	
	The display format works with the participant's various fields, as specified in the editor between hashes.
	
	The display format is displayed directly and HTML can be used. Together with @ref SD_Meeting_Tool_Displays and HTML, participants can be displayed in any way you wish.
	
	@section	sd_meeting_tool_display_formats_requirements	Requirements
	
	@ref		sd_meeting_tool_participants
	
	@section	sd_meeting_tool_display_formats_installation	Installation
	
	Enable the plugin in Wordpress.
	
	The plugin will then create the following database tables:
	
	- @b prefix_sd_mt_display_formats
		Display format data.
	
	@section	sd_meeting_tool_display_formats_usage			Usage
	
	A display format is written as a normal text string. Special keywords in between hashes are automatically replaced at display time with the participant's field.
	
	The keywords can be seen in the table underneath the editor. They can be inserted by writing them manually or double-clicking them in the table.
	
	@par Simple example
	
	\#first_name\#, \#last_name\#, \#region\#
	
	Displays the participant's first name, last name and region columns.
	
	@par HTML example
	
	HTMl can be displayed internally (in the admin interface), but it is more common to be used externally.
	
	\<li\>My name is \#first_name\#\</li\>
	
	@par Another HTML example
	
	\<div class="full_name"\>\#full_name\#\<br /\>\</div\>
	
	@par Example for barcodes
	
	\#full_name\#, \#barcode_with_stars\#
	
	Here the participant's full name is displayed and then the column with the asterisked barcode is displayed.
	
	The asterisks are necessary for the barcode readers SD use to register the barcode.
	
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
*/

/**
	Display Format plugin for SD Meeting Tool.
	
	@brief		Plugin providing participant display formats for internal (admin) use.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se

	@par		Changelog

	@par		1.1
	- Version bump.
**/
class SD_Meeting_Tool_Display_Formats
	extends SD_Meeting_Tool_0
	implements interface_SD_Meeting_Tool_Display_Formats
{
	// To cache things during this cycle.
	private $cache = array();
	
	public function __construct()
	{
		parent::__construct( __FILE__ );

		// Internal filters
		add_filter( 'sd_mt_delete_display_format',		array( &$this, 'sd_mt_delete_display_format' ) );
		add_filter( 'sd_mt_display_participant',		array( &$this, 'sd_mt_display_participant' ), 10, 2 );
		add_filter( 'sd_mt_get_all_display_formats',	array( &$this, 'sd_mt_get_all_display_formats' ) );
		add_filter( 'sd_mt_get_display_format',			array( &$this, 'sd_mt_get_display_format' ) );
		add_filter( 'sd_mt_update_display_format',		array( &$this, 'sd_mt_update_display_format' ) );
		
		// External actions
		add_filter( 'sd_mt_admin_menu',					array( &$this, 'sd_mt_admin_menu' ) );
	}

	public function activate()
	{
		parent::activate();

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_display_formats` (
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
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_display_formats`");
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------

	public function sd_mt_admin_menu( $menus )
	{
		$this->load_language();

		$menus[ $this->_('Display formats') ] = array(
			'sd_mt',
			$this->_('Display formats'),
			$this->_('Display formats'),
			'read',
			'sd_mt_display_formats',
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
				$tab_data['tabs']['edit'] = $this->_( 'Edit');
				$tab_data['functions']['edit'] = 'admin_edit';
				
				$display_format = $this->filters( 'sd_mt_get_display_format', $_GET['id'] );
				if ( $display_format === false )
					wp_die( $this->_( 'Specified display format does not exist!' ) );
				
				$tab_data['page_titles']['edit'] = $this->_( 'Editing display format: %s', $display_format->data->name );
			}
		}

		$this->tabs($tab_data);
	}
	
	public function admin_overview()
	{
		if ( isset( $_POST['create_display_format'] ) )
		{
			$display_format = new SD_Meeting_Tool_Display_Format();
			$display_format->data->name = $this->_( 'Display format created %s', $this->now() );
			$display_format = $this->filters( 'sd_mt_update_display_format', $display_format );
			
			$edit_link = add_query_arg( array(
				'tab' => 'edit',
				'id' => $display_format->id,
			) );
			
			$this->message( $this->_( 'Display format created! <a href="%s">Edit the newly-created display format</a>.', $edit_link ) );
		}

		if ( isset( $_POST['action_submit'] ) && isset( $_POST['display_formats'] ) )
		{
			if ( $_POST['action'] == 'clone' )
			{
				foreach( $_POST['display_formats'] as $display_format_id => $ignore )
				{
					$display_format = $this->filters( 'sd_mt_get_display_format', $display_format_id );
					if ( $display_format !== false )
					{
						$display_format->id = null;
						$display_format->data->name = $this->_( 'Clone of %s', $display_format->data->name );
						$display_format = $this->filters( 'sd_mt_update_display_format', $display_format );
						
						$edit_link = add_query_arg( array(
							'tab' => 'edit',
							'id' => $display_format->id,
						) );
						
						$this->message( $this->_( 'Display format cloned! <a href="%s">Edit the newly-cloned display format</a>.', $edit_link ) );
					}
				}
			}	// clone
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['display_formats'] as $display_format_id => $ignore )
				{
					$display_format = $this->filters( 'sd_mt_get_display_format', $display_format_id );
					if ( $display_format !== false )
					{
						$this->filters( 'sd_mt_delete_display_format', $display_format );
						$this->message( $this->_( 'Display format <em>%s</em> deleted.', $display_format_id ) );
					}
				}
			}	// delete
		}
		
		$form = $this->form();
		$returnValue = $form->start();
		
		$display_formats = $this->filters( 'sd_mt_get_all_display_formats', array() );
		
		if ( count( $display_formats ) < 1 )
		{
			$this->message( $this->_( 'No display formats found!' ) );
		}
		else
		{
			$t_body = '';
			foreach( $display_formats as $display_format )
			{
				$input_select_display_format = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $display_format->data->name,
					'name' => $display_format->id,
					'nameprefix' => '[display_formats]',
				);
				
				$edit_link = add_query_arg( array(
					'tab' => 'edit',
					'id' => $display_format->id,
				) );
				
				$info = array();
				$info[] = htmlspecialchars( $this->parse_display_format_array( $display_format->data->display_format ) );
				$info = implode( '</div><div>', $info );
				
				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input( $input_select_display_format ) . ' <span class="screen-reader-text">' . $form->make_label( $input_select_display_format ) . '</span></th>
					<td>
						<div>
							<a
							title="' . $this->_( 'Edit this display format' ) . '"
							href="' . $edit_link . '">' . $display_format->data->name . '</a>
						</div>
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
					array( 'value' => 'clone', 'text' => $this->_('Clone') ),
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

		// Allow the user to create a new display format
		$input_display_format_create = array(
			'type' => 'submit',
			'name' => 'create_display_format',
			'value' => $this->_( 'Create a new display format' ),
			'css_class' => 'button-primary',
		);
		
		$returnValue .= '
			<p>
				' . $form->make_input( $input_display_format_create ) . '
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
			'display_format' => array(
				'type' => 'text',
				'name' => 'display_format',
				'label' => $this->_( 'Display format' ),
				'description' => $this->_( 'See table below for instructions.' ),
				'size' => 50,
			),
			'update' => array(
				'type' => 'submit',
				'name' => 'update',
				'value' => $this->_( 'Update display format' ),
				'css_class' => 'button-primary',
			),
		);
		
		if ( isset( $_POST['update'] ) )
		{
			$result = $form->validate_post( $inputs, array_keys( $inputs ), $_POST );

			if ($result === true)
			{
				$display_format = $this->filters( 'sd_mt_get_display_format', $id );
				$display_format->data->name = $_POST['name'];
				$display_format->data->display_format = $this->parse_display_format_string( stripslashes( $_POST['display_format'] ) );
								
				$this->filters( 'sd_mt_update_display_format', $display_format );
				
				$this->message( $this->_('The display_format has been updated!') );
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}

		$display_format = $this->filters( 'sd_mt_get_display_format', $id );
		
		$inputs['name']['value'] = $display_format->data->name;
		$inputs['display_format']['value'] = $this->parse_display_format_array( $display_format->data->display_format );
		
		$returnValue .= '<h3>' . $this->_('Display format settings') . '</h3>';

		$returnValue .= '
			' . $form->start() . '
			
			' . $this->display_form_table( $inputs ). '

			' . $form->stop() . '
		';
		
		$returnValue .= '<h3>' . $this->_('Display format settings') . '</h3>
			<p>
				' . $this->_( 'Below is a table with two columns. In the left column there is a keyword with a corresponding user field in the right column.' ) . '
			</p>
			<p>
				' . $this->_( 'Use the keywords exactly as printed in the format field above and the Meeting Tool will automatically replace the keyword with the corresponding participant field.' ) . '
			</p>
		';
		
		$t_body = '';
		$fields = $this->filters( 'sd_mt_get_participant_fields', array() );
		foreach( $fields as $field )
		{
			$t_body .= '
				<tr>
					<td class="keyword">#' . $field->get_slug() . '#</td>
					<td>' .$field->description . ' </td>
				</tr>
			';
		}
		$returnValue .= '
			<table class="widefat keywords">
				<thead>
					<tr>
						<th>' . $this->_('Keyword') . '</th>
						<th>' . $this->_('Participant field') . '</th>
					</tr>
				</thead>
				<tbody>
					'.$t_body.'
				</tbody>
			</table>
			<script type="text/javascript" src="'. $this->paths['url'] . "/js/sd_meeting_tool_display_formats.js" .'"></script>
		'; 

		echo $returnValue;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------

 	public function sd_mt_delete_display_format( $format )
	{
		global $blog_id;
		$id = $format->id;
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_display_formats` WHERE `id` = '$id' AND `blog_id` = '".$blog_id."'";
		$this->query( $query );
	}
	
 	public function sd_mt_display_participant( $SD_Meeting_Tool_Participant, $SD_Meeting_Tool_Display_Format )
 	{
 		// No display format? Then use the ID, since we *always* have the participant's ID.
 		if ( $SD_Meeting_Tool_Display_Format === false )
 			return $SD_Meeting_Tool_Participant->id;
 		if ( !isset( $this->cache['participant_fields'] ) )
 			$this->cache['participant_fields'] = $this->filters( 'sd_mt_get_participant_fields', array() );
 		$fields = $this->cache['participant_fields'];
 		
 		$returnValue = '';
 		
 		foreach( $SD_Meeting_Tool_Display_Format->data->display_format as $format )
 		{
 			$value = reset( $format );
 			switch( key( $format ) )
 			{
 				case 'keyword':
 					if ( isset( $SD_Meeting_Tool_Participant->$value ) )
 					{
 						// Find the participant field.
 						foreach( $fields as $field )
 							if ( $field->name == $format['keyword'] )
 							{
 								$field->value = $SD_Meeting_Tool_Participant->$value;
		 						$field = $this->filters( 'sd_mt_display_participant_field', $field );
		 						$returnValue .= $field->value;
 								break;
 							}
 					}
 					break;
 				case 'string':
 					$returnValue .= stripslashes( $value );
 					break;
 			}
 		}
 		return $returnValue;
 	}
 	
	public function sd_mt_get_display_format( $display_format_id )
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_display_formats` WHERE `blog_id` = '".$blog_id."' AND id = '$display_format_id'";
		$result = $this->query_single( $query );
		if ( $result === false )
			return false;
			
		return $this->display_format_sql_to_object( $result );
	}

	public function sd_mt_get_all_display_formats()
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_display_formats` WHERE `blog_id` = '".$blog_id."'";
		$results = $this->query( $query );
		
		$returnValue = array();
		foreach( $results as $result )
			$returnValue[ $result['id'] ] = $this->display_format_sql_to_object( $result );

		return SD_Meeting_Tool::sort_data_array( $returnValue, 'name' );
	}

	public function sd_mt_update_display_format( $SD_Meeting_Tool_Display_Format )
	{
		global $blog_id;
		$display_format = $SD_Meeting_Tool_Display_Format;		// Convenience.
		$data = $this->sql_encode( $display_format->data );
		
		if ( $display_format->id === null )
		{
			$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_display_formats`
				(`blog_id`, `data`)
				VALUES
				('". $blog_id ."', '" . $data . "')
			";
			$display_format->id = $this->query_insert_id( $query );
		}
		else
		{
			$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_display_formats`
				SET
				`data` = '" . $data. "'
				WHERE `id` = '" . $display_format->id . "'
				AND `blog_id` = '" . $blog_id . "'
			";
			$this->query( $query );
		}
		return $display_format;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------

	/**
		Convert a list row from SQL to an SD_Meeting_Tool_Display_Format.
		
		@param		$sql		Row from the database as an array.
		@return					A complete SD_Meeting_Tool_Display_Format object.
	**/ 
	private function display_format_sql_to_object( $sql )
	{
		$display_format = new SD_Meeting_Tool_Display_Format();
		$display_format->id = $sql['id'];
		$display_format->data = (object) array_merge( (array)$display_format->data, (array)$this->sql_decode( $sql['data'] ) );
		return $display_format;
	}
	
	/**
		Breaks up a string into an array of keywords / strings, dependant on the current participant fields.
		
		@param		$string		String to parse
		@return		An array of arrays.
	**/
	private function parse_display_format_string( $string )
	{
		$fields = $this->filters( 'sd_mt_get_participant_fields', null );
		$returnValue = array();
		
		$matches = preg_split( '/(\#[a-z_]*\#)/', $string, -1, PREG_SPLIT_DELIM_CAPTURE);
		foreach( $matches as $match )
		{
			if ( strlen( $match ) < 1 )
				continue;
			
			$handled = false;
			
			foreach( $fields as $field )
				if ( '#' . $field->get_slug() . '#' == $match )
				{
					$returnValue[] = array( 'keyword' => $field->get_slug() );
					$handled = true;
					break;
				}
			if ( ! $handled )
				// Nope. Wasn't a keyword. Therefore it must be a string.
				$returnValue[] = array( 'string' => $match );
		}
		return $returnValue;
	}
	
	/**
		Converts a display format array back into a human readable string.
		
		@param		$array		Array to convert to a string.
		@return					A human-readable string.
	**/
	private function parse_display_format_array( $array )
	{
		$returnValue = '';

		foreach( $array as $token )
		{
			$type = key( $token );
			$value = reset( $token );
			
			if ( $type == 'keyword' )
				$value = '#' . $value . '#';
			$returnValue .= $value;
		}
		
		return $returnValue;
	}
}
$SD_Meeting_Tool_Display_Formats = new SD_Meeting_Tool_Display_Formats();