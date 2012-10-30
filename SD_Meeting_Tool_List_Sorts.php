<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool List Sorts
Plugin URI: https://it.sverigedemokraterna.se
Description: Stores sort orders and sorts lists.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	@page		sd_meeting_tool_list_sorts					Plugin: List Sorts
	
	@section	sd_meeting_tool_list_sorts_description		Description
	
	Specifies how lists should be sorted.
	
	@section	sd_meeting_tool_list_sorts_requirements		Requirements
	
	- @ref index
	- @ref sd_meeting_tool_lists
	- @ref sd_meeting_tool_participants

	@section	sd_meeting_tool_list_sorts_installation		Installation
	
	Enable the plugin in Wordpress.
	
	The plugin will then create the following database tables:
	
	- @b prefix_sd_mt_list_sorts stores the various list sorts.
	
	@section	sd_meeting_tool_list_sorts_usage			Usage
	
	After creating a list sort the name can be specified. The name is used as an identifier and displayed to the admins.
	
	Underneath the submit button are the participant fields. The field names can be dragged up and down and the order
	can be switched between ascending / descending by double-clicking the "ascending" text itself.
	
	The order is saved automatically after dropping a field.
	
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
*/

/**	
	@brief		Plugin providing list sorting services for SD Meeting Tool.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se

	@par		Changelog

	@par		1.1
	- Version bump.
**/
class SD_Meeting_Tool_List_Sorts
	extends SD_Meeting_Tool_0
	implements interface_SD_Meeting_Tool_List_Sorts
{
	public function __construct()
	{
		parent::__construct( __FILE__ );

		// Actions
		add_filter( 'sd_mt_admin_menu',					array( &$this, 'sd_mt_admin_menu' ) );
		
		// Internal filters
		add_filter( 'sd_mt_delete_list_sort',			array( &$this, 'sd_mt_delete_list_sort' ) );
		add_filter( 'sd_mt_get_all_list_sorts',			array( &$this, 'sd_mt_get_all_list_sorts' ) );
		add_filter( 'sd_mt_get_list_sort',				array( &$this, 'sd_mt_get_list_sort' ) );
		add_filter( 'sd_mt_sort_list',					array( &$this, 'sd_mt_sort_list' ), 10, 2 );
		add_filter( 'sd_mt_update_list_sort',			array( &$this, 'sd_mt_update_list_sort' ) );

		// External filters
		add_action('wp_ajax_sd_mt_list_sorts_admin',	array( &$this, 'ajax_admin') );		
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_admin_menu( $menus )
	{
		$this->load_language();

		$menus[ $this->_('List sorts') ] = array(
			'sd_mt',
			$this->_('List sorts'),
			$this->_('List sorts'),
			'read',
			'sd_mt_list_sorts',
			array( &$this, 'admin' )
		);

		wp_enqueue_script( 'jquery-ui-sortable' );
		return $menus;
	}
	
	public function activate()
	{
		parent::activate();

		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_list_sorts` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `blog_id` int(11) NOT NULL COMMENT 'Blog ID',
		  `data` longtext NOT NULL COMMENT 'Serialized data stdclass containing ... data',
		  PRIMARY KEY (`id`),
		  KEY `blog_id` (`blog_id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=latin1 ;
		");
	}
	
	public function uninstall()
	{
		parent::uninstall();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_list_sorts`");
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
				$tab_data['tabs']['edit'] = $this->_( 'Edit list sort');
				$tab_data['functions']['edit'] = 'admin_edit';

				$list_sort = $this->filters( 'sd_mt_get_list_sort', $_GET['id'] );
				if ( $list_sort === false )
					wp_die( $this->_( 'Specified list sort does not exist!' ) );

				$tab_data['page_titles']['edit'] = $this->_( 'Editing list sort: %s', $list_sort->data->name );
			}
		}

		$this->tabs($tab_data);
	}
	
	public function admin_overview()
	{
		$form = $this->form();
		$returnValue = $form->start();
		$fields = $this->filters( 'sd_mt_get_participant_fields', array() );

		if ( isset( $_POST['action_submit'] ) && isset( $_POST['list_sorts'] ) )
		{
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['list_sorts'] as $list_sort_id => $ignore )
				{
					$list_sort = $this->filters( 'sd_mt_get_list_sort', $list_sort_id );
					if ( $list_sort !== false )
					{
						$this->filters( 'sd_mt_delete_list_sort', $list_sort );
						$this->message( $this->_( 'List sort <em>%s</em> deleted.', $list_sort->data->name ) );
					}
				}
			}	// delete
		}
		
		if ( isset( $_POST['create_list_sort'] ) )
		{
			$list_sort = new SD_Meeting_Tool_List_Sort();
			$list_sort->data->name = $this->_( 'List sort created %s', $this->now() );
			// Assign the default field order... which is basically all the fields.
			foreach( $fields as $field )
				$list_sort->data->orders[] = new SD_Meeting_Tool_List_Sort_Order( $field );
			$list_sort = $this->filters( 'sd_mt_update_list_sort', $list_sort );
			
			$edit_link = add_query_arg( array(
				'tab' => 'edit',
				'id' => $list_sort->id,
			) );
			
			$this->message( $this->_( 'List sort created! <a href="%s">Edit the newly-created list sort</a>.', $edit_link ) );
		}	// create_list_sort

		$list_sorts = $this->filters( 'sd_mt_get_all_list_sorts', null );

		if ( count( $list_sorts ) < 1 )
			$this->message( $this->_( 'There are no list sorts available.' ) );
		else
		{
			$t_body = '';
			foreach( $list_sorts as $list_sort )
			{
				if ( count( $fields ) != count( $list_sort->data->orders ) )
					$list_sort = $this->rebuild_orders( $list_sort );

				$input_select_list_sort = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $list_sort->data->name,
					'name' => $list_sort->id,
					'nameprefix' => '[list_sorts]',
				);
				
				$edit_link = add_query_arg( array(
					'tab' => 'edit',
					'id' => $list_sort->id,
				) );
				
				$info = array();

				$item_info = '<p class="orders">';
				
				// Add the orders as info.
				// While we're doing that, update them if new fields have been added or fields have been removed.
				foreach( $list_sort->data->orders as $index => $order )
				{
					if ( $index > 1 )
						break;
					$order_type = ( $order->ascending ? '' : ', ' . $this->_('descending') );
					$item_info .= '<div class="order">' . $order->field->description . $order_type . '</div>';
				}
				$item_info .= '</p>';
				$info[] = $item_info;

				$info = implode( '</div><div>', $info );

				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input( $input_select_list_sort ) . ' <span class="screen-reader-text">' . $form->make_label( $input_select_list_sort ) . '</span></th>
					<td>
						<div>
							<a
							title="' . $this->_('Edit this list sort') . '"
							href="'. $edit_link .'">' . $list_sort->data->name . '</a>
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

		// Allow the user to create a new list sort
		$input_create_list_sort = array(
			'type' => 'submit',
			'name' => 'create_list_sort',
			'value' => $this->_( 'Create a new list sort' ),
			'css_class' => 'button-primary',
		);
		
		$returnValue .= '
			<p>
				' . $form->make_input( $input_create_list_sort ) . '
			</p>
		';

		$returnValue .= $form->stop();
		
		echo $returnValue;
	}

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
				$list_sort = $this->filters( 'sd_mt_get_list_sort', $id );
				$list_sort->id = $id;
				$list_sort->data->name = $_POST['name'];
				
				$this->filters( 'sd_mt_update_list_sort', $list_sort );
				
				$this->message( $this->_('The list sort has been updated!') );
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}
		
		$list_sort = $this->filters( 'sd_mt_get_list_sort', $id );
		
		$inputs['name']['value'] = $list_sort->data->name;
		
		$input_update = array(
			'type' => 'submit',
			'name' => 'update',
			'value' => $this->_( 'Update list sort' ),
			'css_class' => 'button-primary',
		);

		$returnValue .= '
			' . $form->start() . '
			
			<h3>' . $this->_('List sort settings') . '</h3>
			
			' . $this->display_form_table( $inputs ).'

			<p>
				' . $form->make_input( $input_update ) . '
			</p>

			' . $form->stop() . '
		';
		
		$items = '';
		$t_body = '';

		$fields = $this->filters( 'sd_mt_get_participant_fields', array() );
		
		foreach( $list_sort->data->orders as $order )
		{
			$input_select = array(
				'type' => 'checkbox',
				'checked' => false,
				'label' => $order->field,
				'name' => $order->field,
				'nameprefix' => '[items]',
			);
			
			$ascending = ( $order->ascending ? $this->_('Ascending') : $this->_('Descending') );

			$t_body .= '<tr>
				<td class="name" name="' . $order->field->name . '"><span class="name">' . $order->field->description . '</span></td>
				<td class="ascending">' . $ascending . '</td>
			</tr>';
		}
		
		$items .= '
			<table class="widefat sd_mt_list_sort_orders">
				<thead>
					<tr>
						<th>' . $this->_('Field') . '</th>
						<th>' . $this->_('Order') . '</th>
					</tr>
				</thead>
				<tbody>
					'.$t_body.'
				</tbody>
			</table>
		';
		
		$returnValue .= '
			
			<p>
				' . $this->_( "The rows can be manually sorted by dragging them up and down in the table." ) . '
			</p>
			' . $form->start() . '
			
			' . $items . '
			
			' . $form->stop() . '
		';
		
		$returnValue .= "
			<script type='text/javascript' src='". $this->paths['url'] . '/js/sd_meeting_tool_list_sorts.js' ."'></script>
			<script type='text/javascript'>
				jQuery(document).ready(function($){
					sd_mt_list_sorts.init({
						action : 'sd_mt_list_sorts_admin', 
						ajaxurl : '". admin_url('admin-ajax.php') . "',
						ajaxnonce : '" . wp_create_nonce( 'ajax_sd_mt_list_sorts' ) . "',
						id : '". $list_sort->id . "'
					});
				});
			</script>
		";

		echo $returnValue;
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Ajax
	// --------------------------------------------------------------------------------------------

	/**
		Admin AJAX commands.
	**/
	public function ajax_admin()
	{
		if ( ! SD_Meeting_Tool::check_admin_referrer( 'ajax_sd_mt_list_sorts' ) )
			die();
		
		switch ( $_POST['type'] )
		{
			case 'switch_ascending':
				$list_sort = $this->filters( 'sd_mt_get_list_sort', $_POST['id'] );
				if ( $list_sort === false )
					return;
				
				// Parse our orders and when we find the order with this field name, switch the ascending!
				foreach( $list_sort->data->orders as $order )
				{
					if ( $order->field->name == $_POST['field'] )
					{
						$order->ascending = ! $order->ascending; 
						break;
					}
				}

				$this->filters( 'sd_mt_update_list_sort', $list_sort );
				break;
			case 'reorder_orders':
				$list_sort = $this->filters( 'sd_mt_get_list_sort', $_POST['id'] );
				if ( $list_sort === false )
					return;
				
				$correct_fields = array();
				
				foreach( $_POST['order'] as $field_name )
				{
					foreach( $list_sort->data->orders as $index => $order )
					{
						if ( $order->field->name == $field_name )
						{
							$correct_fields[] = $order;
							unset( $list_sort->data->orders[ $index ] );
						}
					}
				}
				
				$list_sort->data->orders = $correct_fields;

				$this->filters( 'sd_mt_update_list_sort', $list_sort );
				
				echo json_encode( array( 'ok' ) );
				break;
		}
		die();
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------

	public function sd_mt_delete_list_sort( $SD_Meeting_Tool_List_Sort )
	{
		global $blog_id;
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_list_sorts`
			WHERE `id` = '" . $SD_Meeting_Tool_List_Sort->id . "'
			AND `blog_id` = '$blog_id'";
		$this->query( $query );
	}
	
	public function sd_mt_get_all_list_sorts()
	{
		global $blog_id;
		$query = "SELECT id FROM `".$this->wpdb->base_prefix."sd_mt_list_sorts` WHERE `blog_id` = '$blog_id'";
		$results = $this->query( $query );
		
		$returnValue = array();
		
		foreach( $results as $result )
			$returnValue[ $result['id'] ] = $this->filters( 'sd_mt_get_list_sort', $result['id'] );

		return SD_Meeting_Tool::sort_data_array( $returnValue, 'name' );
	}
	
	public function sd_mt_get_list_sort( $id )
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_list_sorts` WHERE `id` = '$id' AND blog_id = '$blog_id'";
		$result = $this->query_single( $query );
		
		if ( $result !== false )
			return $this->list_sort_sql_to_object( $result );
		else
			return false;
	}
	
	public function sd_mt_sort_list( $SD_Meeting_Tool_List, $SD_Meeting_Tool_List_Sort )
	{
		if ( ( $SD_Meeting_Tool_List && $SD_Meeting_Tool_List_Sort )=== false )
			return $SD_Meeting_Tool_List;
		
		$sorter = new SD_Meeting_Tool_List_Sorter();
		return $sorter->sort( $SD_Meeting_Tool_List, $SD_Meeting_Tool_List_Sort );
	}
	
	public function sd_mt_update_list_sort( $SD_Meeting_Tool_List_Sort )
	{
		global $blog_id;

		$list_sort = $SD_Meeting_Tool_List_Sort;		// Convenience.
		$data = $this->sql_encode( $SD_Meeting_Tool_List_Sort->data );
		
		if ( $list_sort->id === null )
		{
			$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_list_sorts`
				(`blog_id`, `data`)
				VALUES
				('". $blog_id ."', '".$data."')
			";
			$list_sort->id = $this->query_insert_id( $query );
		}
		else
		{
			$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_list_sorts`
				SET
				`data` = '" . $data . "'
				WHERE `id` = '" . $list_sort->id . "'";
			$this->query( $query );
		}

		return $list_sort;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------

	/**
		Convert a row from SQL to an SD_Meeting_Tool_List_Sort.
		
		@param		$sql						Row from the database as an array.
		@return		SD_Meeting_Tool_List_Sort
	**/ 
	private function list_sort_sql_to_object( $sql )
	{
		$list_sort = new SD_Meeting_Tool_List_Sort();
		$list_sort->id = $sql['id'];
		$list_sort->data = (object) array_merge( (array)$list_sort->data, (array)$this->sql_decode( $sql['data'] ) );
		return $list_sort;
	}
	
	/**
		Rebuilds the order fields of a SD_Meeting_Tool_List_Sort.
		
		@param		$SD_Meeting_Tool_List_Sort		List sort to rebuild.
		@return		Updated SD_Meeting_Tool_List_Sort.
	**/
	private function rebuild_orders( $SD_Meeting_Tool_List_Sort )
	{
		$fields = $this->filters( 'sd_mt_get_participant_fields', array() );
		
		foreach( $SD_Meeting_Tool_List_Sort->data->orders as $index => $order )
		{
			$seen = false;
			foreach( $fields as $field_index => $field )
			{
				if ( $order->field->name == $field->name )
				{
					unset( $fields[ $field_index ] );
					$seen = true;
				}
			}
			if ( ! $seen )
				unset( $SD_Meeting_Tool_List_Sort->data->orders[ $index ] );
		}
		
		// Add all new fields to the orders.
		foreach( $fields as $field )
		{
			$order = new SD_Meeting_Tool_List_Sort_Order( $field );
			$SD_Meeting_Tool_List_Sort->data->orders[] = $order;
		}
		
		$this->filters( 'sd_mt_update_list_sort', $SD_Meeting_Tool_List_Sort );
		return $SD_Meeting_Tool_List_Sort;
	}
}
$SD_Meeting_Tool_List_Sorts = new SD_Meeting_Tool_List_Sorts();

// --------------------------------------
//		class SD_Meeting_Tool_List_Sorter
// --------------------------------------

/**
	Will sort a SD_Meeting_Tool_List according to the given SD_Meeting_Tool_List_Sort_Order.
	
	Used internally by SD_Meeting_Tool_List_Sorts.
	
	@brief		List sorting class.
	@see		SD_Meeting_Tool_List_Sorts
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_List_Sorter
{
	private $participants;
	
	/**
		Sorts a list.
		@param	$SD_Meeting_Tool_List				List to sort.
		@param	$SD_Meeting_Tool_List_Sort_Order	How to sort the list.
		@return	A sorted $SD_Meeting_Tool_List.				
	**/
	public function sort( $SD_Meeting_Tool_List, $SD_Meeting_Tool_List_Sort_Order )
	{
		$this->participants = array();
		$participants = $SD_Meeting_Tool_List->participants;
		$sort_order = $SD_Meeting_Tool_List_Sort_Order->data->orders;
		$participants = $this->sort_array( $participants, $sort_order );
		
		array_walk_recursive( $participants, array( $this, 'append_to_participants' ) );
		
		$SD_Meeting_Tool_List->participants = $this->participants;
		return $SD_Meeting_Tool_List;
	}
	
	/**
		Adds the participant to the end of the participants array.
	**/
	private function append_to_participants( $participant )
	{
		// We're only interested in adding real people (SD_Meeting_Tool_Participant), not arrays.
		if ( !is_object( $participant ) )
			return;
		$this->participants[ $participant->id ] = $participant;
	}
	
	/**
		Recursive function that sorts an array and then sorts any subarrays, if necessary.
	**/
	private function sort_array( &$array, $sort_order )
	{
		if ( count( $sort_order ) < 1 )
			return;
		
		// Get the key for the current sort order.
		$field = reset( $sort_order );
		$field_name = $field->field->name;
		
		$first_user = reset( $array );
		// If the requested field doesn't exist then we cannot sort it further.
		if ( !isset( $first_user->$field_name ) )
			return $array;
		
		// Split the array into several smaller arrays.
		$sorted_array = array();
		foreach( $array as $key=>$value )
		{
			if ( !isset( $sorted_array[ $value->$field_name ] ) )
				$sorted_array[ $value->$field_name ] = array();
			$sorted_array[ $value->$field_name ][] = $value;
		}
		
		// And now sort them.
		ksort( $sorted_array );
		
		if ( ! $field->ascending )
			$sorted_array = array_reverse( $sorted_array);
		
		$array = $sorted_array;
		
		array_shift( $sort_order );
		
		if ( count( $sort_order ) > 0 )
			foreach( $array as $key => $unsorted_subgroup )
				if ( count($unsorted_subgroup) > 1 )	// Don't sort subgroups that only have one item.
					$array[ $key ] = $this->sort_array( $unsorted_subgroup, $sort_order );
		
		return $array;
	}
}
