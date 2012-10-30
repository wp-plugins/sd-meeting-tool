<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool Lists
Plugin URI: https://it.sverigedemokraterna.se
Description: Simple, basic Lists module for Sd Meeting Tool.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	@page		sd_meeting_tool_lists							Plugin: Lists
	
	@section	sd_meeting_tool_lists_description				Description
	
	A list is the mortar of the Meeting Tool (the bricks being participants), consisting of a group of participants and, optionally, other lists.
	
	The participants from other lists can be included and / or excluded from a list. It will first include all the participants in the specified included lists, and then subtract participants int he specified excluded lists.  
	
	@section	sd_meeting_tool_display_formats_requirements	Requirements
	
	@ref		sd_meeting_tool_participants
	
	@section	sd_meeting_tool_lists_installation				Installation
	
	Enable the plugin in Wordpress.
	
	The plugin will then create the following database tables:
	
	- @b prefix_sd_mt_lists
		Lists and their settings.
	- @b prefix_sd_mt_list_excludes
		Which lists are to be excluded from each respective list.
	- @b prefix_sd_mt_list_includes
		Which lists are to be included into each respective list.
	- @b prefix_sd_mt_list_participants
		The participants of each list.

	@section	sd_meeting_tool_lists_usage						Usage
	
	Each list includes a display format and list sort. The display format is the @b default display format used when displaying the list's participants. Other plugins can override the display format.
	
	A list sort specifies how to sort a list. Once again, other plugins might override the default list sort.
	
	If you want participants from other lists to be automatically included, select the included lists. The excluded lists do the opposite.
	
	Lists by themselves are stupid and can't do anything, it is up to the admin to create lists that make sense for other plugins to use.
	
	@par Example list suitable for checking in
	
	Create
	
	- a list for all participants that are allowed to check in. Leave it empty.
	- a list for all participants that have checked in. Leave it empty.
	- a list for all participants that @b may check in. Include the first list and exclude the second list.
	
	Set the registration plugin to use the third list as the participant source list. Each time someone is checked in the check-in
	action will add the participant to the second list. The third list will automatically remove that participant from the first list,
	resulting in a list of participants that have not checked in yet. 
	
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/

