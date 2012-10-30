<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool Elections
Plugin URI: https://it.sverigedemokraterna.se
Description: Enables elections.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

/**
	@page		sd_meeting_tool_elections					Plugin: Elections
	
	@section	sd_meeting_tool_elections_description		Description
	
	Provides three types of election handling:
	
	- @b manual elections have their choices specified by the admin and the result is filled in manually.
	- @b open elections use participants and lists to keep track of who voted for what choice and when.
	- @b anonymous elections have their choices specified by the admin, the users are registered and when the votes are tallied
	afterwards the results are filled in manually.
	
	An electoral register is used for open and anonymous elections.
	
	The plugin provides shortcodes for displaying election results to visitors.
	
	@section	sd_meeting_tool_elections_requirements		Requirements
	
	- @ref index
	- @ref SD_Meeting_Tool_Lists
	- @ref SD_Meeting_Tool_Participants
	- @ref SD_Meeting_Tool_Registrations
	
	@section	sd_meeting_tool_elections_installation		Installation
	
	Enable the plugin in Wordpress.
	
	The plugin will then create the following database tables:
	
	- @b prefix_sd_mt_elections
		Serialized election objects.
	
	@section	sd_meeting_tool_elections_usage				Usage
	
	The three types of elections are handled somewhat differently. Two of them, open and anonymous elections, need an electoral
	register. The simplest election type, the manual, requires nothing but some voting choices and the results to be typed in
	manually.
	
	@subsection	sd_meeting_tool_elections_usage_electoral_register		The electoral register
	
	The electoral register calculates which participants have the right to vote, depending on which of the participants are
	present. The total participants are separated into groups, the 1-first order voters are calculated and then the votes
	are distributed to those participants that have voting rights @b and are present at the meeting.
	
	When editing the election two fields are used:
	
	- The @b group field optionally separates the voters into groups. Each group becomes a separate pool of voters, separated from
	the other groups.
	- After that the @b voting @b order field specifies which participants may vote.
	
	In the @b voting @b order field, specify a number. 1 is for first-order voters, meaning that they constitute the max amount
	of voters for the group. @sdmt sums the amount of 1 voters for each group.
	
	When calculating who gets to vote, if a first-order voter in the gorup is absent, a second-order voter will be able to vote
	in his place. If yet another first-order voter is absent, another second-order voter will be given voting rights. If a
	second-order voter is not available then a third-order will be tried. Repeat until there are no >1 voters left.
	
	If there are several participants that have the same voting order the first available participant will be chosen.
	
	@subsection	sd_meeting_tool_elections_usage_manual			Manual elections
	
	- Configure the voting choices.
	- Start registering votes.
	- Fill in how many voted for each choice.
	- Finish registering votes.
	- View the results.
	
	The simplest type of election used to quick hand counts. Create it, specify the name and which choices the voters can
	vote for. Don't forget to specify an @b abstain choice.
	
	The next step is to @b register @b votes. You will be shown an informational text specifying that the choices can not be
	changed. Accept and then simply type in how many voted for each choice.
	
	When the votes have been counted, finish registering votes.
	
	Now the results can be viewed and a shortcode can be created to show the results to the visitors.
	
	@subsection	sd_meeting_tool_elections_usage_anonymous		Anonymous elections
	
	- Configure the voting choices.
	- Configure the electoral register choices.
	- Calculate the electoral register.
	- Start registering votes.
	- Fill in how many voted for each choice.
	- Finish registering votes.
	- View the results.
	
	Similar to manual elections, the anonymous type has choices that must be filled-in manually but also has an electoral register
	and lists that control who may vote and who has voted.
	
	Each time the election is edited the electoral register is cleared.
	
	After registering all the votes the votes can be counted and the results typed in the results box.
	
	Finish registering votes to go to the results page.
	
	@subsection	sd_meeting_tool_elections_usage_open			Open elections
	
	- Configure the voting choices.
	- Configure the electoral register choices.
	- Calculate the electoral register.
	- Start registering votes.
	- Fill in each participant's choice.
	- Finish registering votes.
	- View the results.
	
	Similar to anonymous elections, the open type has an electoral register and lists for who may and has voted. 
	
	The choices, instead of just being text boxes, are now associated to lists. Every participant that has voted for a specific
	choice is placed into the associated list.
	
	@section	sd_meeting_tool_elections_tips				Tips
	
	@par		Copy lists, not actions.
	
	If you use auto-generated lists for may-vote and have-vote, you will find that creating separate registrations and
	actions for each election will require a lot of work.
	
	It would be faster to use a just one action and one set of lists and copy the results to archive lists. Like so:
	
	- Create a generic "have voted" list.
	- Create a generic "may vote" list. Exclude the generic "have voted" list.
	- Create an action that puts the participant in the generic "have voted" list.
	- Create a registration that uses the generic "may vote" list as the source.
	- Now create the election
	- The election should have its own have voted and "may vote" lists.
	- Configure the generic "may vote" list to use the election's "may vote" list.
	
	The participants that have voted will be placed in the generic "have voted" list.
	
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
*/

