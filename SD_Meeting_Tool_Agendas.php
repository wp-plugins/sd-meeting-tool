<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool Agendas
Plugin URI: https://it.sverigedemokraterna.se
Description: Provides agendas.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	@page		sd_meeting_tool_agendas					Plugin: Agendas
	
	@section	sd_meeting_tool_agendas_description		Description
	
	The agenda keeps a list of talking points for the meeting. A functionary can edit agenda items and select
	the current agenda item for each agenda.
	
	@ref sd_meeting_tool_agendas_usage_shortcodes can be used to display agendas and agenda items to visitors.
	
	@section	sd_meeting_tool_agendas_requirements	Requirements
	
	@ref index

	@section	sd_meeting_tool_agendas_installation	Installation
	
	Enable the plugin in Wordpress.
	
	The plugin will then create the following database tables:
	
	- @b prefix_sd_mt_agendas stores complete agendas.
	- @b prefix_sd_mt_agenda_items stores the items for each agenda.
	
	@subsection	sd_meeting_tool_agendas_installation_css	CSS
	
	Displaying of shortcodes can be controlled using the following CSS: 
	
	@par	[show_agenda]
	
	@code
	ol.sd_mt_agenda li.current_item
	{
		background-color: #cceaf4;
	}
	@endcode

	@par	[current_agenda_item]
	
	@code
	span.sd_mt_agenda_item
	{
		background-color: #cceaf4;
	}
	@endcode

	@section		sd_meeting_tool_agendas_usage	Usage
	
	There can be several agendas per blog.
	
	Each agenda has several items.
	
	Each item consists of some text and, optionally, a link. The link can be anything: http, https, mailto, etc.
	
	Doubleclicking an agenda item will mark it as "current". The current agenda item can then be displayed to the visitors
	using a shortcode. Doubleclicking a selected item will deselect it.
	
	Items can be sorted by dragging them up and down in the item list. 
	
	@subsection		sd_meeting_tool_agendas_usage_shortcodes	Shortcodes
	
	The following shortcodes are available:
	
	@par	[show_agenda id="12" autorefresh="yes"]
	
	- @b id				The id of the agenda to display.
	- @b autorefresh		Use javascript to automatically refresh the agenda every 10 seconds.
	
	@par	[current_agenda_item id="12" link="yes" number="no" text="yes"]
	
	- @b autorefresh		Use javascript to automatically refresh the agenda item every 10 seconds.
	- @b id				ID of the agenda we're looking at.
	- @b link				Enable displaying of the item text as a link.
	- @b number			Show the number of the agenda item in the agenda. Starts with 1.
	- @b text				Display the item text.
	
	Specific combinations trigger special displays: link="yes" and text="no" displays the link.
	
	link="no" and text" displays the item text.
	
	link="no" and text="no" does ... nothing.

	@subsection		sd_meeting_tool_agendas_usage_todo	Todo
	
	- @b	Shortcodes		Use forms to create flexible shortcodes instead of static buttons.
	- @b	Object			Make the javascript use a separate object for each displayed agenda.
	
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
*/

