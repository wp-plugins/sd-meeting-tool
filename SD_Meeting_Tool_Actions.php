<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool Actions
Plugin URI: https://it.sverigedemokraterna.se
Description: Provide actions for the modules.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	@page		sd_meeting_tool_actions					Plugin: Actions
	
	@section	sd_meeting_tool_actions_description		Description
	
	An action is a group of one or more action items (SD_Meeting_Tool_Action_Item).
	
	The plugin provides a framework for other plugins to create and store their own action items. The plugin
	also provides action trigger handling, triggering the items in the correct order when requested.
	
	Actions are triggered by other plugins. The registration plugin can, for example, trigger an action that contains
	action items that insert the participant into a list. 
	
	Action items are dynamically supplied by installed plugins. @ref sd_meeting_tool_lists, for example, provides action items
	for adding and removing participants to and from lists. 
	
	@section	sd_meeting_tool_actions_requirements		Requirements
	
	@ref index

	@section	sd_meeting_tool_actions_installation		Installation
	
	Enable the plugin in Wordpress.
	
	The plugin will then create the following database tables:
	
	- @b prefix_sd_mt_actions stores the actions (groups).\n
	- @b prefix_sd_mt_action_items stores the action item data.\n

	@section	sd_meeting_tool_actions_usage				Usage
	
	Decide first what action is necessary. Take, for example, checking somebody in.
	
	That would mean that the registration plugin needs an action that:
	
	- Removes the participant from a list ("able to check in")
	- Inserts the participant into a list ("checked in")
	
	After the goal is clear, create the action.
	
	Then edit the action and create two action items of the correct type (see the drop-down list).
	
	After the two items are created, configure them. This will, in turn, call the list plugin's configure method.
	The returned configuration is stored by Actions.
	
	The action items can be reordered by dragging, in case the order is important.
	
	@see SD_Meeting_Tool_Action_Trigger
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/

