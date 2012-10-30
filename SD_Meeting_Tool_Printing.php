<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool Printing
Plugin URI: https://it.sverigedemokraterna.se
Description: Prints participants.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	@page		sd_meeting_tool_printing				Plugin: Printing
	
	@section	sd_meeting_tool_printing_description	Description
	
	"Prints" participants to PDFs. Can print onto existing PDF files. Uses templates and has wizards to automatically generate
	various templates.
	
	@section	sd_meeting_tool_printing_requirements	Requirements
	
	- @ref index
	
	@section	sd_meeting_tool_printing_installation	Installation
	
	Enable the plugin in Wordpress.
	
	The plugin will then create the following database tables:
	
	- @b prefix_sd_mt_printing_templates
		Templates.
	
	@section	sd_meeting_tool_printing_usage			Usage
	
	A printing template must contain at least one block, a rectangular piece of the page. Each block is equal to one participant.
	If a template has 10 blocks then 10 participants will be printed per page.
	
	Within each block several fields can exist. The fields are participant fields and have several display options:
	
	- Page to print on
	- X offset of the field inside the block
	- Y offset
	- Width (to help in centering the text)
	- Which of the participant's fields to actually use as the text source
	- Font and font size
	
	Printing works as follows:
	
	- A block is selected, together with the next unprinted participant.
	- If there are no more blocks, create a new page and go to step 1.
	- Fields are printed in page order: all fields on page 1 are printed first.
	- Fields are printing offsetted within their blocks.
	- If a field is to be printed on another page, a new page will be created for the field.
	- If available the page from the template is imported.
	- Repeat.
	
	To more easily understand how blocks and fields work, create a template using a wizard.
	
	To actually print a list of participants, click on a template, select which list you want printed and @sdmt will then generate
	a PDF that you can print or save to disk for future use.
	
	@subsection	sd_meeting_tool_printing_usage_templates	Templates
	
	Existing PDF files can be used as templates (backgrounds). Templates must first be uploaded to the media catalog, after which
	they can be selected for use in a printing template.
	
	The setting under the PDF selection dropdown, print all pages, sets whether the whole PDF template (background) should be
	printed for each participant or just those pages that are used in the fields.
	
	For example: A PDF template has 10 pages. You have a block that has two fields, one on page 1 and the other field on page 2.
	If <em>print all pages</em> is selected, the first field will be printed on page 1 and the second on page 2. After that there
	will be eight "empty" pages (taken directly from the template) and then the next participant will be printed.
	
	@subsection	sd_meeting_tool_printing_usage_wizards		Wizards
	
	Templates can be automatically generated using the wizards function. Most of the wizard templates available are generally
	self-explanatory. The name badge template generates name badges that fit the plastic neck badge holders that the Sweden Democrats
	usually use at their meetings.
	
	After creating a template using the wizard, edit the template and select which fields to print. The default is to print the
	first participant field.
	
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
*/