/**
	@brief		Provides agenda services for SD Meeting Tool.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se

	@par		Changelog

	@par		1.1
	- Version bump.
**/
class SD_Meeting_Tool_Agendas
	extends SD_Meeting_Tool_0
	implements interface_SD_Meeting_Tool_Agendas
{
	public function __construct()
	{
		parent::__construct( __FILE__ );

		// Internal filters
		add_filter( 'sd_mt_delete_agenda',				array( &$this, 'sd_mt_delete_agenda' ) );
		add_filter( 'sd_mt_get_agenda',					array( &$this, 'sd_mt_get_agenda') );
		add_filter( 'sd_mt_get_all_agendas',			array( &$this, 'sd_mt_get_all_agendas') );
		add_filter( 'sd_mt_update_agenda',				array( &$this, 'sd_mt_update_agenda' ) );
		add_filter( 'sd_mt_update_agenda_item',			array( &$this, 'sd_mt_update_agenda_item' ) );
	
		// External actions
		add_filter( 'sd_mt_admin_menu',					array( &$this, 'sd_mt_admin_menu' ) );
		
		// Shortcodes
		add_shortcode('show_agenda',					array( &$this, 'shortcode_show_agenda') );
		add_shortcode('current_agenda_item',			array( &$this, 'shortcode_current_agenda_item') );
		
		// Ajax
		add_action('wp_ajax_sd_mt_agendas_ajax_admin',	array( &$this, 'ajax_admin') );		

		wp_enqueue_style( 'sd_mt_agendas', '/' . $this->paths['path_from_base_directory'] . '/css/SD_Meeting_Tool_Agendas.css', false, '1.0', 'screen' );
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_admin_menu( $menus )
	{
		$this->load_language();

		$menus[ $this->_('Agendas') ] = array(
			'sd_mt',
			$this->_('Agendas'),
			$this->_('Agendas'),
			'read',
			'sd_mt_agenda',
			array( &$this, 'admin' )
		);

		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_style( 'sd_mt_agendas', '/' . $this->paths['path_from_base_directory'] . '/css/SD_Meeting_Tool_Agendas.css', false, '1.0', 'screen' );
		return $menus;
	}
	
	public function activate()
	{
		parent::activate();

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_agendas` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `blog_id` int(11) NOT NULL COMMENT 'Blog ID',
		  `data` longtext NOT NULL COMMENT 'Serialized data stdclass containing ... data',
		  PRIMARY KEY (`id`),
		  KEY `blog_id` (`blog_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1 ;
		");

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_agenda_items` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `agenda_id` int(11) NOT NULL,
		  `order` smallint(6) NOT NULL DEFAULT '1',
		  `data` longtext NOT NULL,
		  PRIMARY KEY (`id`),
		  KEY `action_id` (`agenda_id`,`order`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
		");
	}
	
	public function uninstall()
	{
		parent::uninstall();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_agendas`");
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_agenda_items`");
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
			if ( $_GET['tab'] == 'edit_agenda' )
			{
				$tab_data['tabs']['edit_agenda'] = $this->_( 'Edit agenda');
				$tab_data['functions']['edit_agenda'] = 'admin_edit';

				$agenda = $this->filters( 'sd_mt_get_agenda', $_GET['id'] );
				if ( $agenda === false )
					wp_die( $this->_( 'Specified agenda does not exist!' ) );

				$tab_data['page_titles']['edit_agenda'] = $this->_( 'Editing agenda: %s', $agenda->data->name );
			}

			if ( $_GET['tab'] == 'edit_item' )
			{
				$tab_data['tabs']['edit_item'] = $this->_( 'Edit item');
				$tab_data['functions']['edit_item'] = 'admin_edit_item';

				$agenda = $this->filters( 'sd_mt_get_agenda', $_GET['id'] );
				if ( $agenda === false )
					wp_die( $this->_( 'Specified agenda does not exist!' ) );
				$item_id = $_GET['item'];
				if ( ! isset( $agenda->items[ $item_id ] ) )
					wp_die( $this->_( 'Specified agenda item does not exist!' ) );
				
				$tab_data['page_titles']['edit_item'] = $this->_( 'Editing action item: %s (%s)', $agenda->items[ $item_id ]->data->name, $item_id );
			}
		}

		$this->tabs($tab_data);
	}
	
	public function admin_overview()
	{
		$form = $this->form();
		$returnValue = $form->start();
		
		if ( isset( $_POST['action_submit'] ) && isset( $_POST['agendas'] ) )
		{
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['agendas'] as $agenda_id => $ignore )
				{
					$agenda = $this->filters( 'sd_mt_get_agenda', $agenda_id );
					if ( $agenda !== false )
					{
						$this->filters( 'sd_mt_delete_agenda', $agenda );
						$this->message( $this->_( 'Agenda <em>%s</em> deleted.', $agenda->data->name ) );
					}
				}
			}	// delete
		}
		
		if ( isset( $_POST['create_agenda'] ) )
		{
			$agenda = new SD_Meeting_Tool_Agenda();
			$agenda->data->name = $this->_( 'Agenda created %s', $this->now() );
			$agenda = $this->filters( 'sd_mt_update_agenda', $agenda );
			
			$edit_link = add_query_arg( array(
				'tab' => 'edit_agenda',
				'id' => $agenda->id,
			) );
			
			$this->message( $this->_( 'Agenda created! <a href="%s">Edit the newly-created agenda</a>.', $edit_link ) );
		}	// create agenda

		$agendas = $this->filters( 'sd_mt_get_all_agendas', array() );

		if ( count( $agendas ) < 1 )
			$this->message( $this->_( 'There are no agendas available.' ) );
		else
		{
			$t_body = '';
			foreach( $agendas as $agenda )
			{
				$input_select_agenda = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $agenda->data->name,
					'name' => $agenda->id,
					'nameprefix' => '[agendas]',
				);
				
				$edit_link = add_query_arg( array(
					'tab' => 'edit_agenda',
					'id' => $agenda->id,
				) );
				
				$info = array();
				
				$item_info = '<p>';
				// Add the items as info.
				foreach( $agenda->items as $item )
				{
					$current_agenda_item = ( $agenda->data->current_item_id == $item->id );
					$item_info .= ( $current_agenda_item ? '<strong>' : '' );
					$item_info .= $item->data->name . "<br />\n";
					$item_info .= ( $current_agenda_item ? '</strong>' : '' );
				}
				$item_info .= '</p>';
				$info[] = $item_info;
				$info = implode( '</div><div>', $info );
				
				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input( $input_select_agenda ) . ' <span class="screen-reader-text">' . $form->make_label( $input_select_agenda ) . '</span></th>
					<td>
						<div>
							<a
							title="' . $this->_('Edit this agenda') . '"
							href="'. $edit_link .'">' . $agenda->data->name . '</a>
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

		// Allow the user to create a new agenda
		$input_create_agenda = array(
			'type' => 'submit',
			'name' => 'create_agenda',
			'value' => $this->_( 'Create a new agenda' ),
			'css_class' => 'button-primary',
		);
		
		$returnValue .= '
			<p>
				' . $form->make_input( $input_create_agenda ) . '
			</p>
		';

		$returnValue .= $form->stop();
		
		echo $returnValue;
	}
	
	/**
		Edit an agenda.
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
		);
		
		if ( isset( $_POST['update'] ) )
		{
			$result = $form->validate_post( $inputs, array_keys( $inputs ), $_POST );

			if ($result === true)
			{
				$agenda = $this->filters( 'sd_mt_get_agenda', $id );
				$agenda->id = $id;
				$agenda->data->name = $_POST['name'];
				
				$this->filters( 'sd_mt_update_agenda', $agenda );
				
				$this->message( $this->_('The agenda has been updated!') );
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}
		
		if ( isset( $_POST['create_submit'] ) )
		{
			$agenda = $this->filters( 'sd_mt_get_agenda', $id );

			$names = trim( $_POST['create_names'] );
			$names = str_replace( "\r", "", $names );
			$names = array_filter( explode( "\n", $names ) );
			foreach( $names as $name )
			{
				$name = trim( $name );
				if ( $name == "" )
					continue;
				
				$item = new SD_Meeting_Tool_Agenda_Item();
				$item->data->name = $name;
				$item->order = PHP_INT_MAX;
				$agenda->items[] = $item;
			}
			$this->filters( 'sd_mt_update_agenda', $agenda );
			if ( count($names) > 1 )
				$this->message( $this->_('New items have been created!') );
			else
				$this->message( $this->_('A new item has been created!') );
		}
		
		if ( isset( $_POST['mass_edit'] ) && isset( $_POST['items'] ) )
		{
			if ( $_POST['mass_edit'] == 'delete' )
			{
				$agenda = $this->filters( 'sd_mt_get_agenda', $id );
				foreach( $_POST['items'] as $item_id => $ignore )
				{
					if ( isset( $agenda->items[ $item_id ] ) )
					{
						$item = $agenda->items[ $item_id ];
						unset( $agenda->items[ $item_id ] );
						$this->message( $this->_( 'Agenda item <em>%s</em> deleted.', $item->data->name ) );
					}
				}
				$this->filters( 'sd_mt_update_agenda', $agenda );
			}	// delete
		}

		$agenda = $this->filters( 'sd_mt_get_agenda', $id );
		
		// Automatically create a new page with a shortcode. Note that we need $agenda beforehand.
		if ( isset( $_POST['create_shortcode_page'] ) )
		{
			$page = key( $_POST['create_shortcode_page'] );
			$post_content = '';
			switch ( $page )
			{
				case 'show_agenda':
					$post_title = $this->_( 'Agenda' );
					$post_content = '[show_agenda id="' . $agenda->id . '" autorefresh="yes"]';
					break;
				case 'current_agenda_item':
					$post_title = $this->_( 'Agenda item' );
					$post_content = '[current_agenda_item autorefresh="yes" id="' . $agenda->id . '" link="yes" number="no" text="yes"]';
					break;
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
		
		$inputs['name']['value'] = $agenda->data->name;
		
		$input_update = array(
			'type' => 'submit',
			'name' => 'update',
			'value' => $this->_( 'Update agenda' ),
			'css_class' => 'button-primary',
		);

		$returnValue .= '
			' . $form->start() . '
			
			<h3>' . $this->_('Agenda settings') . '</h3>
			
			' . $this->display_form_table( $inputs ).'

			<p>
				' . $form->make_input( $input_update ) . '
			</p>

			' . $form->stop() . '
		';
		
		if ( count( $agenda->items ) < 1 )
		{
			$items = $this->_( 'This agenda has no items.' );
		}
		else
		{
			$items = '';
			$t_body = '';

			foreach( $agenda->items as $item )
			{
				$input_select = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $item->id,
					'name' => $item->id,
					'nameprefix' => '[items]',
				);
				
				$item_edit_url = add_query_arg( array(
					'tab' => 'edit_item',
					'item' => $item->id,
				) );
				
				$tr_class = '';
				if ( $agenda->is_current_item_id( $item->id ) )
					$tr_class .= ' current_item';
				
				$t_body .= '<tr class="'. $tr_class.'">
					<th scope="row" class="check-column">' . $form->make_input($input_select) . ' <span class="screen-reader-text">' . $form->make_label($input_select) . '</span></th>
					<td><a href="' . $item_edit_url . '">' . $item->data->name . '</a></td>
				</tr>';
			}
			
			$mass_edit_select = array(
				'type' => 'select',
				'name' => 'mass_edit',
				'label' => $this->_('With the selected rows'),
				'options' => array(
					array( 'value' => '', 'text' => $this->_('Do nothing') ),
					array( 'value' => 'delete', 'text' => $this->_('Delete') ),
				),
			);
			$mass_edit_submit = array(
				'type' => 'submit',
				'name' => 'mass_edit_submit',
				'value' => $this->_('Apply'),
				'css_class' => 'button-secondary',
			);

			$selected = array(
				'type' => 'checkbox',
				'name' => 'check',
			);
			
			$items .= '
				<div>
					' . $form->make_label( $mass_edit_select ) . '
					' . $form->make_input( $mass_edit_select ) . '
					' . $form->make_input( $mass_edit_submit ) . '
				</div>
				<table class="widefat sd_mt_agendas">
					<thead>
						<tr>
							<th class="check-column">' . $form->make_input( $selected ) . '<span class="screen-reader-text">' . $this->_('Selected') . '</span></th>
							<th>' . $this->_('Name') . '</th>
						</tr>
					</thead>
					<tbody>
						'.$t_body.'
					</tbody>
				</table>
			';
			
		}
		
		$returnValue .= '
			
			<h3>' . $this->_('Items') . '</h3>
			
			<p>
				' . $this->_( "The rows can be manually sorted by dragging them up and down in the table." ) . ' ' . $this->_("You can select the current item by doubleclicking the item's row. Doubleclick the same item again to deselect it." ) . '
			</p>
			' . $form->start() . '
			
			' . $items . '
			
			' . $form->stop() . '
		';
		
			$random_id = md5( rand( 0, PHP_INT_MAX ) . rand( 0, PHP_INT_MAX ) );

			$returnValue .= '
				<script type="text/javascript" src="'. $this->paths["url"] . "/js/sd_meeting_tool_agendas.js" .'"></script>
				<script type="text/javascript">
					jQuery(document).ready(function($){
						var sd_mt_agenda_'. $random_id .' = new sd_meeting_tool_agendas();
						sd_mt_agenda_'. $random_id .'.init_admin(
						{
							"ajaxurl" : "'. admin_url("admin-ajax.php") . '",
							"ajaxnonce" : "' . wp_create_nonce( 'sd_mt_agendas_ajax_admin' ) . '",
							"action" : "sd_mt_agendas_ajax_admin", 
							"agenda_id" : "'. $agenda->id . '",
						},
						{}
						);
					});
				</script>
			';

		// Create new items
		$inputs_create = array(
			'names' => array(
				'type' => 'textarea',
				'name' => 'create_names',
				'label' => $this->_( 'Names of new items' ),
				'description' => $this->_( 'Each row of text becomes a new item.' ),
				'rows' => 5,
				'cols' => 40,
			),
			'submit' => array(
				'type' => 'submit',
				'name' => 'create_submit',
				'value' => $this->_('Create new item(s)'),
				'css_class' => 'button-primary',
			),
		);
		
		$returnValue .= '
			
			<h3>' . $this->_('Create new item(s)') . '</h3>

			' . $form->start() . '
			' . $this->display_form_table( $inputs_create ) . '
			' . $form->stop() . '
		';
		
		// Shortcodes
		
		$buttons = array(
			'show_agenda' => array(
				'name' => 'show_agenda',
				'type' => 'submit',
				'value' => $this->_( 'Create a page with this shortcode' ),
				'title' => $this->_( 'Creates a new page with this shortcode as the only content.' ),
				'nameprefix' => '[create_shortcode_page]',
				'css_class' => 'button-secondary',
			),
			'current_agenda_item' => array(
				'name' => 'current_agenda_item',
				'type' => 'submit',
				'value' => $this->_( 'Create a page with this shortcode' ),
				'title' => $this->_( 'Creates a new page with this shortcode as the only content.' ),
				'nameprefix' => '[create_shortcode_page]',
				'css_class' => 'button-secondary',
			),
		);
		
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
						<td>[show_agenda id="' . $agenda->id . '" autorefresh="yes"]</td>
						<td>
							<p>' . $this->_( 'Shows the current agenda as a list.' ). '</p>
							<p>
								' . $this->_( '<em>autorefresh</em>: Use javascript to automatically refresh the agenda.' ). '
								' . $this->_( '<em>id</em>: The ID of the agenda to be displayed.' ). '
								' . $this->_( '<em>link</em>: Enable links of those agenda items that have links.' ). '<br />
							</p>
						</td>
						<td>' . $form->make_input( $buttons['show_agenda'] ) . '</td>
					</tr>
					<tr>
						<td>[current_agenda_item id="' . $agenda->id . '" link="yes" number="no" text="yes"]</td>
						<td>
							<p>' . $this->_( 'Displays the current agenda item text and or link. If both text and link are selected, the text will be linked.' ). '</p>
							<p>
								' . $this->_( '<em>autorefresh</em>: Use javascript to automatically refresh the item.' ). '
								' . $this->_( '<em>id</em>: The ID of the agenda to use.' ). '
								' . $this->_( '<em>link</em>: Enable linking of the agenda item.' ). '<br />
								' . $this->_( '<em>number</em>: Display the number of the item before the text.' ). '<br />
								' . $this->_( '<em>text</em>: Display the agenda item text.' ). '<br />
							</p>
						</td>
						<td>' . $form->make_input( $buttons['current_agenda_item'] ) . '</td>
					</tr>
				<tbody>
			</table>

			' . $form->stop() . '
		';
		
		echo $returnValue;
	}

	public function admin_edit_item()
	{
		$form = $this->form();
		$id = $_GET['id'];
		$item_id = $_GET['item'];
		$returnValue = '';

		$inputs = array(
			'name' => array(
				'type' => 'text',
				'name' => 'name',
				'label' => $this->_( 'Name' ),
				'size' => 50,
				'maxlength' => 200,
			),
			'link' => array(
				'type' => 'text',
				'name' => 'link',
				'label' => $this->_( 'Link' ),
				'size' => 50,
				'maxlength' => 200,
				'validation' => array(
					'empty' => true,
				),
			),
		);
		
		if ( isset( $_POST['update'] ) )
		{
			$result = $form->validate_post( $inputs, array_keys( $inputs ), $_POST );

			if ($result === true)
			{
				$agenda = $this->filters( 'sd_mt_get_agenda', $id );
				$item = $agenda->items[ $item_id ];
				$item->data->name = $_POST['name'];
				$item->data->link = $_POST['link'];
				
				$this->filters( 'sd_mt_update_agenda_item', $item );
				
				$this->message( $this->_('The agenda item has been updated!') );
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}
		
		$agenda = $this->filters( 'sd_mt_get_agenda', $id );
		$item = $agenda->items[ $item_id ];

		$inputs['name']['value'] = $item->data->name;
		$inputs['link']['value'] = $item->data->link;
		
		$input_update = array(
			'type' => 'submit',
			'name' => 'update',
			'value' => $this->_( 'Update agenda item' ),
			'css_class' => 'button-primary',
		);

		$returnValue .= '
			' . $form->start() . '
			
			<h3>' . $this->_('Agenda item settings') . '</h3>
			
			' . $this->display_form_table( $inputs ).'

			<p>
				' . $form->make_input( $input_update ) . '
			</p>

			' . $form->stop() . '
			' . $this->make_back_link_to_agenda_editor() . '
		';
		
		echo $returnValue;
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Ajax
	// --------------------------------------------------------------------------------------------

	public function ajax_admin()
	{
		if ( ! SD_Meeting_Tool::check_admin_referrer( 'sd_mt_agendas_ajax_admin' ) )
			die();

		switch ( $_POST['type'] )
		{
			case 'agenda_items_reorder':
				$agenda = $this->filters( 'sd_mt_get_agenda', $_POST['agenda_id'] );
				if ( $agenda === false )
					break;

				$items = $_POST['order'];
		
				$order = 1;
				foreach ( $_POST['order'] as $item_id )
				{
					$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_agenda_items`
					SET
					`order` = '" . $order . "'
					WHERE `id` = '" . $item_id . "'";
					$this->query( $query );
					$order++;
				}
				echo json_encode( array( 'result' => 'ok' ) );

				$agenda = $this->filters( 'sd_mt_get_agenda', $_POST['agenda_id'] );
				$this->write_cache( $agenda );
				
				break;
			case 'select_current_item':
				$agenda = $this->filters( 'sd_mt_get_agenda', $_POST['agenda_id'] );
				if ( $agenda === false )
					break;
				$current_item_id = intval( $_POST['current_item_id'] );
				
				if ( $current_item_id != 0 )
					if ( ! isset( $agenda->items[ $current_item_id ] ) )
						break;
				$agenda->set_current_item_id( $current_item_id );
				$this->filters( 'sd_mt_update_agenda', $agenda );
				echo json_encode( array( 'result' => 'ok' ) );

				$this->write_cache( $agenda );

				break;
		}
		die();
	}
	
	public function ajax_user()
	{
		switch ( $_POST['type'] )
		{
			case 'get_agenda_item':
				$agenda_id = $_POST['agenda_id'];
				$agenda = $this->filters( 'sd_mt_get_agenda', $agenda_id );
				if ( $agenda === false )
					break;
					
		}
		die();
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------

	public function sd_mt_delete_agenda( $SD_Meeting_Tool_Agenda )
	{
		global $blog_id;
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_agendas`
			WHERE `id` = '" . $SD_Meeting_Tool_Agenda->id . "'
			AND `blog_id` = '$blog_id'";
		$this->query( $query );

		$this->delete_cache( $SD_Meeting_Tool_Agenda );

		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_agenda_items`
			WHERE `agenda_id` = '" . $SD_Meeting_Tool_Agenda->id . "'";
		$this->query( $query );
	}
	
	public function sd_mt_get_agenda( $id )
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_agendas` WHERE `id` = '$id' AND blog_id = '$blog_id'";
		$result = $this->query_single( $query );
		
		if ( $result === false )
			return false;

		$agenda = $this->agenda_sql_to_object( $result );
	
		// Get the items.
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_agenda_items` WHERE `agenda_id` = '$id' ORDER BY `order`, id";
		$items = $this->query( $query );
		foreach( $items as $item )
			$agenda->items[ $item['id'] ] = $this->agenda_item_sql_to_object( $item );
		
		return $agenda;
	}
	
	public function sd_mt_get_all_agendas()
	{
		global $blog_id;
		$query = "SELECT id FROM `".$this->wpdb->base_prefix."sd_mt_agendas` WHERE `blog_id` = '$blog_id'";
		$results = $this->query( $query );
		
		$returnValue = array();
		
		foreach( $results as $result )
			$returnValue[ $result['id'] ] = $this->filters( 'sd_mt_get_agenda', $result['id'] );

		return SD_Meeting_Tool::sort_data_array( $returnValue, 'name' );
	}
	
	public function sd_mt_update_agenda( $SD_Meeting_Tool_Agenda )
	{
		global $blog_id;

		$agenda = $SD_Meeting_Tool_Agenda;		// Convenience.
		$data = $SD_Meeting_Tool_Agenda->data;	// Convenience
		
		if ( $agenda->id === null )
		{
			$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_agendas`
				(`blog_id`, `data`)
				VALUES
				('". $blog_id ."', '" . $this->sql_encode( $data ) . "')
			";
			$agenda->id = $this->query_insert_id( $query );
		}
		else
		{
			$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_agendas`
				SET
				`data` = '" . $this->sql_encode( $agenda->data ). "'
				WHERE `id` = '" . $agenda->id . "'
				AND `blog_id` = '$blog_id'";
			$this->query( $query );
		}
		$this->write_cache( $agenda );
		
		$old_agenda = $this->filters( 'sd_mt_get_agenda', $agenda->id );
		foreach( $old_agenda->items as $item_id => $item )
		{
			if ( !isset( $agenda->items[ $item_id ] ) )
			{
				$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_agenda_items`
					WHERE
					`id` = '" . $item_id . "'
				";
				$this->query( $query );
			}
		}
		
		foreach( $agenda->items as $item )
		{
			$item->agenda_id = $agenda->id;
			$this->filters( 'sd_mt_update_agenda_item', $item );
		}
		
		return $agenda;
	}
	
	public function sd_mt_update_agenda_item( $SD_Meeting_Tool_Agenda_Item )
	{
		$item = $SD_Meeting_Tool_Agenda_Item;		// Convenience
		if ( $item->id === null )
		{
			$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_agenda_items`
				(
					`order`, `agenda_id`, `data`
				)
				VALUES
				(
					'". $item->order ."',
					'". $item->agenda_id ."',
					'". $this->sql_encode( $item->data ) ."'
				)
			";
			$item->id = $this->query_insert_id( $query );
		}
		else
		{
			$query = "UPDATE  `".$this->wpdb->base_prefix."sd_mt_agenda_items`
				SET
					`agenda_id` = '". $item->agenda_id ."',
					`order` = '". $item->order ."',
					`data` = '". $this->sql_encode( $item->data ) ."'
				WHERE
					`id` = '". $item->id ."'
			";
			$this->query( $query );
		}
		
		// Write the cache, but first we need to fetch the agenda.
		$agenda = $this->filters( 'sd_mt_get_agenda', $item->agenda_id );
		$this->write_cache( $agenda );
		
		return $item;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------

	/**
		@brief		Convert a row from SQL to an SD_Meeting_Tool_Agenda.
		
		@param		array		$sql		Row from the database as an array.
		@return		An SD_Meeting_Tool_Agenda object.
	**/ 
	private function agenda_sql_to_object( $sql )
	{
		$agenda = new SD_Meeting_Tool_Agenda();
		$agenda->id = $sql['id'];
		$agenda->data = (object) array_merge( (array)$agenda->data, (array)$this->sql_decode( $sql['data'] ) );
		return $agenda;
	}

	/**
		@brief		Convert a row from SQL to an SD_Meeting_Tool_Agenda_Item.
		
		@param		array		$sql		Row from the database as an array.
		@return		An SD_Meeting_Tool_Agenda_Item object.
	**/ 
	private function agenda_item_sql_to_object( $sql )
	{
		$item = new SD_Meeting_Tool_Agenda_Item();
		$item->id = $sql['id'];
		$item->agenda_id = $sql['agenda_id'];
		$item->order = $sql['order'];
		$item->data = $this->sql_decode( $sql['data'] );
		return $item;
	}
	
	/**
		@brief		Delete the cache for this agenda.
		@param		SD_Meeting_Tool_Agenda		$agenda		Agenda whose cache must be deleted.
	**/
	private function delete_cache( $agenda )
	{
		$files = glob( $this->cache_directory() . 'agendas_' . $agenda->id . '_*' );
		foreach( $files as $file )
			unlink( $file );
	}
	
	/**
		@brief		Makes a link back to the agenda editor.
		
		@return		Back link string.
	**/
	private function make_back_link_to_agenda_editor()
	{
		$url = remove_query_arg( array('item', 'tab') );
		$url = add_query_arg( array(
			'tab' => 'edit_agenda',
		), $url );
		
		return SD_Meeting_Tool::make_back_link( $this->_( 'Back to the agenda editor' ), $url );
	}
	
	/**
		@brief		Displays an agenda to the user.
		
		Options array:
		
		- agenda	The SD_Meeting_Tool_Agenda to display.
		
		@param		array		$options
	**/
	private function display_agenda( $options )
	{
		$returnValue = '';
		$agenda = $options[ 'agenda' ];		// Conv

		foreach( $agenda->items as $item )
		{
			$item_text = $item->data->name;
			$class = 'agenda_' . $agenda->id . '_' . $item->id;
			
			if ( $agenda->is_current_item_id( $item->id ) )
				$class .= ' current_item';
			
			if ( $item->data->link != '' )
				$item_text = '<a href="'. $item->data->link .'">' . $item_text . '</a>';
			
			$returnValue .= '<li agenda_item_id="'.$item->id.'" class="'.$class.'">' . $item_text . '</li>';
		}
		
		return $returnValue;
	}
	
	private function display_agenda_item( $options )
	{
		$returnValue = '';
		$agenda = $options[ 'agenda' ];		// Conv
		
		// Is there a current item selected?
		if ( $agenda->get_current_item_id() === false )
			return;
		
		$current_item_id = $agenda->get_current_item_id();
		if ( !isset( $agenda->items[ $current_item_id ] ) )
			return;
		$item = $agenda->items[ $current_item_id ];
		
		// Retrieve the number/order of the item in the agenda.
		$item_order = array_keys( $agenda->items );
		$item_order = array_flip( $item_order );
		$current_item_order = $item_order[ $current_item_id ] + 1;

		$returnValue = '';

		if ( $options[ 'text'] )
			$returnValue .= $item->data->name;

		if ( $options[ 'number' ] )
				$returnValue = $current_item_order . '. ' . $returnValue;

		if ( $options[ 'link' ] && $item->data->link != '' )
		{
			if ( $options[ 'text'] )
				$returnValue = '<a href="' . $item->data->link . '">' . $returnValue . '</a>';
			else
				$returnValue = $item->data->link;
		}
		
		return $returnValue;
	}
	
	/**
		@brief		Write the current agenda and current_agenda_item to the cache.

		@param		SD_Meeting_Tool_Agenda		$SD_MT_Agenda		Agenda data to write.
	**/
	private function write_cache( $SD_MT_Agenda )
	{
		// The agenda
		$file = $this->cache_directory() . $this->cache_file( 'agendas_' . $SD_MT_Agenda->id );
		$options = array( 'agenda' => $SD_MT_Agenda );
		$data = $this->display_agenda( $options );
		file_put_contents( $file, $data );
		
		// And the current agenda item
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
		@brief		Shows an agenda.
		
		The agenda is shown as an ordered list. Multiple agendas can be shown at the same time.
		
		@par		Attributes
		
		- autorefresh	Refresh the agenda automatically using javacsript. Default "no".
		- id			Agenda ID. Required.
		
		@param		array		$attr		Attributes array.
		@return		Agenda HTML string to display.
	**/
	public function shortcode_show_agenda( $attr )
	{
		if ( !isset( $attr['id'] ) )
			return;
		$id = $attr['id'];
		$autorefresh = isset( $attr['autorefresh'] ) && $attr['autorefresh'] == 'yes';
		
		$agenda = $this->filters( 'sd_mt_get_agenda', $id );
		if ( $agenda === false )
			return;
		
		$div_id = hash( 'sha512', rand(0, PHP_INT_MAX) );
		
		$returnValue = '<div class="sd_mt_agenda sd_mt_agenda_' . $div_id . '">';
		$returnValue .= '<ol class="agenda agenda_'.$id.'">';
		
		$options = array(
			'agenda' => $agenda,
		);
		$returnValue .= $this->display_agenda( $options );
		$returnValue .= '</ol>';
		$returnValue .= '</div>';
		
		if ( $autorefresh )
		{
			$random_id = md5( rand( 0, PHP_INT_MAX ) . rand( 0, PHP_INT_MAX ) );

			$returnValue .= '
				<script type="text/javascript" src="'. $this->paths["url"] . "/js/sd_meeting_tool_agendas.js" .'"></script>
				<script type="text/javascript">
					jQuery(document).ready(function($){
						var sd_mt_agenda_'. $random_id .' = new sd_meeting_tool_agendas();
						sd_mt_agenda_'. $random_id .'.init_agenda_autorefresh(
						{},
						{
							"div_id" : "' . $div_id . '",
							"urls" : {
								"agenda" : "' . $this->cache_url() . $this->cache_file( 'agendas_' . $agenda->id ) . '"
							}
						});
					});
				</script>
			';
		}
		return $returnValue;
	}
	
	/**
		@brief		Shows the current agenda item.
		
		@par		Attributes
		
		- autorefresh	Refresh the agenda item automatically using javacsript. Default "no".
		- id			Agenda ID. Required.
		- link			Show the agenda item link, or make the text linked. Default "no".
		- number		Put a number in front of the agenda item, according to its position in the agenda. Default "no".
		- text			Show the agenda item text. Default "no".
		
		@param		array		$attr		Attributes array.
		@return		Agenda item HTML string to display.
	**/
	public function shortcode_current_agenda_item( $attr )
	{
		if ( !isset( $attr['id'] ) )
			return;
		$id = $attr['id'];
		$autorefresh = isset( $attr['autorefresh'] ) && $attr['autorefresh'] == 'yes';
		$link = isset( $attr['link'] ) && $attr['link'] == 'yes';
		$text = isset( $attr['text'] ) && $attr['text'] == 'yes';
		$number = isset( $attr['number'] ) && $attr['number'] == 'yes';
		
		$agenda = $this->filters( 'sd_mt_get_agenda', $id );
		if ( $agenda === false )
			return;
		
		$display = $this->display_agenda_item( array(
			'agenda' => $agenda,
			'link' => $link,
			'text' => $text,
			'number' => $number,
		) );
		
		$attributes = intval( $link )
			. intval( $number )
			. intval( $text );
		$span_class = 'sd_mt_agenda_' . $agenda->id . '_current_item_' . $attributes;
		
		$returnValue = '<span class="sd_mt_agenda_current_item sd_mt_agenda_current_item_'.$agenda->id.'
			'.$span_class.'">' . $display . '</span>';

		if ( $autorefresh )
		{
			$random_id = md5( rand( 0, PHP_INT_MAX ) . rand( 0, PHP_INT_MAX ) );
			$url = $this->cache_url();
			$url .= $this->cache_file( 'agendas_' . $agenda->id . '_current_item_' . $attributes );

			$returnValue .= '
				<script type="text/javascript" src="'. $this->paths["url"] . "/js/sd_meeting_tool_agendas.js" .'"></script>
				<script type="text/javascript">
					jQuery(document).ready(function($){
						var sd_mt_agenda_'. $random_id .' = new sd_meeting_tool_agendas();
						sd_mt_agenda_'. $random_id .'.init_agenda_item_autorefresh(
						{
						},
						{
							"span_class" : "' . $span_class . '",
							"urls" : {
								"current_agenda_item" : "' . $url . '"
							}
						});
					});
				</script>
			';
		}
		return $returnValue;
	}
	
}
$SD_Meeting_Tool_Agendas = new SD_Meeting_Tool_Agendas();