/**
	@brief		Plugin providing election services.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
	
	@par		Changelog
	
	@par		1.1
	
	- Added participant query function
**/
class SD_Meeting_Tool_elections
	extends SD_Meeting_Tool_0
{
	public function __construct()
	{
		parent::__construct( __FILE__ );

		// Internal filters
		add_filter( 'sd_mt_delete_election',			array( &$this, 'sd_mt_delete_election' ) );
		add_filter( 'sd_mt_get_all_elections',			array( &$this, 'sd_mt_get_all_elections') );
		add_filter( 'sd_mt_get_election',				array( &$this, 'sd_mt_get_election' ) );
		add_filter( 'sd_mt_update_election',			array( &$this, 'sd_mt_update_election' ) );
		
		// Ajax
		add_action( 'wp_ajax_ajax_sd_mt_elections',		array( &$this, 'ajax_sd_mt_elections') );
		
		// Shortcodes
		add_shortcode('display_election_graph',			array( &$this, 'shortcode_display_election_graph') );
		add_shortcode('display_election_statistics',	array( &$this, 'shortcode_display_election_statistics') );
		
		// External actions
		add_filter( 'sd_mt_admin_menu',					array( &$this, 'sd_mt_admin_menu' ) );
	}

	public function activate()
	{
		parent::activate();		
		$this->query("CREATE TABLE IF NOT EXISTS `".$this->wpdb->base_prefix."sd_mt_elections` (
		  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID of election',
		  `blog_id` int(11) NOT NULL COMMENT 'Blog ID this election belongs to',
		  `data` longtext NOT NULL COMMENT 'Serialized election data',
		  PRIMARY KEY (`id`),
		  KEY `blog_id` (`blog_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1;
		");
	}
	
	public function uninstall()
	{
		parent::uninstall();
		$this->query("DROP TABLE `".$this->wpdb->base_prefix."sd_mt_elections`");
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------
	
	public function sd_mt_admin_menu( $menus )
	{
		$this->load_language();

		$menus[ $this->_('Elections') ] = array(
			'sd_mt',
			$this->_('Elections'),
			$this->_('Elections'),
			'read',
			'sd_mt_elections',
			array( &$this, 'admin' )
		);

		wp_enqueue_style( 'sd_mt_elections', '/' . $this->paths['path_from_base_directory'] . '/css/SD_Meeting_Tool_Elections.css', false, '1.0', 'screen' );
		return $menus;
	}
	
	/**
		@brief		Main admin overview.
	**/
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
				$tab_data['tabs']['edit'] = $this->_( 'Edit' );
				$tab_data['functions']['edit'] = 'admin_edit';

				$election = $this->filters( 'sd_mt_get_election', $_GET['id'] );
				if ( $election === false )
					wp_die( $this->_( 'Specified election does not exist!' ) );

				$tab_data['page_titles']['edit'] = $this->_( 'Editing election: %s', $election->data->name );
			}	// edit

			if ( $_GET['tab'] == 'electoral_register' )
			{
				$tab_data['tabs']['electoral_register'] = $this->_( 'Electoral register' );
				$tab_data['functions']['electoral_register'] = 'admin_electoral_register';

				$election = $this->filters( 'sd_mt_get_election', $_GET['id'] );
				if ( $election === false )
					wp_die( $this->_( 'Specified election does not exist!' ) );

				if ( ! $election->has_electoral_register() )
					wp_die( $this->_( 'Specified election does not have an electoral register!' ) );

				$tab_data['page_titles']['electoral_register'] = $this->_( 'Electoral register for: %s', $election->data->name );
			}	// electoral register

			if ( $_GET['tab'] == 'query_register' )
			{
				$tab_data['tabs']['query_register'] = $this->_( 'Query electoral register' );
				$tab_data['functions']['query_register'] = 'admin_query_register';

				$election = $this->filters( 'sd_mt_get_election', $_GET['id'] );
				if ( $election === false )
					wp_die( $this->_( 'Specified election does not exist!' ) );

				if ( ! $election->has_electoral_register() )
					wp_die( $this->_( 'Specified election does not have an electoral register!' ) );

				$tab_data['page_titles']['query_register'] = $this->_( 'Querying electoral register for: %s', $election->data->name );
			}	// query register

			if ( $_GET['tab'] == 'register' )
			{
				$tab_data['tabs']['register'] = $this->_( 'Register' );
				$tab_data['functions']['register'] = 'admin_register';

				$election = $this->filters( 'sd_mt_get_election', $_GET['id'] );
				if ( $election === false )
					wp_die( $this->_( 'Specified election does not exist!' ) );
				
				$list = $this->filters( 'sd_mt_get_list', $election->data->have_voted );
				$list = $this->filters( 'sd_mt_list_participants', $list );

				$tab_data['page_titles']['register'] = $this->_( 'Vote registration for: %s', $election->data->name );
			}	// Register

			if ( $_GET['tab'] == 'results' )
			{
				$tab_data['tabs']['results'] = $this->_( 'View results' );
				$tab_data['functions']['results'] = 'admin_results';

				$election = $this->filters( 'sd_mt_get_election', $_GET['id'] );
				if ( $election === false )
					wp_die( $this->_( 'Specified election does not exist!' ) );
				
				$tab_data['page_titles']['results'] = $this->_( 'View results for: %s', $election->data->name );
			}	// View results
		}

		$this->tabs($tab_data);
	}
	
	/**
		@brief		Overview tab.
	**/
	public function admin_overview()
	{
		if ( isset( $_POST['action_submit'] ) && isset( $_POST['elections'] ) )
		{
			if ( $_POST['action'] == 'clone' )
			{
				foreach( $_POST['elections'] as $election_id => $ignore )
				{
					$election = $this->filters( 'sd_mt_get_election', $election_id );
					if ( $election !== false )
					{
						$election->data->name = $this->_( 'Copy of %s', $election->data->name );
						$election->uuid = null;
						$election->data->status = 'editing';
						$election->clear_electoral_register();
						$election = $this->filters( 'sd_mt_update_election', $election );

						$edit_link = add_query_arg( array(
							'tab' => 'edit',
							'id' => $election->id,
						) );
						
						$this->message_( 'Election cloned! <a href="%s">Edit the newly-cloned election</a>.', $edit_link );
					}
				}
			}	// clone
			
			if ( $_POST['action'] == 'delete' )
			{
				foreach( $_POST['elections'] as $election_id => $ignore )
				{
					$election = $this->filters( 'sd_mt_get_election', $election_id );
					if ( $election !== false )
					{
						$this->filters( 'sd_mt_delete_election', $election );
						$this->message_( 'Election <em>%s</em> deleted.', $election_id );
					}
				}
			}	// delete
		}
		
		if ( isset( $_POST['create_election'] ) )
		{
			$election = new SD_Meeting_Tool_Election();
			$election->data->type= $_POST['type'];
			$election->data->name = $this->_( 'Election created %s', $this->now() );
			$election = $this->filters( 'sd_mt_update_election', $election );
			
			$edit_link = add_query_arg( array(
				'tab' => 'edit',
				'id' => $election->id,
			) );
			
			$this->message_( 'Election created! <a href="%s">Edit the newly-created election</a>.', $edit_link );
		}	// create election

		$form = $this->form();
		$rv = $form->start();
		
		$elections = $this->filters( 'sd_mt_get_all_elections', null );
		
		if ( count( $elections ) < 1 )
		{
			$this->message_( 'No elections found.' );
		}
		else
		{
			$t_body = '';
			foreach( $elections as $election )
			{
				$input_election_select = array(
					'type' => 'checkbox',
					'checked' => false,
					'label' => $election->id,
					'name' => $election->id,
					'nameprefix' => '[elections]',
				);
				
				$default_action = array();		// What to do when the user clicks on the election.
								
				// ACTION time.
				$actions = array();
				
				if ( $election->is_finished() )
				{
					// Electoral register action
					$results_action_url = add_query_arg( array(
						'tab' => 'results',
						'id' => $election->id,
					) );
					$actions[] = '<a href="'.$results_action_url.'">'. $this->_('View results') . '</a>';
					
					$default_action = array(
						'title' => $this->_( 'View results' ),
						'url' => $results_action_url,
					);
				}
				
				if (
					! $election->is_finished()
					&& $election->finished_editing()
					&& $election->is_electoral_register_calculated()
				)
				{
					// Registration action
					$register_action_url = add_query_arg( array(
						'tab' => 'register',
						'id' => $election->id,
					) );
					$actions[] = '<a href="'.$register_action_url.'">'. $this->_('Register votes') . '</a>';

					$default_action = array(
						'title' => $this->_( 'Register votes' ),
						'url' => $register_action_url,
					);
				}
				
				if ( $election->has_electoral_register() && $election->finished_editing() )
				{
					// Electoral register action
					$electoral_register_action_url = add_query_arg( array(
						'tab' => 'electoral_register',
						'id' => $election->id,
					) );
					$actions[] = '<a href="'.$electoral_register_action_url.'">'. $this->_('Electoral register') . '</a>';
					
					if ( ! $election->is_electoral_register_calculated() )
						$default_action = array(
							'title' => $this->_( 'Calculate the electoral register' ),
							'url' => $electoral_register_action_url,
						);
				}
				
				if ( $election->has_electoral_register() )
				{
					// Electoral register action
					$query_register_action_url = add_query_arg( array(
						'tab' => 'query_register',
						'id' => $election->id,
					) );
					$actions[] = '<a href="'.$query_register_action_url.'">'. $this->_('Query register') . '</a>';
				}
				
				// Edit election action
				$edit_action_url = add_query_arg( array(
					'tab' => 'edit',
					'id' => $election->id,
				) );
				$actions[] = '<a href="'.$edit_action_url.'">'. $this->_('Edit') . '</a>';
				
				if ( count( $default_action ) < 1 )
					$default_action = array(
						'title' => $this->_( 'Edit' ),
						'url' => $edit_action_url,
					);
				
				$actions = implode( '&emsp;<span class="sep">|</span>&emsp;', $actions );
				
				// INFO time.
				$info = array();

				$info[] = $this->_('Type: %s', ucfirst($election->data->type) );
				
				if ( $election->has_electoral_register() )
				{
					if ( ! $election->is_electoral_register_calculated() )
						$info[] = $this->_( 'The electoral register has not been calculated yet.' );
					else
						$info[] = $this->_( 'The electoral register was calculated at %s.', date('Y-m-d H:i:s', $election->data->electoral_register_calculated ) );
				}

				$info = implode( '</div><div>', $info );
				
				$t_body .= '<tr>
					<th scope="row" class="check-column">' . $form->make_input($input_election_select) . ' <span class="screen-reader-text">' . $form->make_label($input_election_select) . '</span></th>
					<td>
						<div>
							<a
							title="' . $default_action['title'] . '"
							href="'. $default_action['url'] .'">' . $election->data->name . '</a>
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
		$input_election_create = array(
			'type' => 'submit',
			'name' => 'create_election',
			'value' => $this->_( 'Create a new election' ),
			'css_class' => 'button-primary',
		);
		
		$input_election_type = array(
			'type' => 'select',
			'name' => 'type',
			'label' => $this->_( 'Election type' ),
			'options' => array(
				'manual' => $this->_('Manual'),
				'open' => $this->_('Open'),
				'anonymous' => $this->_('Anonymous'),
			),
		);
		
		$rv .= '<h3>' . $this->_('Create a new election')  . '</h3>';
		$rv .= '<p>' . $form->make_label( $input_election_type ) . ' ' . $form->make_input( $input_election_type ) . '</p>';

		$rv .= '<p>' . $form->make_input( $input_election_create ) . '</p>';

		$rv .= $form->stop();
		
		echo $rv;
	}

	/**
		@brief		Election edit tab.
	**/
	public function admin_edit()
	{
		$form = $this->form();
		$id = $_GET['id'];
		$election = $this->filters( 'sd_mt_get_election', $id );
		$rv = '';
		
		$list_inputs = array( 'eligible_voters', 'voters_present', 'may_vote', 'have_voted' );
		$electoral_register_inputs = array( 'group_field', 'voting_order_field' );
		
		$inputs = array(
			'name' => array(
				'type' => 'text',
				'name' => 'name',
				'label' => $this->_( 'Name' ),
				'size' => 50,
				'maxlength' => 200,
			),

			// Registration
			'registration' => array(
				'name' => 'registration',
				'type' => 'select',
				'label' => $this->_( 'Registration' ),
				'description' => $this->_( 'Which registration setting to use when registering votes.' ),
				'options' => array(),
			),
			
			// Lists
			'eligible_voters' => array(
				'name' => 'eligible_voters',
				'type' => 'select',
				'label' => $this->_( 'Eligible voters' ),
				'description' => $this->_( 'The input list that specifies which participants are eligible voters. This list should contain all the eligible voters, not just those that are present.' ),
				'options' => array(),
			),
			'voters_present' => array(
				'name' => 'voters_present',
				'type' => 'select',
				'label' => $this->_( 'Voters present' ),
				'description' => $this->_( 'Which of the participant voters are present.' ),
				'options' => array(),
			),
			'may_vote' => array(
				'name' => 'may_vote',
				'type' => 'select',
				'label' => $this->_( 'May vote' ),
				'description' => $this->_( 'The output list in which all participants who are allowed to voted are placed.' ),
				'options' => array(),
			),
			'have_voted' => array(
				'name' => 'have_voted',
				'type' => 'select',
				'label' => $this->_( 'Have voted' ),
				'description' => $this->_( 'The output list in which participants who have voted are placed.' ),
				'options' => array(),
			),
			
			// May vote calculator
			'group_field' => array(
				'name' => 'group_field',
				'type' => 'select',
				'label' => $this->_( 'Group field' ),
				'description' => $this->_( 'Which participant field to group participants by.' ),
				'options' => array(),
			),
			'voting_order_field' => array(
				'name' => 'voting_order_field',
				'type' => 'select',
				'label' => $this->_( 'Voting order field' ),
				'description' => $this->_( "Which participant field specifies the participant's voting order." ),
				'options' => array(),
			),

			'update' => array(
				'type' => 'submit',
				'name' => 'update',
				'value' => $this->_( 'Update election' ),
				'css_class' => 'button-primary',
			),
		);
		
		$display_lists = false;
		if ( $election->is_open() || $election->is_anonymous() )
			$display_lists = true;
		
		$editing = $election->is_editing();		// Convenience.
		
		if ( isset( $_POST['update'] ) && $editing )
		{
			$result = $form->validate_post( $inputs, array_keys( $inputs ), $_POST );

			if ($result === true)
			{
				$election = $this->filters( 'sd_mt_get_election', $id );
				$election->data->name = $_POST['name'];
				
				// Choices
				$new_choices = array();
				foreach( $_POST['choices'] as $choice_id => $choice_data )
				{
					// Is this choice worth saving?
					$choice_name = strip_tags( trim($choice_data['name']) );
					if ( $choice_name == '' )
						continue;
					 
					// Create a new option, make it a duplicate of the existing option and then put the new data in.
					$choice = new SD_Meeting_Tool_Election_Choice();
					$choice->uuid = $choice_id; 
					$choice->name = $choice_name;
					if ( ! $election->is_manual() && ! $election->is_anonymous() )
					{
						$new_list_name = $this->_( 'Election - %s: choice %s', $election->data->name, $choice->name );
						$choice->list_id = $this->maybe_create_list( $choice_data['list_id'], $new_list_name);
					}
					$new_choices[] = $choice;
				}
				$election->data->choices = $new_choices;
				
				if ( $display_lists )
				{
					$election->data->registration = intval( $_POST['registration'] );
					
					foreach( $list_inputs as $input )
					{
						$new_list_name = $this->_( 'Election - %s: %s', $election->data->name, $inputs[ $input ]['label'] );
						$election->data->$input = $this->maybe_create_list( $_POST[ $input ], $new_list_name );
					}
					foreach( $electoral_register_inputs as $input )
						$election->data->$input = $_POST[ $input ];
					
					$election->clear_electoral_register();
					$may_vote_list = $this->filters( 'sd_mt_get_list', $election->data->may_vote );
					$this->filters( 'sd_mt_remove_list_participants', $may_vote_list );
				}
				$this->filters( 'sd_mt_update_election', $election );
				
				$this->message_( 'The election has been updated!');
				SD_Meeting_Tool::reload_message();
			}
			else
			{
				$this->error( implode('<br />', $result) );
			}
		}
		
		if ( $display_lists )
		{
			// Lists
			$all_lists = $this->filters( 'sd_mt_get_all_lists', array() );
			$lists = array();
			foreach( $all_lists as $list )
				$lists[ $list->id ] = $list->data->name;

			// Registration
			$all_registrations = $this->filters( 'sd_mt_get_all_registrations', array() );
			$registrations = array();
			foreach( $all_registrations as $registration )
				$inputs[ 'registration' ][ 'options' ][ $registration->id ] = $registration->data->name;
				
			// Fields
			$participant_fields = $this->filters( 'sd_mt_get_participant_fields', array() );
			$fields = array( ''  => $this->_('None') );
			foreach( $participant_fields as $field )
				$fields[ $field->name ] = $field->description;
		}
		foreach( $list_inputs as $list )
		{
			if ( $display_lists )
			{
				$inputs[ $list ]['options'] = array( 'create' => $this->_( 'Create a new list') ) + $lists;
				$inputs[ $list ]['value'] = $election->data->$list;
			}
			else
				unset( $inputs[ $list ] );
		}

		$election = $this->filters( 'sd_mt_get_election', $id );
		
		$inputs['name']['value'] = $election->data->name;
		$inputs['type']['value'] = $election->data->type;
		
		$rv .= '
			' . $form->start() . '
			
			' . $this->display_form_table( array( $inputs['name'] ) ) . '
		';
		
		// Choices
		$rv .= '<h3>' . $this->_('Options') . '</h3>';
		$choices = $election->data->choices;
		if ( $election->is_editing() )
		{
			if ( count($choices) %5 == 0 )
			{
				$choice = new SD_Meeting_Tool_Election_Choice();
				$choice->name = $this->_('Abstain');
				$choices[] = $choice;
			}
			$choice_count = ceil( count($choices) / 5) * 5;
		}
		else
			$choice_count = count( $choices );
		
		$choice_options = '';
		$temp_choices = $choices;
		for( $counter=0; $counter<$choice_count; $counter++ )
		{
			$choice = array_shift($temp_choices);
			if ( $choice === null )
				$choice = new SD_Meeting_Tool_Election_Choice();
			$nameprefix = '[choices][' . $choice->uuid . ']';
			$choice_input = array(
				'type' => 'text',
				'name' => 'name',
				'nameprefix' => $nameprefix,
				'label' => $this->_( 'Choice name' ),
				'description' => $this->_( 'Name / description of the choice.' ),
				'value' => $choice->name,
				'size' => 25,
				'maxlength' => 255,
				'readonly' => !$election->is_editing(),
			);
			$choice_options .= '
				<tr>
					<td>
						<div>
							' . $form->make_label( $choice_input ) . ' ' . $form->make_input( $choice_input ) . '
						</div>
					</td>
				</tr>
			';
		}
		
		if ( $election->is_manual() || $election->is_anonymous() )
		{
			$rv .= '
				<table class="widefat">
					<thead>
						<tr>
							<th>' . $this->_('Choice name') . '</th>
						</tr>
					</thead>
					<tbody>
						' . $choice_options . '
					</tbody>
				</table>
			';
		}
		else
		{
			$t_body = '';
			for( $counter=0; $counter<$choice_count; $counter++ )
			{
				$choice = array_shift($choices);
				if ( $choice === null )
				{
					$choice = new SD_Meeting_Tool_Election_Choice();
					$choice->list_id == 'create';
				}
				$nameprefix = '[choices][' . $choice->uuid . ']';
				$choice_name = array(
					'type' => 'text',
					'name' => 'name',
					'nameprefix' => $nameprefix,
					'label' => $this->_( 'Choice name' ),
					'description' => $this->_( 'Name / description of the choice.' ),
					'value' => $choice->name,
					'size' => 25,
					'maxlength' => 255,
				);
				$choice_list = array(
					'type' => 'select',
					'name' => 'list_id',
					'nameprefix' => $nameprefix,
					'label' => $this->_( 'List to use as source' ),
					'description' => $this->_( 'Participants that have voted for this choice are put into the above list.' ),
					'value' => $choice->list_id,
					'options' => array('create' => $this->_('Create a new list') ) + $lists ,
				);
				$t_body .= '
					<tr>
						<td>
							<div>
								' . $form->make_label( $choice_name ) . ' ' . $form->make_input( $choice_name ) . '
							</div>
						</td>
						<td>
							<div>
								' . $form->make_label( $choice_list ) . ' ' . $form->make_input( $choice_list ) . '
							</div>
							<div>
								' . $form->make_description( $choice_list ) . '
							</div>
						</td>
					</tr>
				';
			}
			$rv .= '
				<table class="widefat">
					<thead>
						<tr>
							<th>' . $this->_('Choice name') . '</th>
							<th>' . $this->_('Choice list') . '</th>
						</tr>
					</thead>
					<tbody>
						'.$t_body.'
					</tbody>
				</table>
			';
		}
		
		if ( $display_lists )
		{
			// Lists
			$inputs_to_display = array();
			foreach( $list_inputs as $list )
			{
				$inputs[$list]['value'] = intval( $election->data->$list );
				$inputs_to_display[] = $inputs[$list];
			}
				
			$rv .= '<h3>' . $this->_('Lists') . '</h3>
				' . $this->display_form_table( $inputs_to_display ) . '
			';

			// Registration
			$inputs['registration']['value'] = $election->data->registration;
			$inputs_to_display = array(
				$inputs['registration']
			);
			$rv .= '<h3>' . $this->_('Registration') . '</h3>
				' . $this->display_form_table( $inputs_to_display ) . '
			';
			
			// Fields
			$inputs_to_display = array();
			foreach( $electoral_register_inputs as $input )
			{
				$inputs[ $input ][ 'options' ] = $fields;
				$inputs[ $input ][ 'value' ] = $election->data->$input;
				$inputs_to_display[] = $inputs[ $input ];
			}

			$rv .= '<h3>' . $this->_('Electoral register') . '</h3>
				' . $this->display_form_table( $inputs_to_display ) . '
			';
		}
		
		if ( $editing )
			$rv .= $this->display_form_table( array( $inputs['update'] ) );

		$rv .= $form->stop();
		
		echo $rv;
	}
	
	/**
		@brief		Electoral register tab.
	**/
	public function admin_electoral_register()
	{
		$form = $this->form();
		$id = $_GET['id'];
		$election = $this->filters( 'sd_mt_get_election', $id );
		$rv = '';
		
		if ( isset( $_POST['generate_electoral_register'] ) && ! $election->is_finished() )
		{
			$rv .= $this->generate_electoral_register( $election );
		}
		
		$inputs = array(
			'generate_electoral_register' => array(
				'name' => 'generate_electoral_register',
				'type' => 'submit',
				'value' => $this->_( 'Generate the electoral register' ),
				'css_class' => 'button-primary',
			),
		);
		
		if ( $election->is_electoral_register_calculated() )
		{
			$rv .= '<p>' . $this->_( 'The electoral register was generated at %s.', date('Y-m-d H:i:s', $election->data->electoral_register_calculated ) ) . '</p>';
			$inputs[ 'generate_electoral_register' ][ 'value' ] = $this->_( 'Regenerate the electoral register' );
		}
		
		if ( ! $election->is_finished() )
		{
			$rv .= $form->start();
			
			$rv .= $this->display_form_table( $inputs );		
			
			$rv .= $form->stop();
		}
		
		// Display the register
		$rv .= $this->display_electoral_register( $election );
		
		echo $rv;
	}
	
	/**
		@brief		Queries the voting status of a participant.
	**/
	public function admin_query_register()
	{
		$rv = '';
		$form = $this->form();
		
		$id = $_GET['id'];
		$election = $this->filters( 'sd_mt_get_election', $id );
		
		$eligible_voters_id = $election->data->eligible_voters;
		$eligible_voters = $this->filters( 'sd_mt_get_list', $eligible_voters_id );
		$eligible_voters = $this->filters( 'sd_mt_list_participants', $eligible_voters );
		$display_format = $eligible_voters->get_display_format();
		
		$participants_autocomplete = array();
		$participant_options = array();

		foreach( $eligible_voters->participants as $participant )
		{
			$id = $participant->id;
			$name = $this->filters( 'sd_mt_display_participant', $participant, $display_format );
			$participant_options[ $id ] = $name;
			$participants_autocomplete[ $id ] = $id . ' ' . $name;
		}
		
		$inputs = array(
			'participant' => array(
				'css_class' => 'participant',
				'label' => $this->_( 'Participant to query' ),
				'name' => 'participant',
				'size' => 40,
				'type' => 'text',
			),
			'participants' => array(
				'label' => $this->_( 'Participant to query' ),
				'name' => 'participants',
				'options' => $participant_options,
				'type' => 'select',
			),
			'query_participant' => array(
				'css_class' => 'button-primary',
				'name' => 'query_participant',
				'type' => 'submit',
				'value' => $this->_( 'Query the participant' ),
			),
		);
		
		foreach( $inputs as $index => $input )
			$form->use_post_value( $inputs[ $index ], $_POST );
		
		wp_print_scripts('jquery-ui-autocomplete');
		
		$participants_autocomplete = '"' . implode( '", "', $participants_autocomplete ) . '"';
		
		if ( isset( $_POST[ 'query_participant' ] ) )
		{
			$id = $_POST[ 'participants' ];
			if ( $_POST[ 'participant' ] != '' )
			{
				$int = preg_replace( '/ .*/', '', $_POST[ 'participant' ] );
				$int = intval( $int );
				if ( isset( $eligible_voters->participants[ $int ] ) )
					$id = $int;
			}
			$rv .= $this->query_participant( $election, $id );
		}
		
		$rv .= $this->p_( 'Select the participant that is to join / leave the electoral register. This can be done by typing in his ID or selecting him from the select box. Press the button and any changes to the electoral register will be shown as a preview.' );
		
		$rv .= $form->start();
		$rv .= $this->display_form_table( $inputs );
		$rv .= $form->stop();
		
		$rv .= '
			<script type="text/javascript" src="'. $this->paths['url'] . '/js/sd_meeting_tool_elections.js' .'"></script>
			<script type="text/javascript">
				jQuery(function( $ )
				{
					$( "input.participant" ).autocomplete({
						"source" : [' . $participants_autocomplete .']
					});
				});
			</script>
		';
		
		echo $rv;
	}
	
	/**
		@brief		Register votes tab.
	**/
	public function admin_register()
	{
		$id = $_GET['id'];
		$rv = '';
		$form = $this->form();
		$election = $this->filters( 'sd_mt_get_election', $id );
		
		if ( isset( $_POST['finish_registering'] ) && isset( $_POST['sure'] ) )
		{
			$election->finish();
			$this->filters( 'sd_mt_update_election', $election );
		}
		
		if ( $election->is_finished() )
		{
			$rv .= '
				<p>
					' . $this->_( 'The election is over and no more votes can be registered.' ) . '
				</p>
			';
			echo $rv;
			return;
		}
		
		if ( isset( $_POST['begin_registering'] ) )
		{
			$election->begin_registration();
			$this->filters( 'sd_mt_update_election', $election );
		}
		
		// Election must be marked as registering before we can register votes. 
		if ( ! $election->is_registering() )
		{
			$input_submit = array(
				'type' => 'submit',
				'name' => 'begin_registering',
				'value' => $this->_( 'Begin registering votes' ),
				'css_class' => 'button-primary',
			);
			
			$rv .= $form->start();
			$rv .= '
				<p>
					'. $this->_('Votes can only be registered when there are no further changes to be made to the election. The button below will prevent further changes to the election and allow votes to be registered.') . '
				</p>
				<p>
					'. $form->make_input( $input_submit ) . '
				</p>
			';
			$rv .= $form->stop();
		}
		else
		{
			switch( $election->data->type )
			{
				case 'manual':
					$rv .= $this->register_manual_votes( $election );
					$rv .= '<h3>' . $this->_( 'Finish registering votes' ) . '</h3>';
					$rv .= $this->register_finish( $election );
					break;
				case 'anonymous':
					$rv .= $this->register_votes( $election );
					$rv .= $this->register_manual_votes( $election );
					$rv .= '<h3>' . $this->_( 'Finish registering votes' ) . '</h3>';
					$rv .= $this->register_finish( $election );
					break;
				case 'open':
					$rv .= $this->register_votes( $election );
					$rv .= $this->register_voter_options( $election );
					$rv .= '<h3>' . $this->_( 'Finish registering votes' ) . '</h3>';
					$rv .= $this->register_finish( $election );
					break;
			}
		}
		echo $rv;
	}

	/**
		@brief		Display results tab.
	**/
	public function admin_results()
	{
		$id = $_GET['id'];
		$rv = '';
		$form = $this->form();
		$election = $this->filters( 'sd_mt_get_election', $id );

		// Automatically create a new page with a shortcode. Note that we need $election beforehand.
		if ( isset( $_POST['create_shortcode_page'] ) )
		{
			$page = key( $_POST['create_shortcode_page'] );
			$post_content = '';
			switch ( $page )
			{
				case 'display_election_graph':
					$post_title = $this->_( 'Election results graph' );
					$post_content = '[display_election_graph id="' . $election->id . '"]';
					break;
				case 'display_election_statistics':
					$post_title = $this->_( 'Election statistics' );
					$post_content = '[display_election_statistics id="' . $election->id . '"]';
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
				$this->message_( 'A new page has been created! You can now %sedit the page%s or %sview the page%s.',
					'<a href="' . add_query_arg( array( 'post' => $page_id), 'post.php?action=edit' ) . '">',
					'</a>',
					'<a href="' . add_query_arg( array( 'p' => $page_id), get_bloginfo('url') ) . '">',
					'</a>'
				);
			}
		}
		
		$rv .= $this->display_election_graph( $election );
		
		if ( ! $election->is_manual() )
		{
			$rv .= $this->display_election_statistics( $election );
			$rv .= $this->display_election_voters( $election );
			$rv .= $this->display_election_nonvoters( $election );
		}
				
		// Shortcodes
		$buttons = array(
			'display_election_graph' => array(
				'name' => 'display_election_graph',
				'type' => 'submit',
				'value' => $this->_( 'Create a page with this shortcode' ),
				'title' => $this->_( 'Creates a new page with this shortcode as the only content.' ),
				'nameprefix' => '[create_shortcode_page]',
				'css_class' => 'button-secondary',
			),
			'display_election_statistics' => array(
				'name' => 'display_election_statistics',
				'type' => 'submit',
				'value' => $this->_( 'Create a page with this shortcode' ),
				'title' => $this->_( 'Creates a new page with this shortcode as the only content.' ),
				'nameprefix' => '[create_shortcode_page]',
				'css_class' => 'button-secondary',
			),
		);
		
		$t_body = '
			<tr>
				<td>[display_election_graph id="' . $election->id . '"]</td>
				<td>
					<p>' . $this->_( 'Shows the graph for this election.' ). '</p>
				</td>
				<td>' . $form->make_input( $buttons['display_election_graph'] ) . '</td>
			</tr>
		';
		
		if ( ! $election->is_manual() )
		{
			$t_body .= '
				<tr>
					<td>[display_election_statistics id="' . $election->id . '"]</td>
					<td>
						<p>' . $this->_( 'Shows the statistics for this election..' ). '</p>
					</td>
					<td>' . $form->make_input( $buttons['display_election_statistics'] ) . '</td>
				</tr>
			';
		}
		
		$rv .= '
			
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
					' . $t_body . '
				<tbody>
			</table>

			' . $form->stop() . '
		';
		
		echo $rv;
	}
		
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Ajax
	// --------------------------------------------------------------------------------------------

	/**
		@brief		Ajax functions.
	**/
	public function ajax_sd_mt_elections()
	{
		if ( ! SD_Meeting_Tool::check_admin_referrer( 'ajax_sd_mt_elections' ) )
			die();
		
		$election = $this->filters( 'sd_mt_get_election', $_POST['election_id'] );
		if ( $election === false )
			die();
		
		switch( $_POST['type'] )
		{
			case 'fetch_have_voted':
				$form = $this->form();
				$list = $this->filters( 'sd_mt_get_list', $election->data->have_voted );
				$list = $this->filters( 'sd_mt_list_participants', $list );
				$have_voted = $list->participants;

				$display_format = $this->filters( 'sd_mt_get_display_format', $list->data->display_format_id );
				
				$t_headers = '';
				$choices = $election->data->choices;		// Conv
				foreach( $choices as $index => $choice )
				{
					$list = $this->filters( 'sd_mt_get_list', $choice->list_id );
					$list = $this->filters( 'sd_mt_list_participants', $list );
					$choices[ $index ]->participants = $list->participants;
					$t_headers .= '<th class="option">' . $choice->name . '</th>'; 
				}

				$t_body = '';
				foreach( $have_voted as $participant )
				{
					$t_body .= '<tr><td>' . $this->filters( 'sd_mt_display_participant', $participant, $display_format) .'</td>';
					$input_selected = array(
						'name' => $participant->id,
						'nameprefix' => '[participants]',
						'type' => 'checkbox',
					);
					foreach( $choices as $index => $choice )
					{
						$input_selected['checked'] = isset( $choice->participants[ $participant->id ] );
						$input_selected['title'] = $choice->name;
						$t_body .= '<td
							class="option"
							participant_id="' . $participant->id . '"
							option_id="' . $choice->uuid . '" >' . $form->make_input( $input_selected ) . '</td>';
					}
					$t_body .= '</tr>';
				}

				$text = '
					<table class="widefat">
						<thead>
							<tr>
								<th>' . $this->_('Name') . '</th>
								' . $t_headers . '
							</tr>
						</thead>
						<tbody>
							'.$t_body.'
						</tbody>
					</table>
				';

				$rv = array(
					'hash' => $this->hash( $text ),
					'text' => $text,
				);
				
				if ( $rv['hash'] == $_POST['have_voted_list_hash'] )
					$rv['text'] = '';
				echo json_encode( $rv );
				break;
			case 'set_vote':
				$rv = array();
				$participant = $this->filters( 'sd_mt_get_participant', $_POST['participant_id'] );

				// Find this option.
				foreach( $election->data->choices as $choice )
				{
					$list = $this->filters( 'sd_mt_get_list', $choice->list_id );
					$list = $this->filters( 'sd_mt_list_participants', $list );
					if ( $choice->uuid == $_POST['option_id'] )
					{
						if ( isset( $list->participants[ $participant->id ] ) )
							$this->filters( 'sd_mt_remove_list_participant', $list, $participant );
						else
							$this->filters( 'sd_mt_add_list_participant', $list, $participant );
					}
					else
						$this->filters( 'sd_mt_remove_list_participant', $list, $participant );
				}
				$rv = array( 'result' => 'ok' );

				$election = $this->maybe_finish_election( $election );				
				if ( $election->is_finished() )
				{
					$rv = array( 'result' => 'reload' );
				} 
				echo json_encode( $rv );
				break;
		}
		die();
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------

	/**
		@brief		Delete an election.
		
		@param		SD_Meeting_Tool_Election	$SD_Meeting_Tool_Election		Election to delete.
	**/
	public function sd_mt_delete_election( $SD_Meeting_Tool_Election )
	{
		global $blog_id;
		$query = "DELETE FROM `".$this->wpdb->base_prefix."sd_mt_elections`
			WHERE `id` = '" . $SD_Meeting_Tool_Election->id . "'
			AND `blog_id` = '$blog_id'
		";
		$this->query( $query );
	}
	
	/**
		@brief		List all elections.
		
		@return		An array of {SD_Meeting_Tool_Election}s.
	**/
	public function sd_mt_get_all_elections()
	{
		global $blog_id;
		$query = "SELECT id FROM `".$this->wpdb->base_prefix."sd_mt_elections` WHERE `blog_id` = '$blog_id'";
		$results = $this->query( $query );
		$rv = array();
		
		foreach( $results as $result )
			$rv[ $result['id'] ] = $this->sd_mt_get_election( $result['id'] );

		return SD_Meeting_Tool::sort_data_array( $rv, 'name' );
	}
	
	/**
		@brief		Retrieves an election.
		
		@param		int		$id		The ID of the election to retrieve.
		@return		The SD_Meeting_Tool_Election object, or FALSE if no election with this ID was found.
	**/
	public function sd_mt_get_election( $id )
	{
		global $blog_id;
		$query = "SELECT * FROM `".$this->wpdb->base_prefix."sd_mt_elections` WHERE `id` = '$id' AND `blog_id` = '$blog_id'";
		$result = $this->query_single( $query );
		if ( $result === false )
			return false;

		return $this->election_sql_to_object( $result );
	}
	
	/**
		@brief		Update an election.
		
		@param		SD_Meeting_Tool_Election	$SD_Meeting_Tool_Election		The election to update.
	**/
	public function sd_mt_update_election( $SD_Meeting_Tool_Election )
	{
		global $blog_id;
		
		$election = $SD_Meeting_Tool_Election;
		$data = $this->sql_encode( $SD_Meeting_Tool_Election->data );
		
		if ( $election->id === null )
		{
			$query = "INSERT INTO  `".$this->wpdb->base_prefix."sd_mt_elections`
				(`blog_id`, `data`)
				VALUES
				('". $blog_id ."', '" . $data . "')
			";
			$election->id = $this->query_insert_id( $query );
		}
		else
		{
			$query = "UPDATE `".$this->wpdb->base_prefix."sd_mt_elections`
				SET
				`data` = '" . $data. "'
				WHERE `id` = '" . $SD_Meeting_Tool_Election->id . "'
				AND `blog_id` = '$blog_id'
			";
			$this->query( $query );
		}
		
		return $election;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------
	
	/**
		Convert a election row from SQL to an SD_Meeting_Tool_Election.
		
		@param		array	$sql		Row from the database as an array.
		@return		A complete SD_Meeting_Tool_Election object.
	**/ 
	private function election_sql_to_object( $sql )
	{
		$election = new SD_Meeting_Tool_Election();
		$election->id = $sql['id'];
		$election->data = (object) array_merge( (array)$election->data, (array)$this->sql_decode( $sql['data'] ) );
		return $election;
	}
	
	/**
		@brief		Calculate the electoral register for an election.
		
		The SD_Meeting_Tool_Election must include the following variables:
		
		- eligible_voters	A SD_Meeting_Tool_List of the eligible voters (including all participants).
		- voters_present	A SD_Meeting_Tool_List of the voters present (including all participants).
		
		The returned SD_Meeting_Tool_Election will have the following variables:
		
		- may_vote			An array of participant IDs that are allowed to vote in this election.
		
		@param		SD_Meeting_Tool_Election $SD_Meeting_Tool_Election		The election to use.		
		@return		The modified, unsaved SD_Meeting_Tool_Election.
	**/
	public function calculate_electoral_register( $SD_Meeting_Tool_Election )
	{
		$election  = $SD_Meeting_Tool_Election;			// Convenience.
		
		// Check for existence of all lists
		foreach( $election->lists() as $list_id )
		{
			$list = $this->filters( 'sd_mt_get_list', $list_id );
			if ( $list == false )
				throw new Exception( $this->_( 'List %s does not exist anymore!', $list_id ) );
		}
		
		// Lists are good. How about the fields?
		$group = $election->data->group_field;		// Convenience.
		$field = $this->filters( 'sd_mt_get_participant_field', $group );
		if ( $field === false )
			throw new Exception( $this->_( 'The participant field used for grouping does not exist anymore!' ) );
			
		$voting_order = $election->data->voting_order_field;		// Convenience
		$field = $this->filters( 'sd_mt_get_participant_field', $voting_order );
		if ( $field === false )
			throw new Exception( $this->_( 'The participant field used for the voting order does not exist anymore!' ) );
		
		if ( ! is_object( $election->eligible_voters ) )
			throw new Exception( 'No list of eligible voters!' );
		
		if ( ! is_object( $election->voters_present ) )
			throw new Exception( 'No list of voters present!' );
		
		// Excellent! Everything is ready.
		
		$eligible_voters = $election->eligible_voters;
		$voters_present = $election->voters_present;
		
		// Calculate the max votes of all participants and put them into groups and such.
		$max_votes = array();
		foreach( $eligible_voters->participants as $participant )
		{
			$order = $participant->$voting_order;
			if ( $order != 1 )
				continue;
			
			// Increase the max votes counter for this group.
			$participant_group = $participant->$group;
			if ( !isset( $max_votes[ $participant_group ] ) )
				$max_votes[ $participant_group ] = 0;
			
			$max_votes[ $participant_group ]++;
		}
		ksort( $max_votes );

		$election->clear_electoral_register();
		$election->data->groups_incomplete = $max_votes;		// Keep track of how many voters are missing in each group. Just copy the max votes array to incomplete and decrease it later.
		
		$voter_orders = array();
		foreach( $voters_present->participants as $participant )
		{
			$order = intval( $participant->$voting_order );
			if ( $order < 1 )
				continue;

			// And put the p into the correct order for his group.
			$participant_group = $participant->$group;
			if ( !isset( $voter_orders[ $participant_group ] ) )
				$voter_orders[ $participant_group ] = array();
			
			if ( ! isset( $voter_orders[ $participant_group ][ $order ] ) )
				$voter_orders[ $participant_group ][ $order ] = array();
			
			$voter_orders[ $participant_group ][ $order ][] = $participant->id;
		}
		
		// Sort the orders of the voters so that order 1 is at the top.
		foreach( $voter_orders as $group => $orders )
			ksort( $voter_orders[ $group ] );
		
		// And now we have a voter pool!
		// Empty the pool!
		$may_vote = array();
		foreach( $max_votes as $group => $votes )
		{
			for ( $counter = 0; $counter < $votes; $counter ++ )
			{
				if ( ! isset( $voter_orders[ $group ] ) )
					continue;

				// Find the first best voter of this group's pool.
				foreach( $voter_orders[ $group ] as $order => $voters )
				{
					$found = false;
					foreach( $voters as $index => $voter )
					{
						// We have to unset it from the source, not the array copies we have here.
						unset( $voter_orders[ $group ][$order][$index] );
						$may_vote[] = $voter;
						$found = true;
						$election->data->groups_incomplete[ $group ]--;
						break;
					}
					if ( $found )
						break;
				}
			}
		}
		$election->may_vote = $may_vote;
		
		// Clear the incomplete groups if necessary.
		foreach( $election->data->groups_incomplete as $group => $missing )
			if ( $missing < 1 )
			{
				$election->data->groups_complete[ $group ] = $group;
				unset( $election->data->groups_incomplete[ $group ] );
			}
		ksort( $election->data->groups_complete );
		ksort( $election->data->groups_incomplete );
		
		
		$election->electoral_register_calculated( $this->time() );
		
		return $election;		
	}
	
	/**
		@brief		Display an electoral register.
		
		@param		SD_Meeting_Tool_Election	$SD_Meeting_Tool_Election		The election from which to display the electoral register.
		@return		A HTML string containing all the info about the electoral register of an election.
	**/
	public function display_electoral_register( $SD_Meeting_Tool_Election )
	{
		$election  = $SD_Meeting_Tool_Election;			// Convenience.
		$rv = '';
		
		// Get the "may vote" list.
		$may_voters = $this->filters( 'sd_mt_get_list', $election->data->may_vote );
		if ( $may_voters === false )
		{
			$this->error( $this->_('The may vote-list does not exist!' ) );
			return;
		}
		$may_voters = $this->filters( 'sd_mt_list_participants', $may_voters );
		
		if ( count( $election->data->groups_incomplete ) > 0 )
		{
			$t_body = array();
			// Display the list of incomplete groups?
			foreach( $election->data->groups_incomplete as $group => $missing )
				$t_body[ $group ] .= '<tr><td>' . $group . '</td><td>' . $missing . '</td></tr>';
			$t_body = implode( '', $t_body );
			
			if ( $t_body != '' )
			{
				$rv .= '
					<p>
						' . $this->_( 'There were not enough voters present to fill the vote quotas for the follow groups:' )  . '
					</p>
					<table class="widefat">
						<thead>
							<tr>
								<th>' . $this->_('Group') . '</th>
								<th>' . $this->_('Voters missing') . '</th>
							</tr>
						</thead>
						<tbody>
							'.$t_body.'
						</tbody>
					</table>
				';
			}
		}
		
		if ( count( $election->data->groups_complete ) > 0 )
		{
			$rv .= wpautop( $this->_( 'The following groups have enough voters present:' ) );
			$rv .= '<ul>';
			$rv .= '<li>' . implode( '</li><li>', $election->data->groups_complete ) . '</li>';
			$rv .= '</ul>';
		}
		
		$may_voters = $this->filters( 'sd_mt_get_list_participants', $may_voters );
		$display_format = $this->filters( 'sd_mt_get_display_format', $may_voters->display_format_id() );
		
		$rv .= '
			<p>
				' . $this->_( 'The following participants are allowed to vote:' ) . '
			</p>
			<ul>
		';
		foreach( $may_voters->participants as $participant )
		{
			$participant = $this->filters( 'sd_mt_get_participant', $participant->id );
			$display_name = $this->filters( 'sd_mt_display_participant', $participant, $display_format );
			$rv .= '<li>' . $display_name . '</li>';
		}
		$rv .= '</ul>';
		return $rv;
	}
	
	/**
		@brief		Generate an electoral register for an election.
		
		Will empty and refill the may_vote list.
		
		@param		$SD_Meeting_Tool_Election		SD_Meeting_Tool_Election for which to generate the electoral register.
		@return		An HTML message.
	**/
	public function generate_electoral_register( $SD_Meeting_Tool_Election )
	{
		$rv = '';
		$election  = $SD_Meeting_Tool_Election;			// Convenience.
		
		try
		{
			$list = $this->filters( 'sd_mt_get_list', $election->data->eligible_voters );
			$election->eligible_voters = $this->filters( 'sd_mt_list_participants', $list );
			$list = $this->filters( 'sd_mt_get_list', $election->data->voters_present );
			$election->voters_present = $this->filters( 'sd_mt_list_participants', $list );
	
			$election = $this->calculate_electoral_register( $election );
			
			// We're done! Put the voters into the may vote list.
			$may_vote_list = $this->filters( 'sd_mt_get_list', $election->data->may_vote );
			$this->filters( 'sd_mt_remove_list_participants', $may_vote_list );
			$this->filters( 'sd_mt_add_list_participants', $may_vote_list, $election->may_vote );
			
			// And update the election
			$this->filters( 'sd_mt_update_election', $election );
			
			$rv .= $this->message( 'The electoral register has been calculated!' ); 
		}
		catch ( Exception $e )
		{
			$rv .= $this->error( $e->getMessage() );
		}
		
		return $rv;
	}
	
	/**
		@brief		Queries the voting status of a participant.
		
		Returns what would happen if the participant were to join / leave the electoral register.
		
		@param		SD_Meeting_Tool_Election	$SD_Meeting_Tool_Election		The election on which to base the query.
		@param		int							$participant_id					ID of participant to query.
		@return		A HTML string containing a message() or error().
	**/
	public function query_participant( $SD_Meeting_Tool_Election, $participant_id )
	{
		$election = $SD_Meeting_Tool_Election;
		
		try
		{
			$list = $this->filters( 'sd_mt_get_list', $election->data->eligible_voters );
			$election->eligible_voters = $this->filters( 'sd_mt_list_participants', $list );
			$list = $this->filters( 'sd_mt_get_list', $election->data->voters_present );
			$election->voters_present = $this->filters( 'sd_mt_list_participants', $list );
			
			// Get the may vote list, so that we can use its display format later.
			$may_vote_list = $this->filters( 'sd_mt_get_list', $election->data->may_vote );
			$may_vote_display_format = $may_vote_list->get_display_format();
	
			// What do we do with this participant?
			$participant_present = isset( $election->voters_present->participants[ $participant_id ] );
			$participant = $this->filters( 'sd_mt_get_participant', $participant_id );
			$participant_name = $this->filters( 'sd_mt_display_participant', $participant, $may_vote_display_format );
			
			$election = $this->calculate_electoral_register( $election );

			if ( $participant_present )
				unset( $election->voters_present->participants[ $participant_id ] );
			else
				$election->voters_present->participants[ $participant_id ] = $participant;

			$may_vote_before = $election->may_vote;
			$election = $this->calculate_electoral_register( $election );
			$may_vote_after = $election->may_vote;

			// Find the changes in the electoral registers.
			$changes = array();
			
			// Which people have disappeared from the register?
			$changes[ 'old' ] = array_diff( $may_vote_before, $may_vote_after );
			// Which new people have appeared in the new register?
			$changes[ 'new' ] = array_diff( $may_vote_after, $may_vote_before );
			
			if ( $participant_present )
				$rv .= $this->p_( 'If %s was to leave the electoral register:', '<em>' . $participant_name . '</em>' );
			else
				$rv .= $this->p_( 'If %s was to join the electoral register:', '<em>' . $participant_name . '</em>' );
			
			if ( count( $changes[ 'old' ] ) + count( $changes[ 'new' ] ) < 1 )
			{
				$rv .= $this->p_( 'The participant does not affect the electoral register.' );
			}
			else
			{
				$strings = array();
				foreach( $changes[ 'new' ] as $change )
				{
					$participant = $this->filters( 'sd_mt_get_participant', $change );
					$name = $this->filters( 'sd_mt_display_participant', $participant, $may_vote_display_format );
					$strings[] = $this->p_( '%s would be allowed to vote.', '<em>' . $name . '</em>' );
				}
				foreach( $changes[ 'old' ] as $change )
				{
					$participant = $this->filters( 'sd_mt_get_participant', $change );
					$name = $this->filters( 'sd_mt_display_participant', $participant, $may_vote_display_format );
					$strings[] = $this->p_( '%s would no longer be allowed to vote.', '<em>' . $name . '</em>' );
				}
				$rv .= '<ul><li>' . implode( '</li><li>', $strings ) . '</li></ul>';
			}
			$rv = $this->message( $rv );
		}
		catch ( Exception $e )
		{
			$rv .= $this->error( $e->getMessage() );
		}
		return $rv;
	}
	
	/**
		@brief		Register votes in a manual election.
		
		@param		SD_Meeting_Tool_Election	$election		Election in which to register votes.
	**/
	public function register_manual_votes( $election )
	{
		$form = $this->form();
		$rv = '';
		
		if ( isset( $_POST['update_vote_count'] ) )
		{
			foreach( $election->data->choices as $choice )
				if ( isset( $_POST[ $choice->uuid ] ) )
					$choice->count = intval( $_POST[ $choice->uuid ] );
			$this->filters( 'sd_mt_update_election', $election );
			$this->message_( 'The vote count has been updated!');
		}
		
		if ( $election->is_anonymous() )
		{
			// Check that the admin hasn't inputted MORE votes than there actually are.
			$count = 0;
			foreach( $election->data->choices as $choice )
				$count += $choice->count;
			$have_voted = $election->data->have_voted;
			$have_voted = $this->filters( 'sd_mt_get_list', $have_voted );
			$have_voted = $this->filters( 'sd_mt_list_participants', $have_voted );
			if ( $count > count( $have_voted->participants ) )
				$this->error( $this->_( '%s participants have physically voted but there are %s votes registered by the functionary.', count( $have_voted->participants ), $count ) );
		}
		
		$inputs = array();
		foreach( $election->data->choices as $choice )
		{
			$input = array(
				'name' => $choice->uuid,
				'type' => 'text',
				'label' => $choice->name,
				'value' => $choice->count,
				'size' => 3,
				'maxlength' => 10,
			);
			$inputs[] = $input;
		}
		$inputs[] = array(
			'name' => 'update_vote_count',
			'type' => 'submit',
			'value' => $this->_( 'Update the vote count' ),
			'css_class' => 'button-primary',
		);
		
		$rv .= '
			'.$form->start().'
			' . $this->display_form_table( $inputs ). '
			'.$form->stop().'
		';

		return $rv;
	}
	
	/**
		@brief		Register votes in an election.
		
		@param		SD_Meeting_Tool_Election	$election		Election in which to register votes.
	**/
	public function register_votes( $election )
	{
		$registration = $this->filters( 'sd_mt_get_registration', $election->data->registration );
		$this->filters( 'sd_mt_process_registration', $registration );
		$rv = $this->filters( 'sd_mt_display_registration', $registration );

		return $rv;
	}
	
	/**
		@brief		Shows the register UI of an election.
		
		@param		SD_Meeting_Tool_Election	$election		Election in which to register votes.
	**/
	public function register_voter_options( $election )
	{
		$rv = '';
		
		$form = $this->form();
		$rv .= '
			<div id="have_voted">
			</div>
			<p>
				' . $this->_( 'To prevent the above table from being updated, keep the mouse cursor above it.' ) . '
			</p>
			'.$form->start().'
			<script type="text/javascript" src="'. $this->paths['url'] . '/js/sd_meeting_tool_elections.js' .'"></script>
			<script type="text/javascript">
				jQuery(document).ready(function($){ sd_meeting_tool_elections.init(
					// Ajaxoptions
					{
						"ajaxnonce" : "' . wp_create_nonce( 'ajax_sd_mt_elections' ) . '",
						"action" : "ajax_sd_mt_elections", 
						"ajaxurl" : "'. admin_url('admin-ajax.php') . '",
						"election_id" : "'. $election->id . '",
					},
					// Settings
					{
						list_participants : {
							timeout : 5000,
						}
					}
				); });
			</script>
			'.$form->stop().'
		';

		return $rv;
	}
	
	/**
		@brief		Display a query box allowing the user to finish the election.
		
		@param		SD_Meeting_Tool_Election	$election		Election to finish.
	**/
	function register_finish( $election )
	{
		$rv = '';
		$form = $this->form();
		
		$inputs = array(
			'sure' => array(
				'name' => 'sure',
				'type' => 'checkbox',
				'label' => $this->_( 'I am sure I want to finish registering votes' ),
				'checked' => false,
			),
			'finish_registering' => array(
				'name' => 'finish_registering',
				'type' => 'submit',
				'value' => $this->_( 'Finish registering votes' ),
				'css_class' => 'button-primary',
			),
		);		
		
		$rv .= '<p>' . $form->start() . $this->display_form_table( $inputs ) . $form->stop() . '</p>';
		
		return $rv;
	}
	
	/**
		@brief		Decide, from the post value, whether to create a list.
		
		@param		string		$post_value			Either 'create', to create a list or just a number.
		@param		string		$new_list_name		The name of the new list, if it is to be created.
		@return		Either the newly-created list's ID, or the integerized value from the post.
	**/
	public function maybe_create_list( $post_value, $new_list_name )
	{
		if ( $post_value == 'create' )
		{
			$new_list = new SD_Meeting_Tool_List();
			$new_list->data->name = $new_list_name;
			$new_list = $this->filters( 'sd_mt_update_list', $new_list );
			$post_value = $new_list->id;
		}
		return intval( $post_value );	
	}
	
	/**
		@brief		Decide whether to finish this election.
		
		It's time to finish when all eligible voters have voted.
		
		@param		SD_Meeting_Tool_Election		$SD_Meeting_Tool_Election		Election to analyze.
		@return		The election in return, maybe in a finished state.
	**/
	private function maybe_finish_election( $SD_Meeting_Tool_Election )
	{
		$election = $SD_Meeting_Tool_Election;		// Conv
		$voters = 0;			// How many people have voted at all.
		
		// Have all the people voted?
		foreach( $election->data->choices as $choice )
		{
			$list = $this->filters( 'sd_mt_get_list', $choice->list_id );
			$list = $this->filters( 'sd_mt_list_participants', $list );
			$voters += count( $list->participants );
		}
		
		$all_voters = $this->filters( 'sd_mt_get_list', $election->data->may_vote );
		$all_voters = $this->filters( 'sd_mt_list_participants', $all_voters );
		if ( $voters == count( $all_voters->participants ) )
		{
			$election->finish();
			$this->filters( 'sd_mt_update_election', $election );
		}
		return $election;
	}
	
	/**
		@brief		Display a results graph for an election.
		
		@param		SD_Meeting_Tool_Election		$election		Election for which to display the results graph.
		@return		A HTML string containing the results graph for the election.
	**/
	private function display_election_graph( $election )
	{
		$rv = '';
		
		if ( ! $election->is_manual() && ! $election->is_anonymous() )
		{
			// Temporarily fill the options with values.
			foreach( $election->data->choices as $choice )
			{
				$list = $this->filters( 'sd_mt_get_list', $choice->list_id );
				if ( $list === false )
				{
					$this->error( $this->_('The choice list for <em>%s</em>, %s, does not exist anymore!', $choice->name, $choice->list_id ) );
					return;
				} 
				$list = $this->filters( 'sd_mt_list_participants', $list );
				$choice->count = count( $list->participants );
			}
		}
		
		$max = 0;
		foreach( $election->data->choices as $choice )
			$max += $choice->count;
		
		$t_body = '';
		foreach( $election->data->choices as $choice )
		{
			$percent = round( $choice->count / $max * 100, 2 );
			$value = '<span class="value">' . $choice->count . '</span> <span class="percent">' . $percent . '%</span>'; 
			$t_body .= '
				<tr>
					<td class="choice">' . $choice->name . '</td>
					<td class="votes">
						' . $value . '
					</td>
					<td class="percent_graph">
						<div class="percent_graph" style="width: ' . $percent . '%">
							&nbsp;
						</div>
					</td>
				</tr>
			';
		}

		$rv .= '
			<table class="widefat election_graph">
				<thead>
					<tr>
						<th class="choice">' . $this->_('Choice') . '</th>
						<th class="votes">' . $this->_('Votes') . '</th>
						<th class="graph">' . $this->_('Graph') . '</th>
					</tr>
				</thead>
				<tbody>
					'.$t_body.'
				</tbody>
			</table>
		';
		
		return $rv;
	}
	
	/**
		@brief		Display voting statistics for an election.
		
		@param		SD_Meeting_Tool_Election		$election		Election for which to display the statistics.
		@return		A HTML string containing the voting statistics for the election.
	**/
	private function display_election_statistics( $election )
	{
		$rv = '';
		
		if ( $election->is_manual() )
			return '';
		
		// Time for some statistics?
		$statistics = array();

		$list = $this->filters( 'sd_mt_get_list', $election->data->have_voted );
		if ( $list === false )
		{
			$this->error( $this->_( 'The <em>have voted</em>-list does not exist anymore.' ) );
			return;
		}
		$have_voted = $this->filters( 'sd_mt_list_participants', $list );
		
		// Is it broken?
		if ( count($have_voted->participants) < 1 )
		{
			$this->error( $this->_( 'The <em>have voted</em>-list is empty.' ) );
			return;
		}

		$list = $this->filters( 'sd_mt_get_list', $election->data->may_vote );
		if ( $list === false )
		{
			$this->error( $this->_( 'The <em>may vote</em>-list does not exist anymore.' ) );
			return;
		}
		$may_vote = $this->filters( 'sd_mt_list_participants', $list );
		
		// Are we missing anyone?
		$missing_count = count( $may_vote->participants ) - count( $have_voted->participants );
		$statistic = new stdClass();
		$statistic->header = $this->_( 'Voters missing' );
		$statistic->data = $missing_count;
		$statistics[] = $statistic;
		
		$participation = count( $may_vote->participants ) / count( $have_voted->participants ) * 100;
		$participation = round( $participation, 2 );
		$statistic = new stdClass();
		$statistic->header = $this->_( 'Voter participation' );
		$statistic->data = $participation . '%';
		$statistics[] = $statistic;
		
		// How about some first and last vote times?
		$first_vote = PHP_INT_MAX;
		$last_vote = 0;
		foreach( $have_voted->participants as $participant )
		{
			$first_vote = min( $first_vote, $participant->registered );
			$last_vote = max( $last_vote, $participant->registered );
		}
		$statistic = new stdClass();
		$statistic->header = $this->_( 'First vote' );
		$statistic->data = date( 'Y-m-d H:i:s', $first_vote );
		$statistics[] = $statistic;
		
		$statistic = new stdClass();
		$statistic->header = $this->_( 'Last vote' );
		$statistic->data = date( 'Y-m-d H:i:s', $last_vote );
		$statistics[] = $statistic;
		
		$statistic = new stdClass();
		$statistic->header = $this->_( 'Time elapsed' );
		$statistic->data = date( 'H:i:s', $last_vote - $first_vote );
		$statistics[] = $statistic;
		
		$t_body = '';
		foreach( $statistics as $statistic )
		{
			$t_body .= '<tr>';
			$t_body .= '<th>' . $statistic->header . '</th>';
			$t_body .= '<td>' . $statistic->data . '</td>';
			$t_body .= '</tr>';
		}
		$rv .= '
			<table class="widefat election_statistics">
				<caption>' . $this->_( 'Election statistics' ) . '</caption>
				'. $t_body .'
			</table>
		';
		
		return $rv;
	}
	
	/**
		@brief		Display who voted in an election.
		
		@param		SD_Meeting_Tool_Election		$election		Election for which to display the voters.
		@return		A HTML string showing who voted in the election.
	**/
	private function display_election_voters( $election )
	{
		$rv = '';
		$list = $this->filters( 'sd_mt_get_list', $election->data->have_voted );
		$have_voted = $this->filters( 'sd_mt_list_participants', $list );
		$list = $this->filters( 'sd_mt_get_list', $election->data->may_vote );
		$may_vote = $this->filters( 'sd_mt_list_participants', $list );

		switch( $election->data->type )
		{
			case 'open':
				// Show who voted for what.
				$rv .= '<h3>' . $this->_( 'The following participants have voted' ) . '</h3>';

				foreach( $election->data->choices as $choice )
				{
					$list = $this->filters( 'sd_mt_get_list', $choice->list_id );
					$list = $this->filters( 'sd_mt_list_participants', $list );
					$choice->participants = $list->participants;
				}

				$display_format = $this->filters( 'sd_mt_get_display_format', $list->data->display_format_id );
				$t_body = '';
				foreach( $have_voted->participants as $participant )
				{
					$vote = $this->_( 'Unknown' );
					foreach( $election->data->choices as $choice )
						if ( isset( $choice->participants[ $participant->id ] ) )
						{
							$vote = $choice->name;
							break;
						} 
					$t_body .= '
						<tr>
							<td>' . $this->filters( 'sd_mt_display_participant', $participant, $display_format ) . '</td>
							<td>' . $vote . '</td>
						</tr>
					';
				}
				
				$rv .= '
					<table class="widefat results">
						<thead>
							<tr>
								<th class="option">' . $this->_('Participant') . '</th>
								<th class="votes">' . $this->_('Vote') . '</th>
							</tr>
						</thead>
						<tbody>
							'.$t_body.'
						</tbody>
					</table>
				';
				break;
			case 'anonymous':
				// Show just a list of people who bothered to vote.
				$rv .= '<h3>' . $this->_( 'The following participants have voted' ) . '</h3>';
				// Show who voted.
				$t_body = '';
				$list = $this->filters( 'sd_mt_get_list', $election->data->have_voted );
				$have_voted = $this->filters( 'sd_mt_list_participants', $list );
				$display_format = $this->filters( 'sd_mt_get_display_format', $list->data->display_format_id );
				foreach( $have_voted->participants as $participant )
					$rv .= $this->filters( 'sd_mt_display_participant', $participant, $display_format ) . '<br />';

		}
		return $rv;
	}
	
	/**
		@brief		Display who should have, but did not vote in an election.
		
		@param		SD_Meeting_Tool_Election		$election		Election for which to display the non-voters.
		@return		A HTML string showing non-voters.
	**/
	private function display_election_nonvoters( $election )
	{
		$rv = '';

		$list = $this->filters( 'sd_mt_get_list', $election->data->may_vote );
		$may_vote = $this->filters( 'sd_mt_list_participants', $list );

		$non_voters = array();
		foreach( $may_vote->participants as $participant )
			if ( ! isset( $have_voted->participants[ $participant->id ] ) )
				$non_voters[] = $participant;
		if ( count( $may_vote->participants ) > 0 )
		{
			$rv .= '<h3>' . $this->_( 'The following participants did not vote at all' ) . '</h3>';
			$display_format = $this->filters( 'sd_mt_get_display_format', $list->data->display_format_id );
			foreach( $non_voters as $participant )
				$rv .= $this->filters( 'sd_mt_display_participant', $participant, $display_format ) . '<br />';
		}
		
		return $rv;
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Shortcodes
	// --------------------------------------------------------------------------------------------

	/**
		@brief		Shows the graph of a completed election.
		
		@par		Attributes
		
		- id		Election ID. Required.
		
		@param		array		$attr		Attributes array.
		@return		Election HTML string to display.
	**/
	public function shortcode_display_election_graph( $attr )
	{
		if ( !isset( $attr['id'] ) )
			return;
		$id = $attr['id'];
		
		$election = $this->filters( 'sd_mt_get_election', $id );
		if ( $election === false )
			return;
		
		if ( ! $election->is_finished() )
			return;
		
		$this->load_language();
		
		return $this->display_election_graph( $election );
	}
	
	/**
		@brief		Shows the statistics  of a completed election.
		
		@par		Attributes
		
		- id		Election ID. Required.
		
		@param		array		$attr		Attributes array.
		@return		Election HTML string to display.
	**/
	public function shortcode_display_election_statistics( $attr )
	{
		if ( !isset( $attr['id'] ) )
			return;
		$id = $attr['id'];
		
		$election = $this->filters( 'sd_mt_get_election', $id );
		if ( $election === false )
			return;
		
		if ( ! $election->is_finished() )
			return;
		
		$this->load_language();
		
		return $this->display_election_statistics( $election );
	}	
}
$SD_Meeting_Tool_Elections = new SD_Meeting_Tool_Elections();

// --------------------------------------------------------------------------------------------
// ----------------------------------------- class SD_Meeting_Tool_Election
// --------------------------------------------------------------------------------------------
/**
	@brief		Election class.
	@see		SD_Meeting_Tool_Elections
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Election
{
	/**
		@var	$id
		ID of election.
	**/
	public $id;
	
	/**
		Serialized data.
		
		Only this property will be saved to the DB.
		
		Contains:
		
		- @b eligible_voters List ID of eligible voters.
		- @b electoral_register_calculated True if the electoral register has been calculated.
		- @b group_field Which of the participants field to use as a group.
		- @b groups_complete Array of group => $group that have filled their voter quotas.
		- @b groups_incomplete Array of group => voter_count of groups that have not filled their voter quotas (too few voters).
		- @b have_voted List ID of voters that have voted.
		- @b may_vote List ID of voters that may vote.
		- @b name Name of election.
		- @b options Array of SD_Meeting_Tool_Election_Choices for which the voters can ... vote.
		- @b status String status of the election: 'editing', 'registering', 'finished'.
		- @b type The type of the election: manual, open, anonymous.
		- @b voters_present List ID of voters that are present and able to vote.
		- @b voting_order_field Which participant field name specifies the order in which parts are allowed to vote.
		
		@var	$data
	**/ 
	public $data;

	public function __construct()
	{
		$this->data = new stdClass();
		$this->data->electoral_register_calculated = false;
		$this->data->eligible_voters = -1;
		$this->data->group_field = -1;
		$this->data->groups_complete = array();
		$this->data->groups_incomplete = array();
		$this->data->have_voted = -1;
		$this->data->may_vote = -1;
		$this->data->name = '';
		$this->data->choices = array();
		$this->data->registration = -1;
		$this->data->status = 'editing';
		$this->data->type = 'manual';
		$this->data->voters_present = -1;
		$this->data->voting_order_field = -1;
	}
	
	// Query about the type
	
	/**
		@brief		Returns whether this is an anonymous election.
		@return		True, if this election is anonymous.
	**/
	public function is_anonymous()
	{
		return $this->data->type == 'anonymous';
	}
	
	/**
		@brief		Returns whether this is a manual election.
		@return		True, if this election is manual.
	**/
	public function is_manual()
	{
		return $this->data->type == 'manual';
	}

	/**
		@brief		Returns whether this is an open election.
		@return		True, if this election is open.
	**/
	public function is_open()
	{
		return $this->data->type == 'open';
	}
	
	// Queries about the electoral register
	
	/**
		@brief		Clears the electoral register and everything related to it.
	**/
	public function clear_electoral_register()
	{
		$this->data->groups_complete = array();
		$this->data->groups_incomplete = array();
		$this->data->electoral_register_calculated = false;
	}
	
	/**
		Sets when the electoral register was last calculated.
		
		@param		$when		Unix time when the register was calculated.
	**/
	public function electoral_register_calculated( $when )
	{
		$this->data->electoral_register_calculated = $when;
	}
	
	/**
		Does this election have an electoral register?
		@return		True, if this election has an electoral register.
	**/
	public function has_electoral_register()
	{
		return $this->is_open() || $this->is_anonymous();
	}
	
	/**
		@brief		Returns whether the electoral register has been calculated.
		@return		True if the electoral register has been calculated. Will always return true if the election is manual.
	**/
	public function is_electoral_register_calculated()
	{
		if ( $this->is_manual() )
			return true;
		return $this->data->electoral_register_calculated !== false;
	}
	
	// Status queries
	
	/**
		@brief		Marks the election as being in status "registering".
	**/
	public function begin_registration()
	{
		$this->data->status = 'registering';
	}

	/**
		@brief		Marks the election as finished.
	**/
	public function finish()
	{
		$this->data->status = 'finished';
	}

	/**
		@brief		Return whether the election is being edited.
		@return		True if election is in editing status.
	**/
	public function is_editing()
	{
		return $this->data->status == 'editing';
	}
	
	/**
		@brief		Return whether the election is finished.
		@return		True if election is finished.
	**/
	public function is_finished()
	{
		return $this->data->status == 'finished';
	}
	
	/**
		@brief		Return whether the election is collecting votes.
		@return		True if election is in collection status.
	**/
	public function is_registering()
	{
		return $this->data->status == 'registering';
	}
	
	// Misc
	
	/**
		@brief		A very primitive check that the election has been somewhat edited and ready to go to the next status.
		
		Basically only checks that the lists are set and that there are more than 0 options.
		
		@return		True if the election passes simple checks to go to the next status.
	**/
	public function finished_editing()
	{
		// The easiest check is first...
		if ( $this->data->status != 'editing' )
			return true;

		if ( count($this->data->choices) < 1 )
			return false;
		
		// Manuals can't pass further checks.
		if ( $this->is_manual() )
			return true;
		
		foreach( $this->lists() as $list_name => $list_id )
			if ( $list_id < 1 )
				return false;
			
		if ( $this->data->group_field == -1 )
			return false;
		
		if ( $this->data->voting_order_field == -1 )
			return false;
		
		return true;
	}

	/**
		@brief		Returns a array of the list id's that this election uses.
		
		@return		An array of list id's that this election uses.
	**/
	public function lists()
	{
		$lists = array(
			'eligible_voters',
			'have_voted',
			'may_vote',
			'voters_present',
		);
		$rv = array();
		
		foreach( $lists as $list )
			$rv[ $list ] = $this->data->$list;
		
		return $rv;
	}
}

/**
	@brief		A single voting choice for elections.

	A multi-purpose class for election choices, used for manual, open and anon elections.

	@see		SD_Meeting_Tool_Election
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool_Election_Choice
{
	/**
		How many voters voted for this option.
		
		This option is used when the election is manual.
		@var	$count
	**/
	public $count = 0;
	
	/**
		Which list is associated to this option.
		
		Used for open and anon elections.
		
		@var	$list_id;
	**/
	public $list_id;
	
	/**
		Name / description of choice.
		@var	$name
	**/
	public $name;
	
	/**
		Unique ID for this choice.
		$var	$uuid
	**/
	public $uuid;
	
	public function __construct()
	{
		$this->uuid = SD_Meeting_Tool::random_uuid();
	}
}