/**
	Provides action handling.

	@par		Changelog

	@par		1.1
	- Version bump.
	
	@brief		Standard, basic and complete action handling plugin.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Actions
	extends SD_Meeting_Tool_0
	implements interface_SD_Meeting_Tool_Actions
{
	public function __construct()
	{
		parent::__construct( __FILE__ );

		// Internal filters
		add_filter( 'sd_mt_delete_action',					array( &$this, 'sd_mt_delete_action' ) );
		add_filter( 'sd_mt_get_action',						array( &$this, 'sd_mt_get_action' ) );
		add_filter( 'sd_mt_get_action_item',				array( &$this, 'sd_mt_get_action_item') );
		add_filter( 'sd_mt_get_all_actions',				array( &$this, 'sd_mt_get_all_actions') );
		add_filter( 'sd_mt_update_action',					array( &$this, 'sd_mt_update_action' ) );
		add_filter( 'sd_mt_update_action_item',				array( &$this, 'sd_mt_update_action_item' ) );
	
		// External actions we listen to
		add_filter( 'sd_mt_admin_menu',						array( &$this, 'sd_mt_admin_menu' ) );

		// Ajax
		add_action('wp_ajax_ajax_sd_mt_actions',			array( &$this, 'ajax_admin') );		

		add_action( 'sd_mt_trigger_action',					array( &$this, 'sd_mt_trigger_action' ) );
	}

	public function activate()
	{
		parent::activate();

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_actions` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `blog_id` int(11) NOT NULL COMMENT 'Blog ID',
		  `data` longtext NOT NULL COMMENT 'Serialized data',
		  PRIMARY KEY (`id`),
		  KEY `blog_id` (`blog_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
		");

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_action_items` (
		  `item_id` int(11) NOT NULL AUTO_INCREMENT,
		  `action_id` int(11) NOT NULL,
		  `item_type` varchar(50) NOT NULL COMMENT 'Type of item',
		  `item_order` smallint(6) NOT NULL DEFAULT '100',
		  `data` longtext NOT NULL COMMENT 'Serialized item data',
		  PRIMARY KEY (`item_id`),
		  KEY `action_id` (`action_id`,`item_order`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
		");
	}

	public function uninstall()
	{
		parent::uninstall();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_actions`");
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_action_items`");
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------

	public function sd_mt_admin_menu( $menus )
	{
		$this->load_language();
		
		$menus[ $this->_('Actions') ] = array(
			'sd_mt',
			$this->_('Actions'),
			$this->_('Actions'),
			'read',
			'sd_mt_actions',
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
			if ( $_GET['tab'] == 'edit_action' )
			{
				$tab_data['tabs']['edit_action'] = $this->_( 'Edit action');
				$tab_data['functions']['edit_action'] = 'admin_edit';

				$action = $this->filters( 'sd_mt_get_action', $_GET['id'] );
				if ( $action === false )
					wp_die( $this->_( 'Specified action does not exist!' ) );

				$tab_data['page_titles']['edit_action'] = $this->_( 'Editing action: %s', $action->data->name );
			}

			if ( $_GET['tab'] == 'edit_item' )
			{
				$tab_data['tabs']['edit_item'] = $this->_( 'Edit item');
				$tab_data['functions']['edit_item'] = 'admin_edit_item';

				$action = $this->filters( 'sd_mt_get_action', $_GET['id'] );
				if ( $action === false )
					wp_die( $this->_( 'Specified action does not exist!' ) );
				$item_id = $_GET['item'];
				if ( ! isset( $action->items[ $item_id ] ) )
					wp_die( $this->_( 'Specified action item does not exist!' ) );
				
				$description = $this->filters( 'sd_mt_get_action_item_description', $action->items[ $item_id ] );
				$description = preg_replace( '/<br \/>.*/', '', $description->description );
				$description = strip_tags( $description );
				$tab_data['page_titles']['edit_item'] = $this->_( 'Editing action item: %s (%s)', $description, $item_id );
			}
		}

		$this->tabs($tab_data);
	}
	
	public function admin_overview()
	{
		if ( isset( $_POST['action_submit'] ) && isset( $_POST['actions'] ) )
		{
			if ( $_POST['action'] == 'clone' )
			{
				foreach( $_POST['actions'] as $action_id => $ignore )
				{
					$action = $this->filters( 'sd_mt_get_action', $action_id );
					if ( $action !== false )
					{
						$action->id = null;
						$action->data->name = $this->_( 'Copy of %s', $action->data->name );
						
						// When we're creating, we can't create any items until we know the id of the new action.
						// So save the items for later.
						$old_items = $action->items;
						$action->items = array();
						$action = $this->filters( 'sd_mt_update_action', $action );
						
						// Action created, now clone the action items.
						foreach( $old_items as $item )
						{
							$item->id = null;
							$item->action_id = $action->id;
							$action->items[] = $item;
						}
						$action = $this->filters( 'sd_mt_update_action', $action );

						$edit_link = add_query_arg( array(
							'tab' => 'edit_action',
							'id' => $action->id,
						) );
						
						$this->message( $this->_( 'Action cloned! <a href="%s">Edit the newly-cloned action</a>.', $edit_link ) );
					}
				}
			}	// clone
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['actions'] as $action_id => $ignore )
				{
					$action = $this->filters( 'sd_mt_get_action', $action_id );
					if ( $action !== false )
					{
						$this->filters( 'sd_mt_delete_action', $action );
						$this->message( $this->_( 'Action <em>%s</em> deleted.', $action->data->name ) );
					}
				}
			}	// delete
		}
		
		if ( isset( $_POST['create_action'] ) )
		{
			$action = new SD_Meeting_Tool_Action();
			$action->data->name = $this->_( 'Action created %s', $this->now() );
			$action = $this->filters( 'sd_mt_update_action', $action );
			
			$edit_link = add_query_arg( array(
				'tab' => 'edit_action',
				'id' => $action->id,
			) );
			
			$this->message( $this->_( 'Action created! <a href="%s">Edit the newly-created action</a>.', $edit_link ) );
		}

		$form = $this->form();
		$returnValue = $form->start();

		$actions = $this->filters( 'sd_mt_get_all_actions', array() );
		
		if ( count( $actions ) < 1 )
			$this->message( $this->_( 'There are no actions available.' ) );
		else
		{
			$t_body = '';
			foreach( $actions as $action )
			{
				$input_action_select = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $action->data->name,
					'name' => $action->id,
					'nameprefix' => '[actions]',
				);
				
				$edit_link = add_query_arg( array(
					'tab' => 'edit_action',
					'id' => $action->id,
				) );
				
				// ACTION time.
				$row_actions = array();
				
				// Edit list action
				$row_actions[] = '<a href="'.$edit_link.'">'. $this->_('Edit') . '</a>';
				
				$row_actions = implode( '&emsp;<span class="sep">|</span>&emsp;', $row_actions );
				
				$info = array();
				foreach( $action->items as $item )
				{
					$item = $this->filters( 'sd_mt_get_action_item_description', $item );
					$info[] = '<p>' . $item->description . '</p>';
				}
				$info = implode( '</div><div>', $info );
				
				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input($input_action_select) . ' <span class="screen-reader-text">' . $form->make_label($input_action_select) . '</span></th>
					<td>
						<div>
							<a
							title="' . $this->_('Edit this action') . '"
							href="'. $edit_link .'">' . $action->data->name . '</a>
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

		// Allow the user to create a new list
		$input_list_create = array(
			'type' => 'submit',
			'name' => 'create_action',
			'value' => $this->_( 'Create a new action' ),
			'css_class' => 'button-primary',
		);
		
		$returnValue .= '
			<p>
				' . $form->make_input( $input_list_create ) . '
			</p>
		';

		$returnValue .= $form->stop();
		
		echo $returnValue;
	}

	/**
		@brief		Edit an action.
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
				$action = $this->filters( 'sd_mt_get_action', $id );
				$action->id = $id;
				$action->data->name = $_POST['name'];
				
				$this->filters( 'sd_mt_update_action', $action );
				
				$this->message( $this->_('The action has been updated!') );
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}
		
		if ( isset( $_POST['create_new_item'] ) && $_POST['new_item_type'] != '' )
		{
			$action = $this->filters( 'sd_mt_get_action', $id );
			$item = new SD_Meeting_Tool_Action_Item();
			$item->type = $_POST['new_item_type'];
			$action->items[] = $item; 
			$this->filters( 'sd_mt_update_action', $action );
			$this->message( $this->_('A new item has been created!') );
		}

		if ( isset( $_POST['mass_edit'] ) && isset( $_POST['items'] ) )
		{
			if ( $_POST['mass_edit'] == 'clone' )
			{
				$action = $this->filters( 'sd_mt_get_action', $id );
				foreach( $_POST['items'] as $item_id => $ignore )
				{
					if ( isset( $action->items[ $item_id ] ) )
					{
						$new_item = clone( $action->items[ $item_id ] );
						$new_item->id = null;
						$action->items[] = $new_item;
					}
				}
				$this->filters( 'sd_mt_update_action', $action );
			}	// clone

			if ( $_POST['mass_edit'] == 'delete' )
			{
				$action = $this->filters( 'sd_mt_get_action', $id );
				foreach( $_POST['items'] as $item_id => $ignore )
				{
					if ( isset( $action->items[ $item_id ] ) )
					{
						$item = $action->items[ $item_id ];
						unset( $action->items[ $item_id ] );
						$this->message( $this->_( 'Action item <em>%s</em> deleted.', $item->id ) );
					}
				}
				$this->filters( 'sd_mt_update_action', $action );
			}	// delete
		}

		$action = $this->filters( 'sd_mt_get_action', $id );

		$inputs['name']['value'] = $action->data->name;
		
		$input_update = array(
			'type' => 'submit',
			'name' => 'update',
			'value' => $this->_( 'Update action' ),
			'css_class' => 'button-primary',
		);


		$returnValue .= '
			' . $form->start() . '
			
			<h3>' . $this->_('Action settings') . '</h3>
			
			' . $this->display_form_table( $inputs ) .'

			<p>
				' . $form->make_input( $input_update ) . '
			</p>

			' . $form->stop() . '
		';
		
		if ( count( $action->items ) < 1 )
		{
			$items = $this->_( 'This action has no items.' );
		}
		else
		{
			$items = '';
			$t_body = '';

			foreach( $action->items as $item )
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
				
				$item = $this->filters( 'sd_mt_get_action_item_description', $item );

				$t_body .= '<tr action_item_id="' . $item->id . '">
					<th scope="row" class="check-column">' . $form->make_input($input_select) . ' <span class="screen-reader-text">' . $form->make_label($input_select) . '</span></th>
					<td><a href="' . $item_edit_url . '">' . $item->description . '</a></td>
				</tr>';
			}
			
			$mass_edit_select = array(
				'type' => 'select',
				'name' => 'mass_edit',
				'label' => $this->_('With the selected rows'),
				'options' => array(
					array( 'value' => '', 'text' => $this->_('Do nothing') ),
					array( 'value' => 'clone', 'text' => $this->_('Clone') ),
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
				<table class="widefat sd_mt_actions">
					<thead>
						<tr>
							<th class="check-column">' . $form->make_input( $selected ) . '<span class="screen-reader-text">' . $this->_('Selected') . '</span></th>
							<th>' . $this->_('Description') . '</th>
						</tr>
					</thead>
					<tbody>
						'.$t_body.'
					</tbody>
				</table>
			';
			
		}
		
		// To create a new item, we need to know of all the types.
		$action_item_types = $this->filters( 'sd_mt_get_action_item_types', array() );
		foreach( $action_item_types as $index => $type )
			$action_item_types[ $index ] = $this->filters( 'sd_mt_get_action_item_description', $type );
		$new_item_type_options = array();
		foreach( $action_item_types as $type)
			$new_item_type_options[ $type->type ] = $type->description;
		asort( $new_item_type_options );
		$new_item_type_options = array_merge(
			array( '' => $this->_( 'Select what the new item should do' ) ),
			$new_item_type_options
		);
		
		$inputs_create_item = array(
			'new_item_type' => array(
				'type' => 'select',
				'name' => 'new_item_type',
				'label' => $this->_('New item type'),
				'options' => $new_item_type_options,
			),
			'create_new_item' => array(
				'type' => 'submit',
				'name' => 'create_new_item',
				'value' => $this->_('Create a new item'),
				'css_class' => 'button-secondary',
			),
		);
		
		$items .= $this->display_form_table( $inputs_create_item );
		
		$returnValue .= '
			
			<h3>' . $this->_('Items') . '</h3>
			
			<p>
				' . $this->_('The rows can be manually sorted by dragging them up and down in the table.') . '
			</p>
			
			' . $form->start() . '
			
			' . $items . '
			
			' . $form->stop() . '
		';
		
		$returnValue .= '
			<script type="text/javascript" src="'. $this->paths["url"] . "/js/sd_meeting_tool_actions.js" .'"></script>
			<script type="text/javascript">
				jQuery(document).ready(function($){ sd_mt_actions.init_admin({
					"ajaxurl" : "'. admin_url("admin-ajax.php") . '",
					"ajaxnonce" : "' . wp_create_nonce( 'ajax_sd_mt_actions' ) . '",
					"action" : "ajax_sd_mt_actions", 
					"action_id" : "'. $action->id . '",
				}); });
			</script>
		';
		
		wp_enqueue_script( 'jquery-ui-sortable' );

		echo $returnValue;
	}

	/**
		@brief		Edit an action item.
	**/
	public function admin_edit_item()
	{
		$action = $this->filters( 'sd_mt_get_action', $_GET['id'] );
		
		// Ask the modules to help us configure this item.
		do_action( 'sd_mt_configure_action_item', $action->items[ $_GET['item'] ] );
		
		echo $this->make_back_link_to_action_editor();
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Actions
	// --------------------------------------------------------------------------------------------
	
	/**
		@brief		Triggers an action.
		
		Given an SD_Meeting_Tool_Action_Trigger it will go through the action's items and call each item action.
		
		The Action Trigger must contain the action and the trigger (mostly a SD_Meeting_Tool_Participant).
		
		@brief		Triggers an action.
		@param		SD_Meeting_Tool_Action_Trigger		$action_trigger		Action trigger to use.
	**/
	public function sd_mt_trigger_action( $action_trigger )
	{
		if ( ! is_a( $action_trigger->action, 'SD_Meeting_Tool_Action' ) )
			return;
		
		if ( ! is_object( $action_trigger->trigger ) )
			return;
		
		foreach( $action_trigger->action->items as $item )
		{
			$action_item_trigger = new SD_Meeting_Tool_Action_Item_Trigger();
			$action_item_trigger->action_item = $item;
			$action_item_trigger->trigger = $action_trigger->trigger;
			do_action( 'sd_mt_trigger_action_item', $action_item_trigger );
		}
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Ajax
	// --------------------------------------------------------------------------------------------

	public function ajax_admin()
	{
		if ( ! SD_Meeting_Tool::check_admin_referrer( 'ajax_sd_mt_actions' ) )
			die();
		
		switch ( $_POST['type'] )
		{
			case 'action_items_reorder':
				$items = $_POST['order'];
				$order = 100;
				foreach ( $_POST['order'] as $item_id )
				{
					$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_action_items`
					SET
					`item_order` = '" . $order . "'
					WHERE `item_id` = '" . $item_id . "'";
					$this->query( $query );
					$order++;
				}
				
				echo json_encode( array('ok') );
				break;
		}
		die();
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_delete_action( $action )
	{
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_actions`
			WHERE `id` = '" . $action->id . "'";
		$this->query( $query );

		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_action_items`
			WHERE `action_id` = '" . $action->id . "'";
		$this->query( $query );
	}
	
	public function sd_mt_get_action( $action_id )
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_actions` WHERE `id` = '$action_id' AND `blog_id` = '$blog_id'";
		$result = $this->query_single( $query );
		if ( $result !== false )
			$action = $this->action_sql_to_object( $result );
		else
			return false;
		
		// Get the items.
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_action_items` WHERE `action_id` = '$action_id' ORDER BY item_order";
		$items = $this->query( $query );
		foreach( $items as $item )
			$action->items[ $item['item_id'] ] = $this->action_item_sql_to_object( $item );
		
		return $action;
	}
	
	public function sd_mt_get_all_actions()
	{
		global $blog_id;
		$query = "SELECT id FROM `".$this->wpdb->base_prefix."sd_mt_actions` WHERE `blog_id` = '$blog_id'";
		$results = $this->query( $query );
		
		$returnValue = array();
		
		foreach( $results as $result )
			$returnValue[ $result['id'] ] = $this->filters( 'sd_mt_get_action', $result['id'] );

		return SD_Meeting_Tool::sort_data_array( $returnValue, 'name' );
	}
	
	public function sd_mt_get_action_item( $item_id )
	{
		// Get the items.
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_action_items` WHERE `item_id` = '$item_id'";
		$item = $this->query_single( $query );
		
		if ( $item !== false )
			$item = $this->action_item_sql_to_object( $item );
		
		return $item;
	}
	
	public function sd_mt_update_action( $SD_Meeting_Tool_Action )
	{
		global $blog_id;

		$action = $SD_Meeting_Tool_Action;		// Convenience.
		$data = $this->sql_encode( $action->data );
		
		if ( $action->id === null )
		{
			$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_actions`
				(`blog_id`, `data`)
				VALUES
				('". $blog_id ."', '". $data ."')
			";
			$action->id = $this->query_insert_id( $query );
		}
		else
		{
			$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_actions`
				SET
				`data` = '" . $data . "'
				WHERE `id` = '" . $action->id . "'";
			$this->query( $query );
		}
		
		$old_action = $this->filters( 'sd_mt_get_action', $action->id );
		foreach( $old_action->items as $item_id => $item )
		{
			if ( !isset( $action->items[ $item_id ] ) )
			{
				$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_action_items`
					WHERE
					`item_id` = '" . $item_id . "'
				";
				$this->query( $query );
			}
		}
		
		foreach( $action->items as $item )
		{
			$item->action_id = $action->id;
			$this->filters( 'sd_mt_update_action_item', $item );
		}
		
		return $action;
	}
	
	public function sd_mt_update_action_item( $item )
	{
		if ( $item->id === null )
		{
			$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_action_items`
				(
					`action_id`, `item_type`, `data`
				)
				VALUES
				(
					'". $item->action_id ."',
					'". $item->type ."',
					'". $this->sql_encode($item->data) ."'
				)
			";
			$item->id = $this->query_insert_id( $query );
		}
		else
		{
			$query = "UPDATE  `".$this->wpdb->base_prefix."sd_mt_action_items`
				SET
					`action_id` = '". $item->action_id ."',
					`item_order` = '". $item->order ."',
					`item_type` = '". $item->type ."',
					`data` = '". $this->sql_encode( $item->data ) ."'
				WHERE
					`item_id` = '". $item->id ."'
			";
			$this->query( $query );
		}
		return $item;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------
	
	/**
		@brief		Convert a row from SQL to an SD_Meeting_Tool_Action.
		
		@param		array		$sql		Row from the database as an array.
		@return		An SD_Meeting_Tool_Action object.
	**/ 
	private function action_sql_to_object( $sql )
	{
		$action = new SD_Meeting_Tool_Action();
		$action->id = $sql['id'];
		$action->data = (object) array_merge( (array)$action->data, (array)$this->sql_decode( $sql['data'] ) );
		return $action;
	}

	/**
		Convert a row from SQL to an SD_Meeting_Tool_Action_Item.
		
		@param		array		$sql		Row from the database as an array.
		@return		An SD_Meeting_Tool_Action_Item object.
	**/ 
	private function action_item_sql_to_object( $sql )
	{
		$item = new SD_Meeting_Tool_Action_Item();
		$item->id = $sql['item_id'];
		$item->action_id = $sql['action_id'];
		$item->type = $sql['item_type'];
		$item->order = $sql['item_order'];
		$item->data = (object) array_merge( (array)$item->data, (array)$this->sql_decode( $sql['data'] ) );
		return $item;
	}

	/**
		@brief		Makes a link back to the action editor.
		
		@return		Back link string.
	**/
	private function make_back_link_to_action_editor()
	{
		$url = remove_query_arg( array('item', 'tab') );
		$url = add_query_arg( array(
			'tab' => 'edit_action',
		), $url );
		
		return SD_Meeting_Tool::make_back_link( $this->_( 'Back to the action editor' ), $url );
	}
}
$sd_mt_actions = new SD_Meeting_Tool_Actions();