/**
	Provides a basic amount of list handling. Lists are the hub of participant exchange amongst @sdmt plugins.

	@brief		Standard, basic and complete list handling plugin.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
	
	@par		Changelog
	
	@par		1.1
	
	- New: sd_mt_add_list_participants function
	- New: Particpants are shown in the participant edit textarea
	- Fix: Lists are properly cloned
	
**/
class SD_Meeting_Tool_Lists
	extends SD_Meeting_Tool_0
	implements interface_SD_Meeting_Tool_Lists
{
	/**
		Provides human-readable strings to for {SD_Meeting_Tool_Action_Item}s.
		@var	$strings
	**/
	private $strings;

	public function __construct()
	{
		parent::__construct( __FILE__ );
		
		// Internal filters
		add_filter( 'sd_mt_add_list_participant',			array( &$this, 'sd_mt_add_list_participant' ), 10, 2 );
		add_filter( 'sd_mt_add_list_participants',			array( &$this, 'sd_mt_add_list_participants' ), 10, 2 );
		add_filter( 'sd_mt_clone_list',						array( &$this, 'sd_mt_clone_list') );
		add_filter( 'sd_mt_delete_list',					array( &$this, 'sd_mt_delete_list' ) );
		add_filter( 'sd_mt_get_all_lists',					array( &$this, 'sd_mt_get_all_lists') );
		add_filter( 'sd_mt_get_list',						array( &$this, 'sd_mt_get_list' ) );
		add_filter( 'sd_mt_list_participants',				array( &$this, 'sd_mt_list_participants' ) );
		add_filter( 'sd_mt_remove_list_participant',		array( &$this, 'sd_mt_remove_list_participant' ), 10, 2 );
		add_filter( 'sd_mt_remove_list_participants',		array( &$this, 'sd_mt_remove_list_participants' ) );
		add_filter( 'sd_mt_update_list',					array( &$this, 'sd_mt_update_list' ) );
		
		// External actions we listen to
		add_filter( 'sd_mt_admin_menu',						array( &$this, 'sd_mt_admin_menu' ) );
		add_action( 'sd_mt_configure_action_item',			array( &$this, 'sd_mt_configure_action_item' ) );
		add_action( 'sd_mt_trigger_action_item',			array( &$this, 'sd_mt_trigger_action_item' ) );
		
		// External filters we react to
		add_filter( 'sd_mt_delete_participant',				array( &$this, 'sd_mt_delete_participant' ) );
		add_filter( 'sd_mt_display_participant_field',		array( &$this, 'sd_mt_display_participant_field' ) );
		add_filter( 'sd_mt_get_participant_fields',			array( &$this, 'sd_mt_get_participant_fields' ), 20 );
		add_filter( 'sd_mt_get_action_item_description',	array( &$this, 'sd_mt_get_action_item_description' ) );
		add_filter( 'sd_mt_get_action_item_types',			array( &$this, 'sd_mt_get_action_item_types' ) );
		
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_admin_menu( $menus )
	{
		$this->load_language();

		$menus[ $this->_('Lists') ] = array(
			'sd_mt',
			$this->_('Lists'),
			$this->_('Lists'),
			'read',
			'sd_mt_list',
			array( &$this, 'admin' )
		);			

		wp_enqueue_style( 'sd_mt_lists', '/' . $this->paths['path_from_base_directory'] . '/css/SD_Meeting_Tool_Lists.css', false, '1.1', 'screen' );

		return $menus;
	}
	
	public function activate()
	{
		parent::activate();

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_lists` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `blog_id` int(11) NOT NULL,
		  `data` longtext NOT NULL COMMENT 'Serialized list data',
		  PRIMARY KEY (`id`),
		  KEY `blog_id` (`blog_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
		");

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_list_excludes` (
		  `id` int(11) NOT NULL COMMENT 'List ID',
		  `excluded_list_id` int(11) NOT NULL COMMENT 'Excluded list''s ID',
		  KEY `id` (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Which lists are excluded from this list';
		");

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_list_includes` (
		  `id` int(11) NOT NULL COMMENT 'List ID',
		  `included_list_id` int(11) NOT NULL COMMENT 'Included list''s ID',
		  KEY `id` (`id`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Which lists are included in other lists';
		");

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_list_participants` (
		  `id` int(11) NOT NULL COMMENT 'List ID',
		  `participant_id` int(11) NOT NULL COMMENT 'Participant ID',
		  `registered` datetime NOT NULL,
		  KEY `id` (`id`,`participant_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1 COMMENT='Which participants are in which lists';
		");
	}
	
	public function uninstall()
	{
		parent::uninstall();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_lists`");
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_list_excludes`");
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_list_includes`");
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_list_participants`");
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

				$list = $this->filters( 'sd_mt_get_list', $_GET['id'] );
				if ( $list === false )
					wp_die( $this->_( 'Specified list does not exist!' ) );

				$tab_data['page_titles']['edit'] = $this->_( 'Editing list: %s', $list->data->name );
			}

			if ( $_GET['tab'] == 'copy_participants' )
			{
				$tab_data['tabs']['copy_participants'] = $this->_( 'Copy participants');
				$tab_data['functions']['copy_participants'] = 'admin_copy_participants';

				$list = $this->filters( 'sd_mt_get_list', $_GET['id'] );
				if ( $list === false )
					wp_die( $this->_( 'Specified list does not exist!' ) );

				$tab_data['page_titles']['copy_participants'] = $this->_( 'Copying participants from: %s', $list->data->name );
			}
			
			if ( $_GET['tab'] == 'show_participants' )
			{
				$tab_data['tabs']['show_participants'] = $this->_( 'Show participants');
				$tab_data['functions']['show_participants'] = 'admin_show_participants';

				$list = $this->filters( 'sd_mt_get_list', $_GET['id'] );
				if ( $list === false )
					wp_die( $this->_( 'Specified list does not exist!' ) );

				$tab_data['page_titles']['show_participants'] = $this->_( 'Showing participants for: %s', $list->data->name );
			}
		}

		$tab_data['tabs']['uninstall'] = $this->_( 'Uninstall' );
		$tab_data['functions']['uninstall'] = 'admin_uninstall';
		
		$this->tabs($tab_data);
	}
	
	public function admin_overview()
	{
		$rv = '';

		if ( isset( $_POST['action_submit'] ) && isset( $_POST['lists'] ) )
		{
			if ( $_POST['action'] == 'clone' )
			{
				foreach( $_POST['lists'] as $list_id => $ignore )
				{
					$list = $this->filters( 'sd_mt_get_list', $list_id );
					$new_list = $this->filters( 'sd_mt_clone_list', $list );
					
					$edit_link = add_query_arg( array(
						'tab' => 'edit',
						'id' => $new_list->id,
					) );
					
					$this->message( $this->_( 'List cloned! <a href="%s">Edit the newly-cloned list</a>.', $edit_link ) );
				}
			}	// clone
			if ( $_POST['action'] == 'compare' )
			{
				$rv .= $this->compare_lists( array_keys( $_POST['lists'] ) );
			}	// compare
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['lists'] as $list_id => $ignore )
				{
					$list = $this->filters( 'sd_mt_get_list', $list_id );
					if ( $list !== false )
					{
						$this->filters( 'sd_mt_delete_list', $list );
						$this->message( $this->_( 'List <em>%s</em> deleted.', $list->data->name ) );
					}
				}
			}	// delete
			if ( $_POST['action'] == 'empty' )
			{
				foreach( $_POST['lists'] as $list_id => $ignore )
				{
					$list = $this->filters( 'sd_mt_get_list', $list_id );
					if ( $list !== false )
					{
						$this->filters( 'sd_mt_remove_list_participants', $list );
						$this->message( $this->_( 'List <em>%s</em> emptied.', $list->data->name ) );
					}
				}
			}	// empty
		}
		
		if ( isset( $_POST['create_list'] ) )
		{
			$list = new SD_Meeting_Tool_List();
			$list->data->name = $this->_( 'List created %s', $this->now() );
			$list = $this->filters( 'sd_mt_update_list', $list );
			
			$edit_link = add_query_arg( array(
				'tab' => 'edit',
				'id' => $list->id,
			) );
			
			$this->message( $this->_( 'List created! <a href="%s">Edit the newly-created list</a>.', $edit_link ) );
		}

		$form = $this->form();		
		$rv .= $form->start();
		$lists = $this->filters( 'sd_mt_get_all_lists', array() );
		
		if ( count( $lists ) < 1 )
			$this->message( $this->_( 'There are no lists available.' ) );
		else
		{
			$t_body = '';
			foreach( $lists as $list )
			{
				$input_list_select = array(
					'type' => 'checkbox',
					'checked' => isset( $_POST[ 'lists' ][ $list->id ] ),
					'label' => $list->data->name,
					'name' => $list->id,
					'nameprefix' => '[lists]',
				);
				
				// ACTION time.
				$actions = array();
				
				// Show participants action
				$show_action_url = add_query_arg( array(
					'tab' => 'show_participants',
					'id' => $list->id,
				) );
				$actions[] = '<a title="'. $this->_('Show all of the participants') .'" href="'.$show_action_url.'">'. $this->_('Show participants') . '</a>';
				
				// Copy participants action
				$copy_action_url = add_query_arg( array(
					'tab' => 'copy_participants',
					'id' => $list->id,
				) );
				$actions[] = '<a href="'.$copy_action_url.'">'. $this->_('Copy participants') . '</a>';
				
				// Edit list action
				$edit_action_url = add_query_arg( array(
					'tab' => 'edit',
					'id' => $list->id,
				) );
				$actions[] = '<a href="'.$edit_action_url.'">'. $this->_('Edit') . '</a>';
				
				$actions = implode( '&emsp;<span class="sep">|</span>&emsp;', $actions );
				
				// INFO time.
				$info = array();
				
				// Display format is good info
				$display_format = $list->data->display_format_id;
				if ( $display_format > 0 )
				{
					$display_format = $this->filters( 'sd_mt_get_display_format', $display_format );
					if ( $display_format !== false )
						$info[] = $this->_( 'Display format:' ) . ' ' . $display_format->data->name;
				}
				
				// And so is sort order
				$list_sort_id = $list->data->list_sort_id;
				if ( $list_sort_id > 0 )
				{
					$list_sort = $this->filters( 'sd_mt_get_list_sort', $list_sort_id );
					if ( $list_sort !== false )
						$info[] = $this->_( 'Sort:' ) . ' ' . $list_sort->data->name;
				}
				
				// Included lists
				$sorted_include_list = array();
				foreach( $list->includes as $include )
				{
					$included_list = $this->filters( 'sd_mt_get_list', $include );
					$sorted_include_list[ $included_list->data->name ] = $included_list; 
				}
				ksort( $sorted_include_list );
				
				foreach( $sorted_include_list as $included_list )
					$info[] = sprintf( '+ <em>%s</em>', $included_list->data->name );

				// Excluded lists
				$sorted_exclude_list = array();
				foreach( $list->excludes as $exclude )
				{
					$excluded_list = $this->filters( 'sd_mt_get_list', $exclude );
					$sorted_exclude_list[ $excluded_list->data->name ] = $excluded_list; 
				}
				ksort( $sorted_exclude_list );
				
				foreach( $sorted_exclude_list as $excluded_list )
					$info[] = sprintf( '- <em>%s</em>', $excluded_list->data->name );

				$info = implode( '</div><div>', $info );
				
				// Build a complete list of participants, for the sake of counting.
				$manual_count = count( $list->participants );
				$list = $this->filters( 'sd_mt_list_participants', $list );
				
				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input($input_list_select) . ' <span class="screen-reader-text">' . $form->make_label($input_list_select) . '</span></th>
					<td>
						<div>
							<a
							title="' . $this->_('Show participants') . '"
							href="'. $show_action_url .'">' . $list->data->name . '</a>
						</div>
						<div class="row-actions">' . $actions . '</a>
					</td>
					<td>' . $manual_count . '</td>
					<td><a title="' . $this->_('Show the combined participants') . '" href="' . $show_action_url . '">' . count( $list->participants ) . '</a></td>
					<td><div>' . $info . '</div></td>
				</tr>';
			}
			
			$input_actions = array(
				'type' => 'select',
				'name' => 'action',
				'label' => $this->_('With the selected rows'),
				'options' => array(
					''			=> $this->_('Do nothing'),
					'clone'		=> $this->_('Clone'),
					'compare'	=> $this->_('Compare'),
					'delete'	=> $this->_('Delete'),
					'empty'		=> $this->_('Empty'),
				),
			);
			$form->use_post_value( $input_actions, $_POST );
			
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
							<th>' . $this->_('Name') . '</th>
							<th><span title="' . $this->_( 'Only manually added participants are counted, not participants automatically combined from included lists.' ) . '">' . $this->_('Participants') . '</span></th>
							<th><span title="' . $this->_( 'Number of participants that are combined from any included and excluded lists.' ) . '">' . $this->_('Combined participants') . '</span></th>
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
		$input_list_create = array(
			'type' => 'submit',
			'name' => 'create_list',
			'value' => $this->_( 'Create a new list' ),
			'css_class' => 'button-primary',
		);
		
		$rv .= '
			<p>
				' . $form->make_input( $input_list_create ) . '
			</p>
		';
		
		$rv .= $form->stop();
		
		echo $rv;
	}
	
	/**
		Edit a list.
	**/
	public function admin_edit()
	{
		$form = $this->form();
		$id = $_GET['id'];
		$rv = '';
		
		$inputs = array(
			'name' => array(
				'type' => 'text',
				'name' => 'name',
				'label' => $this->_( 'Name' ),
				'size' => 50,
				'maxlength' => 200,
			),
			'display_format' => array(
				'type' => 'select',
				'name' => 'display_format',
				'label' => $this->_( 'Display format' ),
				'description' => $this->_( 'How to display the participants in the list.' ),
				'options' => array(),
			),
			'list_sort' => array(
				'type' => 'select',
				'name' => 'list_sort',
				'label' => $this->_( 'Sort' ),
				'description' => $this->_( 'How to sort this list.' ),
				'options' => array(),
			),
			'includes' => array(
				'type' => 'select',
				'name' => 'includes',
				'label' => $this->_( 'Included lists' ),
				'description' => $this->_( 'Include the participants that are in the selected lists.' ),
				'size' => 10,
				'css_style' => 'height: auto;',
				'multiple' => true,
			),
			'excludes' => array(
				'type' => 'select',
				'name' => 'excludes',
				'label' => $this->_( 'Excluded lists' ),
				'description' => $this->_( 'Exclude the participants that are in the selected lists.' ),
				'size' => 10,
				'css_style' => 'height: auto;',
				'multiple' => true,
			),
			'update' => array(
				'type' => 'submit',
				'name' => 'update',
				'value' => $this->_( 'Update list' ),
				'css_class' => 'button-primary',
			),
		);
		
		if ( isset( $_POST['update'] ) )
		{
			$result = $form->validate_post( $inputs, array_keys( $inputs ), $_POST );

			if ($result === true)
			{
				$list = $this->filters( 'sd_mt_get_list', $id );
				$list->data->name = $_POST['name'];
				$list->includes = isset( $_POST['includes'] ) ? $_POST['includes'] : array();
				$list->excludes = isset( $_POST['excludes'] ) ? $_POST['excludes'] : array();
				$list->data->display_format_id = $_POST['display_format'];
				$list->data->list_sort_id = $_POST['list_sort'];
				
				$this->filters( 'sd_mt_update_list', $list );
				
				$this->message( $this->_('The list has been updated!') );
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}

		if ( isset( $_POST['update_participants'] ) )
		{
			$list = $this->filters( 'sd_mt_get_list', $id );
			switch( key( $_POST['update_participants'] ) )
			{
				case 'update_select':
					$list->participants = isset( $_POST['all_participants'] ) ? $_POST['all_participants'] : array() ;
					break;
				case 'update_textarea':
					$ids = $_POST['participants_textarea'];
					$ids = str_replace( ',', ' ', $ids );
					$ids = str_replace( "\r", ' ', $ids );
					$ids = str_replace( "\n", ' ', $ids );
					$ids = explode( ' ', $ids );
					$ids = array_filter( $ids );
					foreach( $ids as $pid )
					{
						$participant = $this->filters( 'sd_mt_get_participant', $pid );
						if ( $participant === false )
							continue;
						$list->participants[ $pid ] = $pid;
					}
					break;
				default:
					$_POST['participants'] = isset( $_POST['participants'] ) ? $_POST['participants'] : array() ;
					$list->participants = array_keys( $_POST['participants'] );
					break;
			}
			$this->filters( 'sd_mt_update_list', $list );
			$this->message( $this->_('The list has been updated!') );
			SD_Meeting_Tool::reload_message();
			
			// Because the value part of the form expects the array keys to be the set values.
			$list->participants = array_flip( $list->participants );
		}
		
		$list = $this->filters( 'sd_mt_get_list', $id );
		$lists = $this->filters( 'sd_mt_get_all_lists', $id );
		
		// Put the options in.
		$list_options = array();
		foreach( $lists as $a_list )
		{
			if ( $a_list->id == $id )
				continue;
			$list_options[ $a_list->id ] = $a_list->data->name;
		}
		$inputs['includes']['options'] = $list_options;
		$inputs['excludes']['options'] = $list_options;
		
		$inputs['name']['value'] = $list->data->name;
		$inputs['includes']['value'] = SD_Meeting_Tool::array_intval( $list->includes );
		$inputs['excludes']['value'] = SD_Meeting_Tool::array_intval( $list->excludes );
		
		// Put the display format options in
		$display_formats = $this->filters( 'sd_mt_get_all_display_formats', array() );
		foreach( $display_formats as $display_format )
			$inputs['display_format']['options'][ $display_format->id ] = $display_format->data->name;
		$inputs['display_format']['value'] = intval( $list->data->display_format_id );
		
		// And the sort order options
		$list_sorts = $this->filters( 'sd_mt_get_all_list_sorts', array() );
		foreach( $list_sorts as $list_sort )
			$inputs['list_sort']['options'][ $list_sort->id ] = $list_sort->data->name;
		$inputs['list_sort']['value'] = intval( $list->data->list_sort_id );
		
		$rv .= '<h3>' . $this->_('List settings') . '</h3>';

		$rv .= '
			' . $form->start() . '
			
			' . $this->display_form_table( $inputs ). '

			' . $form->stop() . '
		';
		
		$rv .= '<h3>' . $this->_('Participants') . '</h3>';

		$all_participants = $this->filters( 'sd_mt_get_all_participants', array() );
		
		// Sort the participants, if possible.
		$all_participants = $this->sort_participants( $all_participants, $list );
		
		$inputs_participants = array();
		
		$inputs = array(
			'all_participants' => array(
				'name' => 'all_participants',
				'type' => 'select',
				'label' => $this->_( 'Participants' ),
				'multiple' => true,
				'options' => array(),
				'size' => 10,
				'css_style' => 'height: auto;',
			),
			'update_select' => array(
				'type' => 'submit',
				'name' => 'update_select',
				'nameprefix' => '[update_participants]',
				'value' => $this->_('Apply from the above select box'),
				'css_class' => 'button-primary',
			), 
			'participants_textarea' => array(
				'type' => 'textarea',
				'name' => 'participants_textarea',
				'label' => $this->_( "ID's of participants to add to the list." ),
				'cols' => 40,
				'rows' => 5,
				'validation' => array( 'empty' => true ),
			),
			'update_textarea' => array(
				'type' => 'submit',
				'name' => 'update_textarea',
				'nameprefix' => '[update_participants]',
				'value' => $this->_('Apply from the above text area'),
				'css_class' => 'button-primary',
			), 
			'update_checkboxes' => array(
				'type' => 'submit',
				'name' => 'update_checkboxes',
				'nameprefix' => '[update_participants]',
				'value' => $this->_('Apply from the checkboxes below'),
				'css_class' => 'button-primary',
			), 
		);
		
		$display_format = $this->filters( 'sd_mt_get_display_format', $list->data->display_format_id );
		
		foreach( $all_participants as $participant )
		{
			$display_name = $this->filters( 'sd_mt_display_participant', $participant, $display_format );
			$input = array(
				'type' => 'checkbox',
				'name' => $participant->id,
				'label' => $display_name,
				'nameprefix' => '[participants]',
				'checked' => isset( $list->participants[ $participant->id ] ),
			);
			$inputs_participants[] = $input;
			$inputs['all_participants']['options'][] = array( 'text' => $display_name, 'value' => intval($participant->id) );
		}
		
		$inputs = array_merge( $inputs, $inputs_participants );
		
		$inputs['participants_textarea']['value'] = implode( "\n", array_keys( $list->participants ) );
		$inputs['all_participants']['value'] = SD_Meeting_Tool::array_intval( array_keys( $list->participants ) );
		
		$rv .= $form->start();
		$rv .= $this->display_form_table( $inputs );
		$rv .= $form->stop();
		
		echo $rv;
	}

	/**
		Copy the participants of a list to other lists.
	**/
	public function admin_copy_participants()
	{
		$rv = '';
		$list_id = $_GET['id'];
		$form = $this->form();
		
		if ( isset( $_POST[ 'submit' ] ) && isset( $_POST[ 'to' ] ) && count( $_POST[ 'to' ] ) > 0 )
		{
			// Get the participants of this list.
			$list = $this->filters( 'sd_mt_get_list', $list_id );
			$list = $this->filters( 'sd_mt_list_participants', $list );
			foreach( $_POST[ 'to' ] as $target_list_id )
			{
				$target_list = $this->filters( 'sd_mt_get_list', $target_list_id );
				if ( $target_list === false )
					continue;
				$target_list->participants = $list->participants;
				$this->filters( 'sd_mt_update_list', $target_list );
			}
			$this->message( $this->_ ('The participants have been copied to %s lists!', count( $_POST[ 'to' ] ) ) );
		}
		
		$inputs = array(
			'to' => array(
				'name' => 'to',
				'type' => 'select',
				'label' => $this->_( 'Destination lists' ),
				'description' => $this->_( 'Select the lists to which to copy the participants.' ),
				'css_style' => 'height: auto;',
				'size' => 10,
				'multiple' => true,
				'options' => array(),
			),
			'submit' => array(
				'name' => 'submit',
				'type' => 'submit',
				'value' => $this->_( 'Copy' ),
				'css_class' => 'button-primary',
			),
		);
		
		$lists = $this->filters( 'sd_mt_get_all_lists', array() );
		foreach( $lists as $list_id => $list )
			$inputs[ 'to' ][ 'options' ][ $list_id ] = $list->data->name;
		
		$rv .=
			$form->start() 
			. $this->display_form_table( $inputs )
			. $form->stop(); 
		
		echo $rv;
	}
	
	/**
		Show list collected participants.
	**/
	public function admin_show_participants()
	{
		$rv = '';
		$list_id = $_GET['id'];
		
		$list = $this->filters( 'sd_mt_get_list', $list_id );
		$list = $this->filters( 'sd_mt_list_participants', $list );
		
		$display_format = $this->filters( 'sd_mt_get_display_format', $list->data->display_format_id );
		
		$t_body = '';
		foreach( $list->participants as $participant )
		{
			$display_name = $this->filters( 'sd_mt_display_participant', $participant, $display_format );
			$t_body .= '
				<tr>
					<td>' . $display_name . '</td>
					<td>' . date( 'Y-m-d H:i:s', $participant->registered ) . '</td>
				</tr>
			';
		}
		
		$rv .= '
			<p>
				'. $this->_( '%s participants.', count( $list->participants ) ). '
			</p>
			<table class="widefat">
				<thead>
					<tr>
						<th>' . $this->_('Participant') . '</th>
						<th>' . $this->_('Registered') . '</th>
					</tr>
				</thead>
				<tbody>
					' . $t_body . '
				</tbody>
			</table>
		';
		
		echo $rv;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Actions
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_configure_action_item( $item )
	{
		$this->load_language();
		$this->strings();
		
		switch( $item->type )
		{
			case 'add_list_participant':
			case 'remove_list_participant':
				if ( !isset( $item->data->list_id ) )
					$item->data->list_id = array(); 
				
				if ( isset( $_POST['update_item'] ) )
				{
					if ( !isset( $_POST['list_id'] ) )
						$_POST['list_id'] = array();
					
					$item->data->list_id = array();
					foreach( $_POST['list_id'] as $list_id )
						$item->data->list_id[] = intval( $list_id );
					
					$this->filters( 'sd_mt_update_action_item', $item );
					$this->message( $this->_('The action item has been updated!') ); 
				}
				
				$form = $this->form();
				
				$label = $this->strings[ $item->type ][ 'empty' ];
				$lists = $this->filters( 'sd_mt_get_all_lists', null );
				$list_id_options = array();
				foreach( $lists as $list )
					$list_id_options[ $list->id ] = $list->data->name;
				
				$inputs = array(
					'list_id' => array(
						'type' => 'select',
						'name' => 'list_id',
						'label' => $label,
						'description' => $this->_('Multiple lists can be selected.'),
						'size' => 10,
						'multiple' => true,
						'value' => $item->data->list_id,
						'css_style' => 'height: auto;',
						'options' => $list_id_options,
					),
					'update_item' => array(
						'type' => 'submit',
						'name' => 'update_item',
						'value' => $this->_( 'Apply' ),
						'css_class' => 'button-primary',
					),
				);
				
				$rv = $form->start();
				$rv .= $this->display_form_table( $inputs );
				$rv .= $form->stop();
				
				echo $rv;
				return;
		}
	}
	
	/**
		Triggers an item.
		
		Requires that ->action_item and ->trigger is set.
		
		@param		$item_trigger		SD_Meeting_Tool_Action_Item_Trigger		Action item trigger.
	**/
	public function sd_mt_trigger_action_item( $item_trigger )
	{
		$item = $item_trigger->action_item;		// Convenience.
		switch( $item->type )
		{
			case 'add_list_participant':
				if ( ! is_a( $item_trigger->trigger, 'SD_Meeting_Tool_Participant' ) )
					return;
				$participant = $item_trigger->trigger;		// Convenience.
				foreach( $item->data->list_id as $list_id )
				{
					$list = $this->filters( 'sd_mt_get_list', $list_id );
					if ( $list === false )
						continue;
					$this->filters( 'sd_mt_add_list_participant', $list, $participant );
				}
				break;
			case 'remove_list_participant':
				if ( ! is_a( $item_trigger->trigger, 'SD_Meeting_Tool_Participant' ) )
					return;
				$participant = $item_trigger->trigger;		// Convenience.
				foreach( $item->data->list_id as $list_id )
				{
					$list = $this->filters( 'sd_mt_get_list', $list_id );
					if ( $list === false )
						continue;
					$this->filters( 'sd_mt_remove_list_participant', $list, $participant );
				}
				break;
		}
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_add_list_participant( $SD_Meeting_Tool_List, $SD_Meeting_Tool_Participant )
	{
		$this->sd_mt_add_list_participants( $SD_Meeting_Tool_List, array( $SD_Meeting_Tool_Participant ) );
	}
	
	public function sd_mt_add_list_participants( $SD_Meeting_Tool_List, $SD_Meeting_Tool_Participants )
	{
		foreach( $SD_Meeting_Tool_Participants as $SD_Meeting_Tool_Participant )
			if ( is_object( $SD_Meeting_Tool_Participant ) )
				$SD_Meeting_Tool_List->participants[ $SD_Meeting_Tool_Participant->id ] = $SD_Meeting_Tool_Participant->id;
			else
				$SD_Meeting_Tool_List->participants[ $SD_Meeting_Tool_Participant ] = $SD_Meeting_Tool_Participant;
		$this->filters( 'sd_mt_update_list', $SD_Meeting_Tool_List );
	}
	
	public function sd_mt_clone_list( $list )
	{
		// Clone the list itself.
		$new_list = clone( $list );
		$new_list->id = null;
		$new_list->data->name = $this->_( 'Clone of %s', $list->data->name );
		$new_list = $this->filters( 'sd_mt_update_list', $new_list );

		$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_list_participants`
			(`id`, `participant_id`, `registered`)
			( SELECT '".$new_list->id."' as `id`, `participant_id`, `registered` FROM `".$this->wpdb->base_prefix."sd_mt_list_participants`
			WHERE `id` = '" . $list->id . "')
		";
		$this->query( $query );

		$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_list_excludes`
			(`id`, `excluded_list_id` )
			( SELECT '".$new_list->id."' as `id`, `excluded_list_id` FROM `".$this->wpdb->base_prefix."sd_mt_list_excludes`
			WHERE `id` = '" . $list->id . "')
		";
		$this->query( $query );

		$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_list_includes`
			(`id`, `included_list_id` )
			( SELECT '".$new_list->id."' as `id`, `included_list_id` FROM `".$this->wpdb->base_prefix."sd_mt_list_includes`
			WHERE `id` = '" . $list->id . "')
		";
		$this->query( $query );
		
		return $new_list;
	}
	
	public function sd_mt_delete_list( $list )
	{
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_lists`
			WHERE `id` = '" . $list->id . "'";
		$this->query( $query );

		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_list_participants`
			WHERE `id` = '" . $list->id . "'";
		$this->query( $query );
	}
	
	public function sd_mt_get_all_lists()
	{
		global $blog_id;
		$query = "SELECT id FROM `".$this->wpdb->base_prefix."sd_mt_lists` WHERE `blog_id` = '$blog_id'";
		$results = $this->query( $query );
		$rv = array();
		
		foreach( $results as $result )
			$rv[ $result['id'] ] = $this->sd_mt_get_list( $result['id'] );

		return SD_Meeting_Tool::sort_data_array( $rv, 'name' );
	}
	
	public function sd_mt_get_list( $list_id )
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_lists` WHERE `id` = '$list_id' AND `blog_id` = '$blog_id'";
		$result = $this->query_single( $query );
		if ( $result === false )
			return false;

		$list = $this->sql_to_list( $result );
		
		// Put in the includes and excludes
		foreach( array('includes' => 'included_list_id', 'excludes' => 'excluded_list_id' ) as $type => $column_name )
		{
			$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_list_".$type."` WHERE `id` = '$list_id'";
			$lists = $this->query( $query );
			$built_list = array();		// Since we can't say $result->$type[]
			foreach( $lists as $extra_list )
				$built_list[] = $extra_list[ $column_name ];
			$list->$type = $built_list;
		}
		
		// Put in the participants
		$list->participants = $this->get_list_participants( $list );
		
		return $list;
	}
	
	public function sd_mt_list_participants( $list )
	{
		if ( $list === false )
			return false;

		$collection = new SD_Meeting_Tool_List_Collection();
		$this->collect_participants( $collection, $list );
		$list->participants = $collection->participants;
		
		foreach( $collection->participants as $participant_id => $participant )
		{
			$list->participants[ $participant_id ] = (object) array_merge(
				(array)$this->filters( 'sd_mt_get_participant', $participant_id ),
				(array)$participant
			);
		}
		
		$list = $this->sort_list( $list );
		
		return $list;
	}
	
	public function sd_mt_remove_list_participant( $list, $participant )
	{
		if ( isset( $list->participants[ $participant->id ] ) )
		{
			unset( $list->participants[ $participant->id ] );
			$this->filters( 'sd_mt_update_list', $list );
		}
	}

	public function sd_mt_remove_list_participants( $list )
	{
		$list->participants = array();
		$this->filters( 'sd_mt_update_list', $list );
	}

	public function sd_mt_update_list( $list )
	{
		global $blog_id;
		
		$data = $this->sql_encode( $list->data );
		 
		if ( $list->id === null )
		{
			$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_lists`
				(`blog_id`, `data`)
				VALUES
				('". $blog_id ."', '" . $data . "')
			";
			$list->id = $this->query_insert_id( $query );
		}
		else
		{
			$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_lists`
				SET
				`data` = '" . $data. "'
				WHERE `id` = '" . $list->id . "'
				AND `blog_id` = '" . $blog_id . "'
			";
			$this->query( $query );
		}

		// Includes and excludes
		foreach( array('includes' => 'included_list_id', 'excludes' => 'excluded_list_id' ) as $type => $column_name )
		{
			$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_list_".$type."`
				WHERE `id` = '" . $list->id . "'";
			$this->query ( $query );

			if ( count($list->$type) < 1 )
				continue;
				
			$list_ids = implode( "'), ('".$list->id."', '", $list->$type );

			$query = "INSERT INTO `".$this->wpdb->base_prefix."sd_mt_list_".$type."`
				( `id`, `".$column_name."` ) VALUES
				( '".$list->id."', '".$list_ids."')";
			$this->query ( $query );
		}
		
		$this->set_list_participants( $list );
		
		return $list;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters (external)
	// --------------------------------------------------------------------------------------------
	
	/**
		Clean up removed participants.
		@param	$SD_Meeting_Tool_Participant	Participant being removed.
	**/
	public function sd_mt_delete_participant( $SD_Meeting_Tool_Participant )
	{
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_list_participants`
			WHERE
			`participant_id` = '" . $SD_Meeting_Tool_Participant->id . "'
		";
		$this->query ( $query );
		
		return $SD_Meeting_Tool_Participant;
	}
	
	public function sd_mt_display_participant_field( $SD_Meeting_Tool_Participant_Field )
	{
		if ( $SD_Meeting_Tool_Participant_Field->name == 'registered' )
		{
			$SD_Meeting_Tool_Participant_Field->value = date('Y-m-d H:i:s', $SD_Meeting_Tool_Participant_Field->value );
		}
		return $SD_Meeting_Tool_Participant_Field;
	}
	
	/**
		Provides list action items.
		
		@param	$action_items		Array of SD_Meeting_Tool_Action_Item.
		@return						Updated array of SD_Meeting_Tool_Action_Item. 
	**/
	public function sd_mt_get_action_item_types( $action_items )
	{
		$this->load_language();
		$this->strings();
		
		$item = new SD_Meeting_Tool_Action_Item();
		$item->type = 'add_list_participant';
		$item->description = $this->strings[ $item->type ]['empty'];
		$action_items[] = $item; 

		$item = new SD_Meeting_Tool_Action_Item();
		$item->type = 'remove_list_participant';
		$item->description = $this->strings[ $item->type ]['empty'];
		$action_items[] = $item;
		
		return $action_items;
	}
	
	/**
		Describes the action provided (if it's our own).
		
		@param	$SD_Meeting_Tool_Action_Item	SD_Meeting_Tool_Action_Item to describe.
		@return									Updated array of SD_Meeting_Tool_Action_Item. 
	**/
	public function sd_mt_get_action_item_description( $SD_Meeting_Tool_Action_Item )
	{
		$this->load_language();
		$this->strings();
				
		$item = $SD_Meeting_Tool_Action_Item;		// Convenience.
		
		$rv = '';
		switch( $item->type )
		{
			case 'add_list_participant':
			case 'remove_list_participant':
				if ( $item->data === null || ! isset( $item->data->list_id ) )
				{
					$rv = $this->strings[ $item->type ]['empty'];
				}
				else
				{
					$rv = '';
					$lists = array();
					foreach( $item->data->list_id as $list_id )
					{
						$list = $this->filters( 'sd_mt_get_list', $list_id );
						if ( $list === false )
							$lists[] = $this->_('Unknown');
						else
							$lists[] = $list->data->name;
					}
					$rv .= sprintf(
						$this->strings[ $item->type ]['full'],
						implode( '</em><br /><em>', $lists )
					);
				}
				break;
		}

		$SD_Meeting_Tool_Action_Item->description = $rv;
		return $SD_Meeting_Tool_Action_Item;
	}
	
	/**
		Append "registered" as a field.
		
		@param	$fields		Array of SD_Meeting_Tool_Participant_Field
		@return				The array, but with a new field: registered.
	**/
	public function sd_mt_get_participant_fields( $fields )
	{
		$field = new SD_Meeting_Tool_Participant_Field();
		$field->name = 'registered';
		$field->description = $this->_( 'Registered' );
		$field->is_native = false;
		$fields[] = $field;
		return $fields;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------
	
	/**
		Recurses a list and updates the collection with participants.
		
		Each list include and exclude is calculated separately and then merged into the mainline collection.
		
		Explanation: If the collection has an include, i1, that includes i2 and excludes i3, i2 and i3 will be merge and whatever is left will be included in i1.
		
		Likewise, if the collection has an exclude e1, that includes e2 and excludes i1, first i1 will be calculated and then e2 - i1, and whatever is left is excluded from e1.
		
		Has primitive recursion prevention: will ignore and lists that it has seen previously in that include/exclude tree.
		
		@param		$SD_Meeting_Tool_List_Collection			Existing SD_Meeting_Tool_List_Collection to add to.
		@param		$SD_Meeting_Tool_List						SD_Meeting_Tool_List to parse.
	**/
	private function collect_participants( $SD_Meeting_Tool_List_Collection, $SD_Meeting_Tool_List )
	{
		$collection = $SD_Meeting_Tool_List_Collection;		// Convenience.
		$list = $SD_Meeting_Tool_List;						// Convenience.
		
		if ( $collection->seen( $list->id ) )
			return false;

		// Add the list's participants
		$collection->include_participants( $list->participants );
		
		// Add from the includes.
		$include_collection = new SD_Meeting_Tool_List_Collection();
		foreach( $list->includes as $list_id )
		{
			$include_collection->seen = $collection->seen;
			
			$included_list = $this->filters( 'sd_mt_get_list', $list_id );
			$this->collect_participants( $include_collection, $included_list );
		}		
		$collection->seen( $include_collection->seen );
		$collection->include_participants( $include_collection->participants );
	
		// And now remove all from the excludes.
		$exclude_collection = new SD_Meeting_Tool_List_Collection();
		foreach( $list->excludes as $list_id )
		{
			$exclude_collection->seen = $collection->seen;
			
			$excluded_list = $this->filters( 'sd_mt_get_list', $list_id );
			$this->collect_participants( $exclude_collection, $excluded_list );
		}
		$collection->seen( $exclude_collection->seen );
		$collection->exclude_participants( $exclude_collection->participants );
	}
	
	/**
		@brief		Compares several lists and shows a comparison table.
		@param		$list_ids
					Array of list ids.
		
		@return		HTML table comparing the lists.
	**/
	public function compare_lists( $list_ids )
	{
		$rv = '';
		
		$lists = array();
		$participants = array();
		foreach( $list_ids as $list_id )
		{
			$list = $this->filters( 'sd_mt_get_list', $list_id );
			if ( $list === false )
				continue;
			$lists[] = $list;
			$list = $this->filters( 'sd_mt_list_participants', $list );
			$list_name = $list->data->name;
			
			$display_format_id = $list->display_format_id();
			$display_format = $this->filters( 'sd_mt_get_display_format', $display_format_id );
			
			foreach( $list->participants as $participant )
			{
				$participant_id = $participant->id;
				if ( ! isset( $participants[ $participant_id ] ) )
				{
					$p = new stdClass();
					$p->name = $this->filters( 'sd_mt_display_participant', $participant, $display_format );
					$p->lists = new stdClass();
					$participants[ $participant_id ] = $p;
				}
				$participants[ $participant_id ]->lists->$list_name = $list_name;
			}
		}
		
		$t_body = array();
		foreach( $participants as $participant_id => $participant )
		{
			$row = array();
			$row[] = '<td>' . $participant_id . '</td>';
			$row[] = '<td>' . $participant->name . '</td>';
			foreach( $lists as $list )
			{
				$list_name = $list->data->name;
				$td = sprintf( '<td title="%s" class="', $list_name ); 
				if( isset( $participant->lists->$list_name  ) )
					$row[] = $td . 'yes">' . $this->_( 'Yes' ) . '</td>';
				else
					$row[] = $td . 'no">' . $this->_( 'No' ) . '</td>';
			}
			$t_body[] = implode( '', $row );
		}
		
		$t_head = array(
			'<th>' . $this->_( 'ID' ) . '</th>',
			'<th>' . $this->_( 'Participant' ) . '</th>'
		);
		foreach( $lists as $list )
			$t_head[] = '<th>' . $list->data->name . '</th>';
			
		$rv .= '
			<table class="widefat compare_participants">
				<thead>
					<tr>
						'. implode( $t_head ) .'
					</tr>
				</thead>
				<tbody>
					<tr>
						'. implode( '</tr><tr>', $t_body ) .'
					</tr>
				</tbody>
			</table>
		';
		
		return $rv;
	}
	
	/**
		Convert a list row from SQL to an SD_Meeting_Tool_List.
		
		@param		$sql		Row from the database as an array.
		@return					A complete SD_Meeting_Tool_List object.
	**/ 
	private function sql_to_list( $sql )
	{
		$list = new SD_Meeting_Tool_List();
		$list->id = $sql['id'];
		$list->data = (object) array_merge( (array)$list->data, (array)$this->sql_decode( $sql['data'] ) );
		return $list;
	}
	
	/**
		Returns a list of all the participants of a list.
		
		Note that the list isn't assembled, only the manually entered users are listed.
		
		@return					Array of {SD_Meeting_Tool_List_Participant}s
	**/
	private function get_list_participants( $list )
	{
		$rv = array();
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_list_participants` WHERE `id` = '".$list->id."'";
		$result = $this->query( $query );
		
		foreach( $result as $participant )
		{
			$participant_id = $participant['participant_id'];
			$list_participant = new SD_Meeting_Tool_List_Participant();
			$list_participant->id = $participant_id;
			$list_participant->registered = strtotime( $participant['registered'] );
			$rv[ $participant_id ] = $list_participant;
		}
		
		return $rv;
	}
	
	/**
		Sets which participants are in a list.
		
		Only makes changes.
		
		New participants are taken från the ->participants field.
		The field can be an array of participant IDs or whole participant objects.
		
		@param		$SD_Meeting_Tool_List		List object with new ->participants.
	**/
	private function set_list_participants( $SD_Meeting_Tool_List )
	{
		$list = $SD_Meeting_Tool_List;		// Convenience
		$p = new StdClass();
		$p->removed = $this->get_list_participants( $list );
		$this->query ( "LOCK TABLES `".$this->wpdb->base_prefix."sd_mt_list_participants` WRITE" );
		$p->new = array();
		foreach( $list->participants as $participant_id )
		{
			// If we were given an array of objects, extract the p_id 
			if ( is_object ( $participant_id ) )
				$participant_id = $participant_id->id;
			
			if ( !isset( $p->removed[$participant_id] ) )
				$p->new[] = $participant_id;
			else
				unset( $p->removed[$participant_id] );
		}
		
		// We are now left with new ones in new, and old, unused participants in removed.
		if ( count($p->new) > 0 )
		{
			// Add the new ones.
			$new_participants = implode( "'), ('".$list->id."', now(), '", $p->new );
			$query = "INSERT INTO `".$this->wpdb->base_prefix."sd_mt_list_participants`
				( `id`, registered, `participant_id` ) VALUES
				( '".$list->id."', now(), '".$new_participants."')";
			$this->query ( $query );
		}

		// Trash the removed ones.
		if ( count($p->removed) > 0 )
		{
			$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_list_participants`
				WHERE
				`id` = '" . $list->id . "'
				AND `participant_id` IN ('". implode("','", array_keys($p->removed) ) ."')";
			$this->query ( $query );
		}
		$this->query ( "UNLOCK TABLES" );
	}
	
	/**
		Sorts a list according to its list sort.
		@param		$SD_Meeting_Tool_List			SD_Meeting_Tool_List to sort.
		@return		Sorted SD_Meeting_Tool_List.
	**/
	private function sort_list( $SD_Meeting_Tool_List )
	{
		$list = $SD_Meeting_Tool_List;		// Convenience
		$list_sort = $this->filters( 'sd_mt_get_list_sort', $list->data->list_sort_id );	// Convenience
		$list = $this->filters( 'sd_mt_sort_list', $list, $list_sort );
		return $list;
	}
	
	/**
		Sort the participants using the list's sort.
		@param	$SD_Meeting_Tool_Participants		Array of $SD_Meeting_Tool_Participant.
		@param	$SD_Meeting_Tool_List				List to fetch sort values from.
		@return										Sorted array of $SD_Meeting_Tool_Participant.
	**/
	private function sort_participants( $SD_Meeting_Tool_Participants, $SD_Meeting_Tool_List )
	{
		$templist = clone( $SD_Meeting_Tool_List );
		$templist->participants = $SD_Meeting_Tool_Participants;
		$templist = $this->sort_list( $templist );
		return $templist->participants;
	}
	
	/**
		Load the translated strings.
	**/
	private function strings()
	{
		$this->strings = array(
			'add_list_participant' => array(
				'empty' => $this->_( 'Add the participant to a list' ),
				'full' => $this->_( 'Add the participant to: <br /><em>%s</em>', '%s' ),			// Observe the ugly hack to keep the %s in its place.
			),
			'remove_list_participant' => array(
				'empty' => $this->_( 'Remove the participant from a list' ),
				'full' => $this->_( 'Remove the participant from: <br /><em>%s</em>', '%s' ),		// Observe the ugly hack to keep the %s in its place.
			),
		);
	}
	
}
$SD_Meeting_Tool_Lists = new SD_Meeting_Tool_Lists();

// ---------------------------------------
//	class SD_Meeting_Tool_List_Participant 
// ---------------------------------------

/**
	@brief		A list participant is a participant, added to a list at a specific time.
	@see		SD_Meeting_Tool_List
	@see		SD_Meeting_Tool_Lists
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_List_Participant
{
	/**
		ID of participant.
		@var	$id
	**/
	public $id;
	
	/**
		Unix timestamp of when the participant was registered in this list.
		@var	$registered
	**/
	public $registered;
}

// --------------------------------------
//	class SD_Meeting_Tool_List_Collection 
// --------------------------------------

/**
	@brief		Stores information about participants both from a base list and any included/excluded lists.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_List_Collection
{
	/**
		List of list ids we have seen.
		
		@var	$seen
	**/
	public $seen = array();
	
	/**
		Array of list participants in this collection.
		@var	$participants
	**/
	public $participants = array();
	
	/**
		Queries whether this list has been seen. Also adds it to the list of seen lists.
		
		@param		$list_id		int|array		List id or array thereof that we've seen.
		@return		bool							False if we haven't seen this list, true if we have.
	**/
	public function seen( $list_id )
	{
		if ( ! is_array( $list_id ) )
			$list_id = array( $list_id );
			
		foreach( $list_id as $id )
			if ( isset( $this->seen[$id] ) )
				return true;
			
		// Nope. We haven't seen any of the specified lists.
		// Add them.
		foreach( $list_id as $id )
			$this->seen[$id] = $id;

		return false;
	}
	
	/**
		Includes an array of participants.
		
		@param		$participants		array		Participant IDs in an array.
	**/
	public function include_participants( $participants )
	{
		foreach( $participants as $participant )
			$this->participants[ $participant->id ] = $participant;
	}
	
	/**
		Excludes an array of participants.
		
		@param		$participants		array		Participant IDs in an array.
	**/
	public function exclude_participants( $participants )
	{
		foreach( $participants as $participant )
			unset( $this->participants[ $participant->id ] );
	}

}