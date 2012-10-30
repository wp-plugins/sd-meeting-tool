<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool Displays
Plugin URI: https://it.sverigedemokraterna.se
Description: Displays lists to visitors.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	@page		sd_meeting_tool_displays					Plugin: Displays
	
	@section	sd_meeting_tool_displays_description		Description
	
	The plugin's complete name should be "SD Meeting Tool Display Templates", which better explains what the plugin does:
	displays lists via templates to visitors using shortcodes embedded in posts / pages.
	
	Using a combination of a template header, display format and template footer the plugin will display all the participants
	in a list to the blog visitor, as defined by the shortcode[s].
	
	@section	sd_meeting_tool_displays_requirements		Requirements
	
	- @ref		index
	- @ref		sd_meeting_tool_display_formats
	- @ref		sd_meeting_tool_lists
	- @ref		sd_meeting_tool_participants
	
	@section	sd_meeting_tool_displays_installation		Installation
	
	Enable the plugin in Wordpress.
	
	The plugin will then create the following database tables:
	
	- @b prefix_sd_mt_displays
		Stores display templates.
	
	@section	sd_meeting_tool_displays_usage				Usage
	
	Create a new display [template] using the button.
	
	The name of the display is used for identification. The display format and list sort is normally taken from the list
	the shortcode is made from, but can be overridden if need be.
	
	The header is displayed before the list and the footer after the list. Between that the display format decides how to
	display each individual participant.
	
	@par Example for display a list as an ordered HTML list.
	
	First create a display format that displays the participant something like the following: <pre><li>#first_name#</li></pre>
	
	Now create a display template. Set it to override the list's display format with the just created. In the header box type
	@code
	<ol>
	@endcode

	and in the footer box type

	@code
	</ol>
	@endcode

	Update the display and finally create a shortcode for the list. The list participants should now be displayed in an ordered list.
	
	@par Table example
	
	Displaying users as tables is also possible. Just as the OL example above a display format must be created first.
	
	@code
	<tr>
	  <td>#first_name#</td>
	  <td>#last_name#</td>
	</tr>
	@endcode

	Create a new display, use the newly created display format and the following texts in the header and footer boxes:
	 
	@code
	<table class="sd_mt_display">
	  <thead>
	  <tr>
		<th>First name</th>
		<th>Last name</th>
		</tr>
	  </thead>
	  <tbody>
	@endcode

	and
	
	@code
	  </tbody>
	</table>
	@endcode

	In between those two boxes the participant should be displayed in two table columns.
	
	For an extra bonus, use the included table sorting javascript to allow users to sort their tables.
	Add the following to the footer box (you'll have to fix the path yourself, though):
	
	@code
	<script type="text/javascript" src="wp-content/plugins/sd-meeting-tool-base/js/jquery.tablesorter.min.js">\/script>
	<script type="text/javascript">
	jQuery(document).ready(function($)
	{
		$("table.sd_mt_display").tablesorter();
	});
	</script>
	@endcode

	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
*/


/**
	@brief		Displays lists to visitors using templates.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se

	@par		Changelog

	@par		1.1
	- Version bump.
**/
class SD_Meeting_Tool_Displays
	extends SD_Meeting_Tool_0
{
	public function __construct()
	{
		parent::__construct( __FILE__ );
		
		// Internal filters
		add_filter( 'sd_mt_delete_display',					array( &$this, 'sd_mt_delete_display' ) );
		add_filter( 'sd_mt_get_all_displays',				array( &$this, 'sd_mt_get_all_displays') );
		add_filter( 'sd_mt_get_display',					array( &$this, 'sd_mt_get_display' ) );
		add_filter( 'sd_mt_update_display',					array( &$this, 'sd_mt_update_display' ) );
		
		// Shortcodes
		add_shortcode('display_list',						array( &$this, 'shortcode_display_list') );
		
		// External actions we listen to
		add_filter( 'sd_mt_admin_menu',						array( &$this, 'sd_mt_admin_menu' ) );
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_admin_menu( $menus )
	{
		$this->load_language();

		$menus[ $this->_('Displays') ] = array(
			'sd_mt',
			$this->_('Displays'),
			$this->_('Displays'),
			'read',
			'sd_mt_displays',
			array( &$this, 'admin' )
		);			
		return $menus;
	}
	
	public function activate()
	{
		parent::activate();

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_displays` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `blog_id` int(11) NOT NULL,
		  `data` longtext NOT NULL COMMENT 'Serialized display data',
		  PRIMARY KEY (`id`),
		  KEY `blog_id` (`blog_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
		");
	}
	
	public function uninstall()
	{
		parent::uninstall();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_displays`");
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------

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

				$display = $this->filters( 'sd_mt_get_display', $_GET['id'] );
				if ( $display === false )
					wp_die( $this->_( 'Specified display does not exist!' ) );

				$tab_data['page_titles']['edit'] = $this->_( 'Editing display: %s', $display->data->name );
			}
		}

		$tab_data['tabs']['uninstall'] = $this->_( 'Uninstall' );
		$tab_data['functions']['uninstall'] = 'admin_uninstall';
		
		$this->tabs($tab_data);
	}

	public function admin_overview()
	{
		if ( isset( $_POST['action_submit'] ) && isset( $_POST['displays'] ) )
		{
			if ( $_POST['action'] == 'clone' )
			{
				foreach( $_POST['displays'] as $display_id => $ignore )
				{
					$display = $this->filters( 'sd_mt_get_display', $display_id );
					if ( $display !== false )
					{
						$display->data->name = $this->_( 'Copy of %s', $display->data->name );
						$display->id = null;
						$display = $this->filters( 'sd_mt_update_display', $display );

						$edit_link = add_query_arg( array(
							'tab' => 'edit',
							'id' => $display->id,
						) );
						
						$this->message( $this->_( 'Display cloned! <a href="%s">Edit the newly-cloned display</a>.', $edit_link ) );
					}
				}
			}	// clone
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['displays'] as $display_id => $ignore )
				{
					$display = $this->filters( 'sd_mt_get_display', $display_id );
					if ( $display !== false )
					{
						$this->filters( 'sd_mt_delete_display', $display );
						$this->message( $this->_( 'Display <em>%s</em> deleted.', $display->data->name ) );
					}
				}
			}	// delete
		}
		
		if ( isset( $_POST['create_display'] ) )
		{
			$display = new SD_Meeting_Tool_Display_Template();
			$display->data->name = $this->_( 'Display created %s', $this->now() );
			$display = $this->filters( 'sd_mt_update_display', $display );
			
			$edit_link = add_query_arg( array(
				'tab' => 'edit',
				'id' => $display->id,
			) );
			
			$this->message( $this->_( 'Display created! <a href="%s">Edit the newly-created display</a>.', $edit_link ) );
		}

		$form = $this->form();
		$returnValue = $form->start();
		
		$displays = $this->filters( 'sd_mt_get_all_displays', array() );
		
		if ( count( $displays ) < 1 )
			$this->message( $this->_( 'There are no displays available.' ) );
		else
		{
			$t_body = '';
			foreach( $displays as $display )
			{
				$input_display_select = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $display->data->name,
					'name' => $display->id,
					'nameprefix' => '[displays]',
				);
				
				$edit_link = add_query_arg( array(
					'tab' => 'edit',
					'id' => $display->id,
				) );
				
				// INFO time.
				$info = array();
				
				$info = implode( '</div><div>', $info );
				
				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input($input_display_select) . ' <span class="screen-reader-text">' . $form->make_label($input_display_select) . '</span></th>
					<td>
						<div>
							<a
							title="' . $this->_('Edit this list') . '"
							href="'. $edit_link .'">' . $display->data->name . '</a>
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
				<p>
					' . $form->make_label( $input_actions ) . '
					' . $form->make_input( $input_actions ) . '
					' . $form->make_input( $input_action_submit ) . '
				</p>
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
		
		// Allow the user to create a new list
		$input_display_create = array(
			'type' => 'submit',
			'name' => 'create_display',
			'value' => $this->_( 'Create a new display' ),
			'css_class' => 'button-primary',
		);
		
		$returnValue .= '
			<p>
				' . $form->make_input( $input_display_create ) . '
			</p>
		';

		$returnValue .= $form->stop();
		
		echo $returnValue;
	}
	
	/**
		Edit a display template.
	**/
	public function admin_edit()
	{
		$form = $this->form();
		$id = $_GET['id'];
		$returnValue = '';
		
		$inputs = array(
			'name' => array(
				'type' => 'text',
				'name' => 'name',
				'label' => $this->_( 'Name' ),
				'size' => 50,
				'maxlength' => 200,
			),
			'display_format_id' => array(
				'name' => 'display_format_id',
				'type' => 'select',
				'label' => $this->_( 'Display format override' ),
				'description' => $this->_( "Use the selected display format instead of the list's default." ),
				'options' => array( '' => $this->_('Use display format from the list.' ) ),
			),
			'list_sort_id' => array(
				'name' => 'list_sort_id',
				'type' => 'select',
				'label' => $this->_( 'Sort override' ),
				'description' => $this->_( "Use the selected sort instead of the list's default." ),
				'options' => array( '' => $this->_('Use sort from the list.' ) ),
			),
			'header' => array(
				'type' => 'textarea',
				'name' => 'header',
				'label' => $this->_( 'Header' ),
				'cols' => 50,
				'rows' => 20,
				'validation' => array( 'empty' => true ),
			),
			'footer' => array(
				'type' => 'textarea',
				'name' => 'footer',
				'label' => $this->_( 'Footer' ),
				'cols' => 50,
				'rows' => 20,
				'validation' => array( 'empty' => true ),
			),
			'update' => array(
				'type' => 'submit',
				'name' => 'update',
				'value' => $this->_( 'Update display' ),
				'css_class' => 'button-primary',
			),
		);
		
		if ( isset( $_POST['update'] ) )
		{
			$result = $form->validate_post( $inputs, array_keys( $inputs ), $_POST );

			if ($result === true)
			{
				$display = $this->filters( 'sd_mt_get_display', $id );
				$display->data->name = $_POST['name'];
				$display->data->display_format_id = $_POST['display_format_id'];
				$display->data->list_sort_id = $_POST['list_sort_id'];
				$display->data->header = $_POST['header'];
				$display->data->footer = $_POST['footer'];
				
				$this->filters( 'sd_mt_update_display', $display );
				
				$this->message( $this->_('The display has been updated!') );
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}

		$display = $this->filters( 'sd_mt_get_display', $id );
		
		if ( isset( $_POST['create_shortcode_page'] ) )
		{
			$post = $_POST['create_shortcode_page'];
			$post_content = '';
			if ( isset( $post['display_list'] ) )
			{
				$list = $_POST['create_shortcode_page']['list_id'];
				$list = $this->filters( 'sd_mt_get_list', $list );
				if ( $list === false )
					die('List does not exist!');
				
				$post_title = $list->data->name;

				$shortcode = '[display_list list="' . $list->id . '" ';
				if ( $display->data->display_format_id > 0 )
					$shortcode .= 'display_format_id="'. $display->data->display_format_id.'" ';
				if ( $display->data->list_sort_id > 0 )
					$shortcode .= 'list_sort_id="'. $display->data->list_sort_id.'" ';
				$shortcode .= 'display_id="'. $display->id.'"]';
				$post_content = $shortcode;
			}
			
			if ( $post_content != '' )
			{
				$post = new stdClass();
				$post->post_type = 'page';
				$user = wp_get_current_user();
				$page_id = wp_insert_post(array(
					'post_title' => $post_title,
					'post_type' => 'page',
					'post_content' => $post_content,
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
		}
		
		$display_formats = $this->filters( 'sd_mt_get_all_display_formats', array() );
		foreach( $display_formats as $display_format )
			$inputs['display_format_id']['options'][ $display_format->id ] = $display_format->data->name;
		
		$list_sorts = $this->filters( 'sd_mt_get_all_list_sorts', array() );
		foreach( $list_sorts as $list_sort )
			$inputs['list_sort_id']['options'][ $list_sort->id ] = $list_sort->data->name;
		
		$inputs['display_format_id']['value'] = intval( $display->data->display_format_id );
		$inputs['footer']['value'] = $display->data->footer;
		$inputs['header']['value'] = $display->data->header;
		$inputs['list_sort_id']['value'] = intval( $display->data->list_sort_id );
		$inputs['name']['value'] = $display->data->name;
		
		$returnValue .= '
			' . $form->start() . '
			
			' . $this->display_form_table( $inputs ). '

			' . $form->stop() . '
		';
		
		$inputs = array(
			'display_list' => array(
				'name' => 'display_list',
				'type' => 'submit',
				'value' => $this->_( 'Create a page with this shortcode' ),
				'title' => $this->_( 'Creates a new page with this shortcode as the only content.' ),
				'nameprefix' => '[create_shortcode_page]',
				'css_class' => 'button-secondary',
			),
			'list_id' => array(
				'name' => 'list_id',
				'type' => 'select',
				'label' => $this->_('List'),
				'nameprefix' => '[create_shortcode_page]',
			),
		);
		
		// Put all the lists in the select
		$lists = $this->filters( 'sd_mt_get_all_lists', array() );
		foreach( $lists as $list )
			$inputs['list_id']['options'][ $list->id ] = $list->data->name;
		
		$shortcode = '[display_list list="???" ';
		if ( $display->data->display_format_id > 0 )
			$shortcode .= 'display_format_id="'. $display->data->display_format_id.'" ';
		if ( $display->data->list_sort_id > 0 )
			$shortcode .= 'list_sort_id="'. $display->data->list_sort_id.'" ';
		$shortcode .= 'display_id="'. $display->id.'"]';
		
		$returnValue .= '
			<h3>' . $this->_('Shortcodes') . '</h3>

			' . $form->start() . '
			
			<table class="widefat">
				<thead>
					<tr>
						<th>' . $this->_( 'Shortcode' ). '</th>
						<th>' . $this->_( 'Description' ). '</th>
						<th>' . $this->_( 'Page creation' ). '</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>'. $shortcode . '</td>
						<td>
							<p>' . $this->_( 'Shows the selected list using this display template.' ). '</p>
							<p>
								' . $this->_( '<em>display_format_id</em>: Optional ID of display format to use.' ). '<br />
								' . $this->_( '<em>display_id</em>: ID of template to use as display.' ). '<br />
								' . $this->_( '<em>list_id</em>: ID of list to display.' ). '<br />
								' . $this->_( '<em>list_sort_id</em>: Optional ID of list sort to use.' ). '<br />
							</p>
						</td>
						<td>
							<div>
								' . $form->make_label( $inputs['list_id'] ) . '
								' . $form->make_input( $inputs['list_id'] ) . '
							</div>
							<div>
								' . $form->make_input( $inputs['display_list'] ) . '
							</div>
						</td>
					</tr>
				<tbody>
			</table>

			' . $form->stop() . '
		';
		
		echo $returnValue;
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_delete_display( $display )
	{
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_displays`
			WHERE `id` = '" . $display->id . "'";
		$this->query( $query );
	}
	
	public function sd_mt_get_all_displays()
	{
		global $blog_id;
		$query = "SELECT id FROM `".$this->wpdb->base_prefix."sd_mt_displays` WHERE `blog_id` = '$blog_id'";
		$results = $this->query( $query );
		$returnValue = array();

		foreach( $results as $result )
			$returnValue[ $result['id'] ] = $this->sd_mt_get_display( $result['id'] );

		return SD_Meeting_Tool::sort_data_array( $returnValue, 'name' );
	}
	
	public function sd_mt_get_display( $display_id )
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_displays` WHERE `id` = '$display_id' AND `blog_id` = '$blog_id'";
		$result = $this->query_single( $query );
		if ( $result === false )
			return false;

		return $this->sql_to_display( $result );
	}
	
	public function sd_mt_update_display( $display )
	{
		global $blog_id;
		$data = $this->sql_encode( $display->data );
		
		if ( $display->id === null )
		{
			$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_displays`
				(`blog_id`, `data`)
				VALUES
				('". $blog_id ."', '" . $data . "')
			";
			$display->id = $this->query_insert_id( $query );
		}
		else
		{
			$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_displays`
				SET
				`data` = '" . $data. "'
				WHERE `id` = '" . $display->id . "'";
			$this->query( $query );
		}
		
		return $display;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------
	
	/**
		Convert a display row from SQL to an SD_Meeting_Tool_Display_Template
		
		@param		$sql		Row from the database as an array.
		@return					A complete SD_Meeting_Tool_Display_Template object.
	**/ 
	private function sql_to_display( $sql )
	{
		$display = new SD_Meeting_Tool_Display_Template();
		$display->id = $sql['id'];
		$display->data = (object) array_merge( (array)$display->data, (array)$this->sql_decode( $sql['data'] ) );
		return $display;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Shortcodes
	// --------------------------------------------------------------------------------------------

	/**
		Shows a list.
		
		Displays a list using the specified template.
		
		@par		Attributes
		
		- list			List ID to display. Required.
		- template		Template ID to use. Required.
		
		@param		$attr		Attributes array.
		@return					HTML string to display.
	**/
	public function shortcode_display_list( $attr )
	{
		if ( !isset( $attr['list'] ) )
			return;
		if ( !isset( $attr['display_id'] ) )
			return;
		$list_id = $attr['list_id'];
		$display_id = $attr['display_id'];
		
		$list = $this->filters( 'sd_mt_get_list', $list_id );
		if ( $list === false )
			return;
		
		$display = $this->filters( 'sd_mt_get_display', $display_id );
		if ( $display === false )
			return;
		
		// 1. Shortcode display format
		// 2. Override display format
		// 3. List's display format
		$display_format_id = false;
		if ( isset( $attr['display_format_id'] ) )
			$display_format_id = $attr['display_format_id'];
		if ( !$display_format_id && $display->data->display_format_id != '' )
			$display_format_id = $display->data->display_format_id;
		$display_format = $this->filters( 'sd_mt_get_display_format', $display_format_id );
		if ( $display_format === false )
		{
			$display_format_id = $list->data->display_format_id;
			$display_format = $this->filters( 'sd_mt_get_display_format', $display_format_id );
			if ( $display_format === false )
				return;
		}
		
		// 1. Shortcode sort
		// 2. Override sort
		// 3. List's sort
		$list_sort_id = false;
		if ( isset( $attr['list_sort_id'] ) )
			$list_sort_id = $attr['list_sort_id'];
		if ( !$list_sort_id && $display->data->list_sort_id != '' )
			$list_sort_id = $display->data->list_sort_id;
		if ( $list_sort_id !== false )
		{
			$list_sort = $this->filters( 'sd_mt_get_list_sort', $list_sort_id );
			if ( $list_sort !== false )
				$list->data->list_sort_id = $list_sort_id;
		}
		
		$list = $this->filters( 'sd_mt_list_participants', $list );

		$returnValue = $display->data->header;
		
		foreach( $list->participants as $participant )
			$returnValue .= $this->filters( 'sd_mt_display_participant', $participant, $display_format );
		
		$returnValue .= $display->data->footer;

		return $returnValue;
	}	
}
$SD_Meeting_Tool_Displays = new SD_Meeting_Tool_Displays();

// --------------------------------------------------------------------------------------------
// ----------------------------------------- class SD_Meeting_Tool_Display_Template
// --------------------------------------------------------------------------------------------
/**
	@brief		List display template. 
	@see		SD_Meeting_Tool_Displays
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Display_Template
{
	/**
		Unique ID.
		@var	$id
	**/
	public $id;
	
	/**
		Various data as a stdClass.
		
		Stores:
		- @b display_format_id	ID of participant display format.
		- @b footer				Footer text.
		- @b header				Header text.
		- @b list_sort_id		Optional ID of list sort to use.
		- @b name				Name of display template.
		  
		$var	$data
	**/
	public $data;

	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->display_format_id = false;
		$this->data->footer = '';
		$this->data->header = '';
		$this->data->list_sort_id = false;
		$this->data->name = '';
	}
}