/**
	@brief		Plugin providing participant printing for SD Meeting Tool.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
	
	@par		Changelog
	
	@par		1.1
	
	- New: Wizard, 24 stickers
	- New: Page size and orientation can be chosen
	- New: Block and field clone functions
	
**/
class SD_Meeting_Tool_Printing
	extends SD_Meeting_Tool_0
{
	public function __construct()
	{
		parent::__construct( __FILE__ );

		// Internal filters
		add_filter( 'sd_mt_delete_printing_template',			array( &$this, 'sd_mt_delete_printing_template' ) );
		add_filter( 'sd_mt_get_all_printing_templates',			array( &$this, 'sd_mt_get_all_printing_templates') );
		add_filter( 'sd_mt_get_printing_block_field_fonts', 	array( &$this, 'sd_mt_get_printing_block_field_fonts' ) );
		add_filter( 'sd_mt_get_printing_template',				array( &$this, 'sd_mt_get_printing_template' ) );
		add_filter( 'sd_mt_update_printing_template',			array( &$this, 'sd_mt_update_printing_template' ) );
		
		// External actions
		add_filter( 'sd_mt_admin_menu',							array( &$this, 'sd_mt_admin_menu' ) );
	}

	public function activate()
	{
		parent::activate();
		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_printing_templates` (
		  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID of template',
		  `blog_id` int(11) NOT NULL COMMENT 'Blog ID this template belongs to',
		  `data` longtext NOT NULL COMMENT 'Serialized template data',
		  PRIMARY KEY (`id`),
		  KEY `blog_id` (`blog_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1;
		");
	}
	
	public function uninstall()
	{
		parent::uninstall();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_printing_templates`");
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_admin_menu( $menus )
	{
		$this->load_language();

		$menus[ $this->_('Printing') ] = array(
			'sd_mt',
			$this->_('Printing'),
			$this->_('Printing'),
			'read',
			'sd_mt_printing',
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

		$tab_data['tabs']['overview'] = $this->_( 'Overview' );
		$tab_data['functions']['overview'] = 'admin_overview';

		if ( isset( $_GET['tab'] ) )
		{
			if ( $_GET['tab'] == 'edit' )
			{
				$tab_data['tabs']['edit'] = $this->_( 'Edit');
				$tab_data['functions']['edit'] = 'admin_edit';

				$printing_template = $this->filters( 'sd_mt_get_printing_template', $_GET['id'] );
				if ( $printing_template === false )
					wp_die( $this->_( 'Specified printing template does not exist!' ) );

				$tab_data['page_titles']['edit'] = $this->_( 'Editing printing template: %s', $printing_template->data->name );
			}

			if ( $_GET['tab'] == 'edit_field' )
			{
				$tab_data['tabs']['edit_field'] = $this->_( 'Edit field');
				$tab_data['functions']['edit_field'] = 'admin_edit_field';

				$printing_template = $this->filters( 'sd_mt_get_printing_template', $_GET['id'] );
				if ( $printing_template === false )
					wp_die( $this->_( 'Specified printing template does not exist!' ) );
				
				$field_id = $_GET['field_id'];
				$fields = $printing_template->fields();
				// Does the field itself exist?
				if ( ! isset( $fields[ $field_id ] ) )
					wp_die( $this->_( 'Specified block field does not exist!' ) );
				
				$field = $fields[ $field_id ];
					
				// We need the participant field's description.
				$participant_field = $this->filters( 'sd_mt_get_participant_field', $field->participant_field );
				$field_name = ( $participant_field ? $participant_field->description : $this->_( 'Unknown' ) );
				
				$tab_data['page_titles']['edit_field'] = $this->_( 'Editing block field: %s', $field_name );
			}

			if ( $_GET['tab'] == 'print' )
			{
				$tab_data['tabs']['print'] = $this->_( 'Print' );
				$tab_data['functions']['print'] = 'admin_print';

				$printing_template = $this->filters( 'sd_mt_get_printing_template', $_GET['id'] );
				if ( $printing_template === false )
					wp_die( $this->_( 'Specified printing template does not exist!' ) );

				$tab_data['page_titles']['print'] = $this->_( 'Printing using template: %s', $printing_template->data->name );
			}
		}

		$tab_data['tabs']['wizards'] = $this->_( 'Wizards' );
		$tab_data['functions']['wizards'] = 'admin_wizards';

		$this->tabs($tab_data);
	}
	
	public function admin_overview()
	{
		if ( isset( $_POST['action_submit'] ) && isset( $_POST['printing_templates'] ) )
		{
			if ( $_POST['action'] == 'clone' )
			{
				foreach( $_POST['printing_templates'] as $printing_template_id => $ignore )
				{
					$printing_template = $this->filters( 'sd_mt_get_printing_template', $printing_template_id );
					if ( $printing_template !== false )
					{
						$printing_template->data->name = $this->_( 'Copy of %s', $printing_template->data->name );
						$printing_template->id = null;
						$printing_template = $this->filters( 'sd_mt_update_printing_template', $printing_template );

						$edit_link = add_query_arg( array(
							'tab' => 'edit',
							'id' => $id,
						) );
						
						$this->message( $this->_( 'Printing template cloned! <a href="%s">Edit the newly-cloned printing template</a>.', $edit_link ) );
					}
				}
			}	// clone
			
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['printing_templates'] as $printing_template_id => $ignore )
				{
					$printing_template = $this->filters( 'sd_mt_get_printing_template', $printing_template_id );
					if ( $printing_template !== false )
					{
						$this->filters( 'sd_mt_delete_printing_template', $printing_template );
						$this->message( $this->_( 'Template <em>%s</em> deleted.', $printing_template_id ) );
					}
				}
			}	// delete
		}
		
		if ( isset( $_POST['create_printing_template'] ) )
		{
			$printing_template = new SD_Meeting_Tool_Printing_Template();
			$printing_template->data->name = $this->_( 'Printing template created %s', $this->now() );
			$printing_template = $this->filters( 'sd_mt_update_printing_template', $printing_template );
			
			$edit_link = add_query_arg( array(
				'tab' => 'edit',
				'id' => $printing_template->id,
			) );
			
			$this->message( $this->_( 'Printing template created! <a href="%s">Edit the newly-created printing template</a>.', $edit_link ) );
		}	// create printing template

		$form = $this->form();
		$returnValue = $form->start();
		
		$printing_templates = $this->filters( 'sd_mt_get_all_printing_templates', null );
		
		if ( count( $printing_templates ) < 1 )
		{
			$this->message( $this->_( 'No printing templates found.' ) );
		}
		else
		{
			$t_body = '';
			foreach( $printing_templates as $printing_template )
			{
				$input_printing_template_select = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $printing_template->id,
					'name' => $printing_template->id,
					'nameprefix' => '[printing_templates]',
				);
								
				// ACTION time.
				$actions = array();
				
				// Print display template
				$print_action_url = add_query_arg( array(
					'tab' => 'print',
					'id' => $printing_template->id,
				) );
				$actions[] = '<a title="'. $this->_('Print a list using this template') .'" href="'.$print_action_url.'">'. $this->_('Print a list using this template') . '</a>';
				
				// Edit display template action
				$edit_link = add_query_arg( array(
					'tab' => 'edit',
					'id' => $printing_template->id,
				) );
				$actions[] = '<a href="'.$edit_link.'">'. $this->_('Edit') . '</a>';
				
				$actions = implode( '&emsp;<span class="sep">|</span>&emsp;', $actions );
				
				// INFO time.
				$info = array();
				
				switch( $printing_template->data->orientation )
				{
					case 'L':
						$paper_orientation = $this->_('Landscape');
						break;
					default:
						$paper_orientation = $this->_('Portrait');
						break;
				}
				
				$info[] = $this->_( 'Paper: %s  %s', $printing_template->data->size, $paper_orientation );
				
				$info[] = $this->_( 'Participant blocks: %s', count( $printing_template->data->blocks ) );
				
				$info[] = $this->_( 'Block fields: %s', count( $printing_template->data->fields ) );
				
				$info = implode( '</div><div>', $info );
				
				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input($input_printing_template_select) . ' <span class="screen-reader-text">' . $form->make_label($input_printing_template_select) . '</span></th>
					<td>
						<div>
							<a
							title="' . $this->_('Print a list using this template') . '"
							href="'. $print_action_url .'">' . $printing_template->data->name . '</a>
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
		$input_printing_template_create = array(
			'type' => 'submit',
			'name' => 'create_printing_template',
			'value' => $this->_( 'Create a new printing template' ),
			'css_class' => 'button-primary',
		);
		
		$returnValue .= '<p>' . $form->make_input( $input_printing_template_create ) . '</p>';

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
			'pdf' => array(
				'type' => 'select',
				'name' => 'pdf',
				'label' => $this->_( 'PDF template' ),
				'options' => array( '' => $this->_( 'Do not use a PDF template' ) ) + $this->get_pdf_attachments(),
			),
			'size' => array(
				'name' => 'size',
				'type' => 'text',
				'size' => 4,
				'maxlength' => 10,
				'label' => $this->_( 'Size' ),
				'description' => $this->_( 'Size of page. Default is A4' ),
			),
			'orientation' => array(
				'name' => 'orientation',
				'type' => 'select',
				'label' => $this->_( 'Orientation' ),
				'description' => $this->_( 'Page orientation' ),
				'options' => array(
					'P' => $this->_( 'Portrait' ),
					'L' => $this->_( 'Landscape' ),
				),
			),
			'print_all_pages' => array(
				'name' => 'print_all_pages',
				'type' => 'checkbox',
				'label' => $this->_( 'Print all pages' ),
				'description' => $this->_( 'When using a template the rest of the templates pages (after the first) will automatically be inserted after each participant block. If unselected, only the first page of the template will be used.' ),
			),
			'print_all_pages' => array(
				'name' => 'print_all_pages',
				'type' => 'checkbox',
				'label' => $this->_( 'Print all pages' ),
				'description' => $this->_( 'When using a template the rest of the templates pages (after the first) will automatically be inserted after each participant block. If unselected, only the first page of the template will be used.' ),
			),
			'print_even_pages' => array(
				'name' => 'print_even_pages',
				'type' => 'checkbox',
				'label' => $this->_( 'Print even amount of pages' ),
				'description' => $this->_( 'When using a template, insert an empty page at the end of odd-paged templates to ensure that duplex printing works as expected.' ),
			),
			'update' => array(
				'type' => 'submit',
				'name' => 'update',
				'value' => $this->_( 'Update printing template' ),
				'css_class' => 'button-primary',
			),
		);
		
		if ( isset( $_POST['update'] ) )
		{
			$result = $form->validate_post( $inputs, array_keys( $inputs ), $_POST );

			if ($result === true)
			{
				$printing_template = $this->filters( 'sd_mt_get_printing_template', $id );
				$printing_template->data->name = $_POST['name'];
				$printing_template->data->pdf = $_POST['pdf'];
				$printing_template->data->size = $_POST['size'];
				$printing_template->data->orientation = $_POST['orientation'];
				$printing_template->data->print_all_pages = isset( $_POST['print_all_pages'] );
				$printing_template->data->print_even_pages = isset( $_POST['print_even_pages'] );
				
				$this->filters( 'sd_mt_update_printing_template', $printing_template );
				
				$this->message( $this->_('The list has been updated!') );
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}

		$printing_template = $this->filters( 'sd_mt_get_printing_template', $id );
		
		$inputs['name']['value'] = $printing_template->data->name;
		$inputs['pdf']['value'] = intval( $printing_template->data->pdf );
		$inputs['size']['value'] = $printing_template->data->size;
		$inputs['orientation']['value'] = $printing_template->data->orientation;
		$inputs['print_all_pages']['value'] = intval( $printing_template->data->print_all_pages );
		$inputs['print_even_pages']['value'] = intval( $printing_template->data->print_even_pages );
		
		$returnValue .= '
			' . $form->start() . '
			
			' . $this->display_form_table( $inputs ). '

			' . $form->stop() . '
		';
		
		// The next two sections have been broken up into two methods, for easier overview.
		
		// Blocks
		$returnValue .= '<h3>' . $this->_('Blocks') . '</h3>';
		$returnValue .= $this->admin_edit_blocks( $printing_template );
		
		// Fields
		$returnValue .= '<h3>' . $this->_('Fields') . '</h3>';
		$returnValue .= $this->admin_edit_fields( $printing_template );

		echo $returnValue;
	}

	public function admin_edit_blocks( $printing_template )
	{
		$form = $this->form();
		$returnValue = $form->start();
		
		if ( isset( $_POST['action_submit'] ) && isset( $_POST['blocks'] ) )
		{
			if ( $_POST['action'] == 'clone' )
			{
				foreach( $_POST['blocks'] as $block_id => $data )
				{
					$block = clone( $printing_template->data->blocks[ $block_id ] );
					$printing_template->add_block( $block );
				} 
				$this->filters( 'sd_mt_update_printing_template', $printing_template );
				$this->message_( 'The selected blocks have been cloned!' );
			}	// clone
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['blocks'] as $block_id => $data )
				{
					if ( isset( $data['selected'] ) )
						$printing_template->remove_block( $block_id );
				} 
				$this->filters( 'sd_mt_update_printing_template', $printing_template );
			}	// delete
		}
		
		if ( isset( $_POST['create_submit'] ) )
		{
			$create_amount = intval( $_POST['create_amount'] );
			for ( $counter=0; $counter<$create_amount; $counter++ )
			{
				$block = new SD_Meeting_Tool_Printing_Template_Block();
				$printing_template->add_block( $block );
			}
			$this->filters( 'sd_mt_update_printing_template', $printing_template );
		}	// create block

		if ( isset( $_POST['update_blocks'] ) && isset( $_POST['blocks'] ) )
		{
			// Go through the printing template blocks, instead of the post.
			// Easier to call update_block() if you actually have an object, and not an array.
			foreach( $printing_template->blocks() as $block )
			{
				if ( isset( $_POST['blocks'][$block->id] ) )
				{
					$block_data = $_POST['blocks'][ $block->id ];
					$block_data['x'] = str_replace( ',', '.', $block_data['x'] );
					$block_data['y'] = str_replace( ',', '.', $block_data['y'] );
					$block->x = floatval( $block_data['x'] );
					$block->y = floatval( $block_data['y'] );
				}
			}
			$this->filters( 'sd_mt_update_printing_template', $printing_template );
			$this->message_( 'The blocks have been updated!' );
		}	// update blocks

		if ( count( $printing_template->blocks() ) > 0 )
		{
			$t_body = '';
			foreach( $printing_template->data->blocks as $index => $block )
			{
				$nameprefix = '[blocks][' . $block->id . ']';
				$inputs = array(
					'x' => array(
						'name' => 'x',
						'nameprefix' => $nameprefix,
						'type' => 'text',
						'size' => '3',
						'maxlength' => '6',
						'label' => $this->_('X'),
						'value' => $block->x,
					),
					'y' => array(
						'name' => 'y',
						'nameprefix' => $nameprefix,
						'type' => 'text',
						'size' => '3',
						'maxlength' => '6',
						'label' => $this->_('Y'),
						'value' => $block->y,
					),
				);
				$input_block_select = array(
					'name' => 'selected',
					'nameprefix' => $nameprefix,
					'type' => 'checkbox',
					'checked' => false,
					'label' => $block->id,
				);
								
				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input($input_block_select) . ' <span class="screen-reader-text">' . $form->make_label($input_block_select) . '</span></th>
					<td>
						<div class="screen-reader-text">
							' . $form->make_label( $inputs['x'] ) . '
						</div>
						<div>
							' . $form->make_input( $inputs['x'] ) . 'mm
						</div>
					</td>
					<td>
						<div class="screen-reader-text">
							' . $form->make_label( $inputs['y'] ) . '
						</div>
						<div>
							' . $form->make_input( $inputs['y'] ) . 'mm
						</div>
					</td>
				</tr>';
			}
			
			$input_actions = array(
				'type' => 'select',
				'name' => 'action',
				'label' => $this->_('With the selected rows'),
				'options' => array( '' => $this->_('Do nothing') ,
					'clone' => $this->_('Clone'),
					'delete' => $this->_('Delete'),
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
							<th>' . $this->_('X offset') . '</th>
							<th>' . $this->_('Y offset') . '</th>
						</tr>
					</thead>
					<tbody>
						'.$t_body.'
					</tbody>
				</table>
			';

			$input_update_blocks = array(
				'type' => 'submit',
				'name' => 'update_blocks',
				'value' => $this->_( 'Update the blocks' ),
				'css_class' => 'button-primary',
			);
	
			$returnValue .= '<p>' . $form->make_input( $input_update_blocks ) . '</p>';
		}
		
		$inputs_create = array(
			'create_amount' => array(
				'name' => 'create_amount',
				'type' => 'text',
				'value' => 1,
				'size' => 2,
				'maxlength' => 2,
				'label' => $this->_( 'Amount of blocks to create' ),
			),
			'create_submit' => array(
				'name' => 'create_submit',
				'type' => 'submit',
				'value' => $this->_( 'Create new blocks' ),
				'css_class' => 'button-secondary',
			),
		);

		$returnValue .= '
			' . $form->start() . '
			
			' . $this->display_form_table( $inputs_create ) . '
			
			' . $form->stop() . '
		';
		
		return $returnValue;
	}
	
	public function admin_edit_fields( $printing_template )
	{
		$form = $this->form();
		$returnValue = $form->start();
		
		if ( isset( $_POST['action_submit'] ) && isset( $_POST['fields'] ) )
		{
			if ( $_POST['action'] == 'clone' )
			{
				foreach( $_POST['fields'] as $field_id => $data )
				{
					$field = clone( $printing_template->data->fields[ $field_id ] );
					$printing_template->add_field( $field );
				} 
				$this->filters( 'sd_mt_update_printing_template', $printing_template );
				$this->message_( 'The selected fields have been cloned!' );
			}	// clone
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['fields'] as $field_id => $data )
				{
					if ( isset( $data['selected'] ) )
						$printing_template->remove_field( $field_id );
				} 
				$this->filters( 'sd_mt_update_printing_template', $printing_template );
			}	// delete
		}
		
		if ( isset( $_POST['create_field'] ) )
		{
			// Find the first, best participant field.
			$participant_fields = $this->filters( 'sd_mt_get_participant_fields', array() );
			$participant_field = reset( $participant_fields );
			$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
			$field->participant_field = $participant_field->name;
			$printing_template->add_field( $field );
			$this->filters( 'sd_mt_update_printing_template', $printing_template );
		}	// create field

		if ( count( $printing_template->fields() ) > 0 )
		{
			$t_body = '';
			foreach( $printing_template->fields() as $index => $field )
			{
				$nameprefix = '[fields][' . $field->id . ']';
				$input_field_select = array(
					'name' => 'selected',
					'nameprefix' => $nameprefix,
					'type' => 'checkbox',
					'checked' => false,
					'label' => $field->id,
				);
								
				// INFO time.
				$info = array();
				
				$info[] = $this->_( 'Print on page %s at x %s, y %s', $field->page, $field->x, $field->y );
				
				$info[] = $this->_( 'Use font %s at size %s', $field->font_name, $field->font_size );
				
				switch( $field->justification )
				{
					case 'R':
						$justitification = $this->_( 'Right' );
						break;
					case 'C':
						$justitification = $this->_( 'Center' );
						break;
					case 'J':
						$justitification = $this->_( 'Justify' );
						break;
					default:
						$justitification = $this->_( 'Left' );
						break;
				}
				$info[] = $this->_( 'Justificiation: %s', $justitification );
				
				$info = implode( '</div><div>', $info );
				
				$participant_field = $this->filters( 'sd_mt_get_participant_field', $field->participant_field );
				if ( $participant_field )
					$field_name = $participant_field->description;
				else
					$field_name = $this->_( 'Unknown' );
				
				$edit_link = add_query_arg( array(
					'tab' => 'edit_field',
					'field_id' => $field->id,
				) );
				
				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input($input_field_select) . ' <span class="screen-reader-text">' . $form->make_label($input_field_select) . '</span></th>
					<td>
						<div>
							<a
							title="' . $this->_('Edit this field') . '"
							href="'. $edit_link .'">' . $field_name . '</a>
						</div>
					</td>
					<td><div>' . $info . '</div></td>
				</tr>';
			}
			
			$input_actions = array(
				'type' => 'select',
				'name' => 'action',
				'label' => $this->_('With the selected rows'),
				'options' => array( '' => $this->_('Do nothing') ,
					'clone' => $this->_('Clone'),
					'delete' => $this->_('Delete'),
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
							<th>' . $this->_('Field to print') . '</th>
							<th>' . $this->_('Info') . '</th>
						</tr>
					</thead>
					<tbody>
						'.$t_body.'
					</tbody>
				</table>
			';
		}
		
		$input_create_field = array(
			'type' => 'submit',
			'name' => 'create_field',
			'value' => $this->_( 'Add a new field' ),
			'css_class' => 'button-secondary',
		);

		$returnValue .= '
			
			<p>' . $form->make_input( $input_create_field ) . '</p>

			' . $form->stop() . '
		';
		
		return $returnValue;
	}
	
	public function admin_edit_field()
	{
		$form = $this->form();
		$printing_template_id = $_GET['id'];
		$field_id = $_GET['field_id'];
		
		$printing_template = $this->filters( 'sd_mt_get_printing_template', $printing_template_id );
		$field = $printing_template->fields();
		$field = $field[ $field_id ];
		
		$participant_fields = $this->filters( 'sd_mt_get_participant_fields', array() );
		$fonts = $this->filters( 'sd_mt_get_printing_block_field_fonts', array() );

		$inputs = array(
			'participant_field' => array(
				'name' => 'participant_field',
				'type' => 'select',
				'label' => $this->_( 'Participant field' ),
				'options' => array(),
			),
			'page' => array(
				'name' => 'page',
				'type' => 'text',
				'label' => $this->_( 'Page' ),
				'description' => $this->_('Which page to print on. The first page is 1.'),
				'size' => 2,
				'maxlength' => 2,
			),
			'x' => array(
				'name' => 'x',
				'type' => 'text',
				'label' => $this->_( 'X offset' ),
				'description' => $this->_('Millimeters'),
				'size' => 3,
				'maxlength' => 8,
			),
			'y' => array(
				'name' => 'y',
				'type' => 'text',
				'label' => $this->_( 'Y offset' ),
				'description' => $this->_('Millimeters'),
				'size' => 3,
				'maxlength' => 8,
			),
			'width' => array(
				'name' => 'width',
				'type' => 'text',
				'label' => $this->_( 'Width' ),
				'description' => $this->_('Millimeters'),
				'size' => 3,
				'maxlength' => 8,
			),
			'justification' => array(
				'name' => 'justification',
				'type' => 'select',
				'label' => $this->_( 'Justification' ),
				'options' => array(
					'L' => $this->_( 'Left' ),
					'R' => $this->_( 'Right' ),
					'C' => $this->_( 'Center' ),
					'J' => $this->_( 'Justify' ),
				),
			),
			'font_name' => array(
				'name' => 'font_name',
				'type' => 'select',
				'label' => $this->_( 'Font' ),
				'options' => array(),
			),
			'font_size' => array(
				'name' => 'font_size',
				'type' => 'text',
				'label' => $this->_( 'Font size' ),
				'size' => 3,
				'maxlength' => 4,
			),
			'update' => array(
				'type' => 'submit',
				'name' => 'update',
				'value' => $this->_( 'Update field' ),
				'css_class' => 'button-primary',
			),
		);
		
		if ( isset( $_POST['update'] ) )
		{
			$result = $form->validate_post( $inputs, array_keys( $inputs ), $_POST );

			if ($result === true)
			{
				$field->participant_field = $_POST['participant_field'];
				$field->page = intval( $_POST['page'] );
				$field->page = max( $field->page, 1 );
				$_POST['x'] = str_replace( ',', '.', $_POST['x'] );
				$field->x = floatval( $_POST['x'] );
				$_POST['y'] = str_replace( ',', '.', $_POST['y'] );
				$field->y = floatval( $_POST['y'] );
				$_POST['width'] = str_replace( ',', '.', $_POST['width'] );
				$field->width = floatval( $_POST['width'] );
				$field->justification = $_POST['justification'];
				$field->font_name = $_POST['font_name'];
				$field->font_size = floatval( $_POST['font_size'] );
				$printing_template->update_field( $field );

				$this->filters( 'sd_mt_update_printing_template', $printing_template );
				
				$this->message( $this->_('The field has been updated!') );
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}

		$returnValue = '';
		
		foreach( (array)$field as $key => $value )
			if ( isset( $inputs[$key] ) )
				$inputs[$key]['value'] = $value;
		
		foreach( $participant_fields as $participant_field )
			$inputs['participant_field']['options'][ $participant_field->name ] = $participant_field->description;
		
		foreach( $fonts as $font )
			$inputs['font_name']['options'][ $font->id ] = $font->description;
		ksort( $inputs['font_name']['options'] );
		
		$returnValue .= '
			' . $form->start() . '
			
			' . $this->display_form_table( $inputs ). '

			' . $form->stop() . '
			' . $this->make_back_link_to_template_editor() . '
		';
		
		echo $returnValue;
		
	}
	
	public function admin_print()
	{
		$form = $this->form();
		$returnValue = '';
		$lists = $this->filters( 'sd_mt_get_all_lists', array() );
		
		$inputs = array(
			'list_id' => array(
				'name' => 'list_id',
				'type' => 'select',
				'label' => $this->_( 'List to print' ),
				'description' => $this->_( 'All the participants in the list will be printed.' ),
				'options' => array(),
			),
			'sd_meeting_tool_printing_print_list' => array(
				'type' => 'submit',
				'name' => 'sd_meeting_tool_printing_print_list',
				'value' => $this->_( 'Print list' ),
				'css_class' => 'button-primary',
			),
		);
		
		foreach( $lists as $list )
			$inputs['list_id']['options'][ $list->id ] = $list->data->name;
		
		$returnValue .= '
			' . $form->start() . '
			
			' . $this->display_form_table( $inputs ). '
			' . $form->stop() . '
		';
		
		echo $returnValue;
	}
	
	public function admin_wizards()
	{
		$upload_dir = wp_upload_dir();
		if ( $upload_dir[ 'error' ] !== false )
		{
			$this->error( $upload_dir[ 'error' ] );
			return;
		}

		if ( isset( $_POST['submit'] ) )
		{
			$type = $_POST['type'];
			switch( $type )
			{
				case '24_stickers':
						$template_name = $this->_( '24 stickers' );
						$template = new SD_Meeting_Tool_Printing_Template();
						$template->data->name = $template_name;
						
						for ( $y=0; $y<297; $y+=37.125 )
						{
							for( $x=0; $x<210; $x+=70 )
							{
								$block = new SD_Meeting_Tool_Printing_Template_Block();
								$block->x = $x;
								$block->y = $y;
								$template->add_block( $block );
							}
						}
						// And now for some columns!
						// We need any old field...
						$participant_fields = $this->filters( 'sd_mt_get_participant_fields', array() );
						$participant_field = reset( $participant_fields );
						$participant_field = $participant_field->name;
						
						for( $counter=0; $counter<4; $counter++ )
						{
							$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
							$field->participant_field = $participant_field;
							$field->x = 5 + $x;
							$field->y = 5 + ($counter * 5 );
							$field->width = 70 - 5 - 5;
							$field->justification = 'L';
							$field->font_size = 12;
							$template->add_field( $field );
						}
					break;
				case '2_columns_5':
				case '2_columns_10':
				case '2_columns_15':
				case '2_columns_20':
				case '3_columns_5':
				case '3_columns_10':
				case '3_columns_15':
				case '3_columns_20':
				case '4_columns_5':
				case '4_columns_10':
				case '4_columns_15':
				case '4_columns_20':
					$columns = preg_replace( '/_columns.*/', '', $type );
					$mm = preg_replace( '/.*columns_/', '', $type );
					$template_name = $this->_( '%s columns, %s mm per row', $columns, $mm );

					$template = new SD_Meeting_Tool_Printing_Template();
					$template->data->name = $template_name;
					
					for ( $y=15; $y<297-15; $y+=$mm )
					{
						$block = new SD_Meeting_Tool_Printing_Template_Block();
						$block->x = 15;
						$block->y = $y;
						$template->add_block( $block );
					}
					
					// And now for some columns!
					// We need any old field...
					$participant_fields = $this->filters( 'sd_mt_get_participant_fields', array() );
					$participant_field = reset( $participant_fields );
					$participant_field = $participant_field->name;
					
					$width = (210-15-15) / $columns;
					for( $x=0; $x<210-30; $x+=$width )
					{
						$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
						$field->participant_field = $participant_field;
						$field->x = $x;
						$field->y = 0;
						$field->width = $width;
						$field->justification = 'L';
						$field->font_size = 12;
						$template->add_field( $field );
					}
					break;					
				case 'name_badge_4_77x112':
					$post_id = $this->create_attachment( dirname(__FILE__) . '/extra/printing/name_badges_4_77x112.pdf' );
					$template_name = $this->_( 'Name badge, vertical, 77mm x 112mm, 4 per page' );
					
					$template = new SD_Meeting_Tool_Printing_Template();
					$template->data->name = $template_name;
					$template->data->pdf = $post_id;

					$block = new SD_Meeting_Tool_Printing_Template_Block();
					$block->x = 22;
					$block->y = 33;
					$template->add_block( $block );
					
					$block = new SD_Meeting_Tool_Printing_Template_Block();
					$block->x = 112;
					$block->y = 33;
					$template->add_block( $block );
					
					$block = new SD_Meeting_Tool_Printing_Template_Block();
					$block->x = 22;
					$block->y = 155;
					$template->add_block( $block );

					$block = new SD_Meeting_Tool_Printing_Template_Block();
					$block->x = 112;
					$block->y = 155;
					$template->add_block( $block );
					
					// And now for some fields!
					$participant_fields = $this->filters( 'sd_mt_get_participant_fields', array() );
					$participant_field = reset( $participant_fields );
					$participant_field = $participant_field->name;
					
					$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
					$field->participant_field = $participant_field;
					$field->x = 0;
					$field->y = 40;
					$field->width = 76;
					$field->justification = 'C';
					$field->font_size = 24;
					$template->add_field( $field );

					$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
					$field->participant_field = $participant_field;
					$field->x = 0;
					$field->y = 50;
					$field->width = 76;
					$field->justification = 'C';
					$field->font_size = 24;
					$template->add_field( $field );

					$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
					$field->participant_field = $participant_field;
					$field->x = 0;
					$field->y = 60;
					$field->width = 76;
					$field->justification = 'C';
					$field->font_size = 24;
					$template->add_field( $field );

					$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
					$field->participant_field = $participant_field;
					$field->x = 0;
					$field->y = 70;
					$field->width = 76;
					$field->justification = 'C';
					$field->font_size = 24;
					$template->add_field( $field );
					
					break;
				case 'name_badge_9_94x62':
					$post_id = $this->create_attachment( dirname(__FILE__) . '/extra/printing/name_badges_9_94x62.pdf' );
					$template_name = $this->_( 'Name badge, landscape, 94mm x 62mm, 9 per page' );
					
					$template = new SD_Meeting_Tool_Printing_Template();
					$template->data->name = $template_name;
					$template->data->pdf = $post_id;
					$template->data->orientation = 'L';
					
					foreach( array( 7, 100.5, 193.5 ) as $x )
						foreach( array( 12, 74, 136 ) as $y )
						{
							$block = new SD_Meeting_Tool_Printing_Template_Block();
							$block->x = $x;
							$block->y = $y;
							$template->add_block( $block );
						}

					// And now for some fields!
					$participant_fields = $this->filters( 'sd_mt_get_participant_fields', array() );
					$participant_field = reset( $participant_fields );
					$participant_field = $participant_field->name;
					
					$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
					$field->participant_field = $participant_field;
					$field->x = 5;
					$field->y = 5;
					$field->width = 85;
					$field->justification = 'C';
					$field->font_size = 26;
					$template->add_field( $field );

					$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
					$field->participant_field = $participant_field;
					$field->x = 5;
					$field->y = 15;
					$field->width = 85;
					$field->justification = 'C';
					$field->font_size = 26;
					$template->add_field( $field );

					$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
					$field->participant_field = $participant_field;
					$field->x = 5;
					$field->y = 29;
					$field->width = 85;
					$field->justification = 'C';
					$field->font_size = 18;
					$template->add_field( $field );

					$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
					$field->participant_field = $participant_field;
					$field->x = 5;
					$field->y = 42;
					$field->width = 32;
					$field->justification = 'L';
					$field->font_size = 26;
					$template->add_field( $field );

					$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
					$field->participant_field = $participant_field;
					$field->x = 5;
					$field->y = 48;
					$field->width = 32;
					$field->justification = 'L';
					$field->font_size = 26;
					$template->add_field( $field );

					$field = new SD_Meeting_Tool_Printing_Template_Block_Field();
					$field->participant_field = $participant_field;
					$field->x = 5;
					$field->y = 41;
					$field->width = 32;
					$field->justification = 'L';
					$field->font_size = 8;
					$template->add_field( $field );

					break;
			}
			$template = $this->filters( 'sd_mt_update_printing_template', $template );
			
			$edit_link = add_query_arg( array(
				'tab' => 'edit',
				'id' => $template->id,
			) );
			
			$this->message( $this->_( 'Printing template created! <a href="%s">Edit the newly-created printing template</a>.', $edit_link ) );
		}
		$form = $this->form();
		$inputs = array(
			'type' => array(
				'name' => 'type',
				'type' => 'select',
				'label' => $this->_('Type of template'),
				'options' => array(
					'name_badge_4_77x112' => $this->_( 'Name badge, vertical, 77mm x 112mm, 4 per page' ),
					'name_badge_9_94x62' => $this->_( 'Name badge, landscape, 94mm x 62mm, 9 per page' ),
					'2_columns_5' => $this->_( 'Two columns, 5 mm per row' ),
					'2_columns_10' => $this->_( 'Two columns, 10 mm per row' ),
					'2_columns_15' => $this->_( 'Two columns, 15 mm per row' ),
					'2_columns_20' => $this->_( 'Two columns, 20 mm per row' ),
					'3_columns_5' => $this->_( 'Three columns, 5 mm per row' ),
					'3_columns_10' => $this->_( 'Three columns, 10 mm per row' ),
					'3_columns_15' => $this->_( 'Three columns, 15 mm per row' ),
					'3_columns_20' => $this->_( 'Three columns, 20 mm per row' ),
					'4_columns_5' => $this->_( 'Four columns, 5 mm per row' ),
					'4_columns_10' => $this->_( 'Four columns, 10 mm per row' ),
					'4_columns_15' => $this->_( 'Four columns, 15 mm per row' ),
					'4_columns_20' => $this->_( 'Four columns, 20 mm per row' ),
					'24_stickers' => $this->_( 'Stickers - 3 stickers * 8 rows' ),
				),
			),
			'submit' => array(
				'name' => 'submit',
				'type' => 'submit',
				'value' => $this->_('Create a new template from the above type'),
				'css_class' => 'button-primary',
			),
		);
		$returnValue = $form->start();
		$returnValue .= $this->display_form_table( $inputs );
		$returnValue .= $form->stop();
		echo $returnValue;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Actions
	// --------------------------------------------------------------------------------------------
	
	public function admin_init()
	{
		parent::admin_init();
		
		if (  isset( $_POST['sd_meeting_tool_printing_print_list'] )  && isset( $_GET['id'] ) )
			$this->print_list( intval( $_GET['id'] ), intval( $_POST['list_id'] ) );
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------

	public function sd_mt_delete_printing_template( $SD_Meeting_Tool_Printing_Template )
	{
		global $blog_id;
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_printing_templates`
			WHERE `id` = '" . $SD_Meeting_Tool_Printing_Template->id . "'
			AND `blog_id` = '$blog_id'
		";
		$this->query( $query );
	}
	
	public function sd_mt_get_all_printing_templates()
	{
		global $blog_id;
		$query = "SELECT id FROM `".$this->wpdb->base_prefix."sd_mt_printing_templates` WHERE `blog_id` = '$blog_id'";
		$results = $this->query( $query );
		$returnValue = array();
		
		foreach( $results as $result )
			$returnValue[ $result['id'] ] = $this->sd_mt_get_printing_template( $result['id'] );

		return SD_Meeting_Tool::sort_data_array( $returnValue, 'name' );
	}
	
	public function sd_mt_get_printing_block_field_fonts( $fonts )
	{
		$font = new SD_Meeting_Tool_Printing_Block_Field_Font();
		$fonts[] = $font;
		
		$font = new SD_Meeting_Tool_Printing_Block_Field_Font();
		$font->id = 'timesb';
		$font->bold = true;
		$font->description = 'Times New Roman Bold';
		$fonts[] = $font;
		
		$font = new SD_Meeting_Tool_Printing_Block_Field_Font();
		$font->id = 'timesi';
		$font->bold = false;
		$font->italic = true;
		$font->description = 'Times New Roman Italic';
		$fonts[] = $font;
		
		$font = new SD_Meeting_Tool_Printing_Block_Field_Font();
		$font->id = 'timesib';
		$font->bold = true;
		$font->description = 'Times New Roman Bold Italic';
		$fonts[] = $font;
		
		$font = new SD_Meeting_Tool_Printing_Block_Field_Font();
		$font->id = 'helvetica';
		$font->name = 'helvetica';
		$font->description = 'Helvetica';
		$fonts[] = $font;
		
		$font = new SD_Meeting_Tool_Printing_Block_Field_Font();
		$font->id = 'helveticab';
		$font->bold = true;
		$font->description = 'Helvetica Bold';
		$fonts[] = $font;
		
		$font = new SD_Meeting_Tool_Printing_Block_Field_Font();
		$font->id = 'helveticai';
		$font->bold = false;
		$font->italic = true;
		$font->description = 'Helvetica Italic';
		$fonts[] = $font;
		
		$font = new SD_Meeting_Tool_Printing_Block_Field_Font();
		$font->id = 'helveticabi';
		$font->bold = true;
		$font->description = 'Helvetica Bold Italic';
		$fonts[] = $font;
		
		$font = new SD_Meeting_Tool_Printing_Block_Field_Font();
		$font->id = 'code39';
		$font->name = 'code39';
		$font->description = $this->_('Barcode: %s', $font->name );
		$fonts[] = $font;
		
		return $fonts;
	}
	
	public function sd_mt_get_printing_template( $SD_Meeting_Tool_Printing_Template_ID )
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_printing_templates` WHERE `id` = '$SD_Meeting_Tool_Printing_Template_ID' AND `blog_id` = '$blog_id'";
		$result = $this->query_single( $query );
		if ( $result === false )
			return false;

		return $this->printing_template_sql_to_object( $result );
	}
	
	public function sd_mt_update_printing_template( $SD_Meeting_Tool_Printing_Template )
	{
		global $blog_id;
		$data = $this->sql_encode( $SD_Meeting_Tool_Printing_Template->data );
		$data = $this->sql_encode( $SD_Meeting_Tool_Printing_Template->data );
		
		if ( $SD_Meeting_Tool_Printing_Template->id === null )
		{
			$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_printing_templates`
				(`blog_id`, `data`)
				VALUES
				('". $blog_id ."', '" . $data . "')
			";
			$SD_Meeting_Tool_Printing_Template->id = $this->query_insert_id( $query );
		}
		else
		{
			$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_printing_templates`
				SET
				`data` = '" . $data. "'
				WHERE `id` = '" . $SD_Meeting_Tool_Printing_Template->id . "'
				AND `blog_id` = '$blog_id'
			";
			$this->query( $query );
		}
		return $SD_Meeting_Tool_Printing_Template;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------
	
	/**
		Convert a printing template row from SQL to an SD_Meeting_Tool_Printing_Template.
		
		@param		$sql		Row from the database as an array.
		@return					A complete SD_Meeting_Tool_Printing_Template object.
	**/ 
	private function printing_template_sql_to_object( $sql )
	{
		$printing_template = new SD_Meeting_Tool_Printing_Template();
		$printing_template->id = $sql['id'];
		$printing_template->data = (object) array_merge( (array)$printing_template->data, (array)$this->sql_decode( $sql['data'] ) );
		return $printing_template;
	}

	/**
 		Makes a link back to the template editor.
		
		@return		Back link string.
	**/
	private function make_back_link_to_template_editor()
	{
		$url = remove_query_arg( array('field_id', 'tab') );
		$url = add_query_arg( array(
			'tab' => 'edit',
		), $url );
		
		return SD_Meeting_Tool::make_back_link( $this->_( 'Back to the template editor' ), $url );
	}
	
	/**
		Creates a standard PDF.
		@return		A configured TCPDF class.
	**/
	private function pdf( $SD_Meeting_Tool_Printing_Template )
	{
		$template = $SD_Meeting_Tool_Printing_Template;		// Convenience.
		
		require_once( dirname(__FILE__) . '/include/libraries/tcpdf/tcpdf.php');
		require_once( dirname(__FILE__) . '/include/libraries/fpdi/fpdi.php');
		$pdf = new FPDI( $template->data->orientation, 'mm', $template->data->size );
		$pdf->setPrintHeader( false );
		$pdf->setPrintFooter( false );
		$pdf->SetAutoPageBreak( false );
		$pdf->SetMargins( 0, 0, 0, true );
		$pdf->AddFont( 'code39', '', '', false );
		
		return $pdf;
	}
	
	/**
		Returns a list of all attachments that are PDFs.
		
		Incidentally, the return format is an array that fits in perfectly as an options array in a select...
		
		@return		Array of post->ID => url, containing a list of all attachments that are PDFs.
	**/
	private function get_pdf_attachments()
	{
		$returnValue = array();
		$args = array( 'post_type' => 'attachment', 'numberposts' => -1, 'post_parent' => null ); 
		$attachments = get_posts( $args );
		if ($attachments)
		{
			foreach ( $attachments as $post )
			{
				if ( strpos( $this->strtolower( $post->guid ), '.pdf' ) !== false )
					$returnValue[ $post->ID ] = preg_replace( '/.*\//', '', $post->guid );
			}
		}
		return $returnValue;
	}
	
	private function create_attachment( $realpath )
	{
		$filename = basename( $realpath );
		
		// Try to find the attachment, first.
		$attachments = $this->get_pdf_attachments();
		$attachments = array_flip( $attachments );
		
		if ( isset( $attachments[$filename] ) )
			return $attachments[$filename];
		
		// No attachment? Time to create one.
		
		// Copy the file to the uploads directory.
		$upload_dir = wp_upload_dir();
		$uploaded_file = $upload_dir['path'] . '/' . $filename;
		copy( $realpath, $uploaded_file );
		$wp_filetype = wp_check_filetype( basename($filename), null );
		$attachment = array(
			'post_mime_type' => $wp_filetype[ 'type' ],
			'post_title' => preg_replace('/\.[^.]+$/', '', $filename),
			'post_content' => '',
			'post_status' => null,
			'guid' => $upload_dir['url'] . '/' . $filename,
		);
		$id = wp_insert_attachment( $attachment, $uploaded_file );
		return $id;
	}
	
	private function print_list( $id, $list_id )
	{
		// Does this template exist?
		$printing_template = $this->filters( 'sd_mt_get_printing_template', $id );
		if ( ! $printing_template )
			return;
			
		$list = $this->filters( 'sd_mt_get_list', $list_id );
		if ( ! $list )
			return;
		
		$list = $this->filters( 'sd_mt_list_participants', $list );
		$all_fonts = $this->filters( 'sd_mt_get_printing_block_field_fonts', array() );
		$fonts = array();
		foreach( $all_fonts as $font )
			$fonts[ $font->id ] = $font;
		
		$pdf = $this->pdf( $printing_template );
		
		// Are we using a template?
		$using_template = false;
		$templates = array();
		if ( $printing_template->data->pdf > 0 )
		{
			$post = get_post( $printing_template->data->pdf );
			if ( $post !== null )
			{
				$source_file = get_attached_file( $printing_template->data->pdf );
				$pagecount = $pdf->setSourceFile( $source_file );
				
				if ( $pagecount < 1 )
				{
					$this->error( $this->_( 'The template does not contain any pages! Can not continue!' ) );
					return;
				}
				
				for( $counter=1; $counter<=$pagecount; $counter++ )
					$templates[ $counter ] = $pdf->importPage( $counter, '/MediaBox' );
				$using_template = true;
			}
		}
		
		// Sort the fields into pages.
		$all_fields = $printing_template->fields();
		$fields = array();
		foreach( $all_fields as $field )
		{
			$page = $field->page;
			if ( !isset( $fields[ $page ] ) )
				$fields[ $page ] = array();
			$fields[ $page ][] = $field;
		}
		ksort( $fields );
		
		while ( count( $list->participants ) > 0 )
		{
			$pdf->AddPage();
			$current_page = 1;
			if ( $using_template )
				$pdf->useTemplate( $templates[ $current_page ] );

			foreach( $printing_template->blocks() as $block )
			{
				if ( count( $list->participants ) < 1 )
					break;

				$participant = array_shift( $list->participants );
				
				$current_page = 1;

				foreach( $fields as $page_no => $page_fields )
				{
					if ( $page_no != $current_page )
					{
						$pdf->AddPage();
						$current_page++;
						if ( $using_template && isset( $templates[ $current_page ] ) )
							$pdf->useTemplate( $templates[ $current_page ] );
					}
					 
					foreach( $page_fields as $field )
					{
						$pdf->setXY( $block->x + $field->x, $block->y + $field->y );
						
						// Set the font
						$font = $field->font_name;
						$font = $fonts[ $font ];
						$style = '';
						$style .= ( $font->bold ? 'b' : '' );
						$style .= ( $font->italic ? 'i' : '' );
						$pdf->SetFont( $font->name, $style, $field->font_size );
						
						// And now finally write the output!
						$field_name = $field->participant_field;
						$pdf->Cell(
							$field->width,
							0,							// Height
							$participant->$field_name,
							'',							// Border
							'',							// Line
							$field->justification		// J
						);
					}
				}
			}

			if ( $using_template && $printing_template->data->print_all_pages )
			{
				for( $counter = $current_page + 1; $counter <= $pagecount; $counter++ )
				{
					$pdf->AddPage();
						if ( $using_template && isset( $templates[ $current_page ] ) )
							$pdf->useTemplate( $templates[ $counter ] );
				}
				if ( $printing_template->data->print_even_pages && ($pagecount % 2 != 0 ) )
				{
					$pdf->AddPage();
				}
			}
		}
		
		$pdf_name = 'SD Meeting Tool Printing ' . date('Y-m-d H:i:s') . '.pdf';
		$pdf->Output( $pdf_name );
		exit;
	}
	
}
$SD_Meeting_Tool_Printing = new SD_Meeting_Tool_Printing();

// --------------------------------------------------------------------------------------------
// ----------------------------------------- class SD_Meeting_Tool_Printing_Template
// --------------------------------------------------------------------------------------------
/**
	@brief		Printing template, containing blocks in which to print a participant.
	@see		SD_Meeting_Tool_Printing
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Printing_Template
{
	/**
		ID of the template.
		
		@var	$id
	**/
	public $id;
	
	/**
		Serialized data.
		Contains:
		
		- @b blocks				An array of {SD_Meeting_Tool_Printing_Template_Block}s.
		- @b size				Paper size. A4 is default.
		- @b orientation		Paper orientation. P is default.
		- @b name				Name of the template.
		- @b pdf				PDF attachment (post attachment id) to use as template.
		- @b print_all_pages	If a template is used: automatically insert the rest of the template's pages after each participant.
		- @b print_even_pages	Print an even amount of pages (insert an empty page at the end of odd-numbered templates).
		- @b fields				An array of {SD_Meeting_Tool_Printing_Template_Field}s to be printing in each block.
		
		Note that this is the container class for the fields, since each block has exactly the same fields.
		
		@var	$data
	**/ 
	public $data;

	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->blocks = array();
		$this->data->fields = array();
		$this->data->name = '';
		$this->data->orientation = 'P';
		$this->data->pdf = false;
		$this->data->print_all_pages = true;
		$this->data->print_even_pages = true;
		$this->data->size = 'A4';
	}
	
	/**
		@return		The blocks we have.
	**/
	public function blocks()
	{
		return $this->data->blocks;
	}
	
	/**
		@return		The fields we have.
	**/
	public function fields()
	{
		return $this->data->fields;
	}
	
	/**
		Adds a new thing to somewhere (an array).
		
		@param	$thing		Object to add.
		@param	$where		An array in which to place the new thing.
		@return				The thing with the new id set.
	**/
	private function add( $thing, &$where )
	{
		$thing->id = SD_Meeting_Tool::random_uuid();
		$where[ $thing->id ] = $thing;
		return $thing;
	}
	
	/**
		Looks for a $thing with a specific ID in a $where.
		
		@param		$id		ID to look for.
		@param		$where	Array to look in.
		@return				The found thing, else false.
	**/
	private function find( $id, $where )
	{
		foreach( $where as $thing )
			if ( $thing->id == $id )
				return $thing;
		return false;
	}
	
	/**
		Removes a thing from somewhere (an array).
		
		@param	$thing		Object to remove.
		@param	$where		The array from whence to remove the thing.
	**/
	private function remove( $thing, &$where )
	{
		foreach( $where as $index => $object )
			if ( $thing->id == $object->id )
			{
				unset( $where[ $index ] );
				break;
			}
	}
	
	/**
		Updates a $thing in the array $where.
		
		Uses the thing's ID to find and replace.
		
		@param	$thing		New thing.
		@param	$where		Array of things.
	**/
	private function update( $thing, &$where )
	{
		foreach( $where as $index => $object )
			if ( $thing->id == $object->id )
			{
				$where[ $index ] = $thing;
				break;
			}
	}
	
	/**
		Adds a new block to our array of blocks.
		
		Assigns an ID and adds it to the array.

		@param		$SD_Meeting_Tool_Printing_Template_Block	Block to add.
		@return		A SD_Meeting_Tool_Printing_Template_Block with the new ID set.
	**/
	public function add_block( $SD_Meeting_Tool_Printing_Template_Block )
	{
		return $this->add( $SD_Meeting_Tool_Printing_Template_Block, $this->data->blocks );
	}
	
	/**
		Removes a block.

		@param		$id		ID of block to remove.
	**/
	public function remove_block( $id )
	{
		$block = $this->find( $id, $this->data->blocks ); 
		if ( $block )
			$this->remove( $block, $this->data->blocks );
	}
	
	/**
		Updates a block.

		@param		$SD_Meeting_Tool_Printing_Template_Block	Block to update.
	**/
	public function update_block( $SD_Meeting_Tool_Printing_Template_Block )
	{
		$this->update( $SD_Meeting_Tool_Printing_Template_Block, $this->data->blocks );
	}
	
	/**
		Adds a new field to our array of fields.
		
		Assigns an ID and adds it to the array.

		@param		$SD_Meeting_Tool_Printing_Template_Block_Field	Field to add.
		@return		A SD_Meeting_Tool_Printing_Template_Block_Field with the new ID set.
	**/
	public function add_field( $SD_Meeting_Tool_Printing_Template_Block_Field )
	{
		return $this->add( $SD_Meeting_Tool_Printing_Template_Block_Field, $this->data->fields );
	}
	
	/**
		Removes a field.

		@param		$id		ID of field to remove.
	**/
	public function remove_field( $id )
	{
		$field = $this->find( $id, $this->data->fields );
		if ( $field )
			$this->remove( $field, $this->data->fields );
	}
	
	/**
		Updates a field.

		@param		$SD_Meeting_Tool_Printing_Template_Block_Field	Field to update.
	**/
	public function update_field( $SD_Meeting_Tool_Printing_Template_Block_Field )
	{
		$this->update( $SD_Meeting_Tool_Printing_Template_Block_Field, $this->data->fields );
	}
}

// --------------------------------------------------------------------------------------------
// ----------------------------------------- class SD_Meeting_Tool_Printing_Template_Block
// --------------------------------------------------------------------------------------------
/**
	@brief		A template block is an area on the page in which participant fields are printed. 
	@see		SD_Meeting_Tool_Printing
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Printing_Template_Block
{
	/**
		(UU)ID of this block.
		@var	$id
	**/
	public $id;
	
	/**
		X offset of the block on the page.
		@var	$x
	**/
	public $x = 0;
	
	/**
		Y offset of the block on the page.
		@var	$y
	**/
	public $y = 0;
}

// --------------------------------------------------------------------------------------------
// ----------------------------------------- class SD_Meeting_Tool_Printing_Template_Field
// --------------------------------------------------------------------------------------------
/**
	The offsets are calculated in relation to the block in which this class is contained.
	
	@brief		The Printing template field specifies which participant field to print, where and how.
	@see		SD_Meeting_Tool_Printing
	@see		SD_Meeting_Tool_Printing_Template_Block
	@see		SD_Meeting_Tool_Printing_Template
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Printing_Template_Block_Field
{
	/**
		(UU)ID of this field.
		@var	$id
	**/
	public $id;
	
	/**
		Page on which to print this field.
		@var	$page
	**/
	public $page;
	
	/**
		Participant field to print.
		@var	$participant_field
	**/
	public $participant_field;

	/**
		X offset of the text field, in millimeters.
		@var	$x
	**/
	public $x;

	/**
		Y offset of the text field, in millimeters.
		@var	$y
	**/
	public $y;

	/**
		Width of text field, in millimeters.
		@var	$width
	**/
	public $width;

	/**
		A string for the text justification.
		L left
		R right
		C center
		J justified
		@var	$justification
	**/
	public $justification;

	/**
		Font name string.
		@var	$font_name
	**/
	public $font_name;

	/**
		Font size, as an integer.
		@var	$font_size
	**/
	public $font_size;
	
	public function __construct()
	{
		$this->page = 1;
		$this->x = 0;
		$this->y = 0;
		$this->width = 100;
		$this->justification = 'L';
		$this->font_name = 'times';
		$this->font_size = 12;
	}
}

// --------------------------------------------------------------------------------------------
// ----------------------------------------- class SD_Meeting_Tool_Printing_Template_Block
// --------------------------------------------------------------------------------------------
/**
	@brief		A font available in the printing block field. 
	@see		SD_Meeting_Tool_Printing_Block
	@see		SD_Meeting_Tool_Printing_Block_Field
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Printing_Block_Field_Font
{
	public $id;
	public $name;
	public $description;
	public $bold;
	public $italic;
	
	public function __construct()
	{
		$this->id = 'times';
		$this->name = 'times';
		$this->description = 'Times New Roman';
		$this->bold = false;
		$this->italic = false;
	}
}