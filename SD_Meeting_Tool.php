<?php
/*                                                                                                                                                                                                                                                             
Plugin Name: SD Meeting Tool Base
Plugin URI: https://it.sverigedemokraterna.se
Description: Handle meetings and conferences.
Version: 1.1
Author: Sverigedemokraterna IT
Author URI: https://it.sverigedemokraterna.se
Author Email: it@sverigedemokraterna.se
*/

if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.'); }

require_once( 'SD_Meeting_Tool_0.php' );
require_once( 'include/interface_SD_Meeting_Tool_Actions.php' );
require_once( 'include/interface_SD_Meeting_Tool_Agendas.php' );
require_once( 'include/interface_SD_Meeting_Tool_Display_Formats.php' );
require_once( 'include/interface_SD_Meeting_Tool_Lists.php' );
require_once( 'include/interface_SD_Meeting_Tool_List_Sorts.php' );
require_once( 'include/interface_SD_Meeting_Tool_Participants.php' );
require_once( 'include/interface_SD_Meeting_Tool_Registrations.php' );
require_once( 'include/interface_SD_Meeting_Tool_Registration_UI.php' );
require_once( 'include/SD_Meeting_Tool_Action.php' );
require_once( 'include/SD_Meeting_Tool_Action_Item.php' );
require_once( 'include/SD_Meeting_Tool_Action_Trigger.php' );
require_once( 'include/SD_Meeting_Tool_Action_Item_Trigger.php' );
require_once( 'include/SD_Meeting_Tool_Agenda.php' );
require_once( 'include/SD_Meeting_Tool_Agenda_Item.php' );
require_once( 'include/SD_Meeting_Tool_Display_Format.php' );
require_once( 'include/SD_Meeting_Tool_List.php' );
require_once( 'include/SD_Meeting_Tool_List_Sort.php' );
require_once( 'include/SD_Meeting_Tool_List_Sort_Order.php' );
require_once( 'include/SD_Meeting_Tool_Participant.php' );
require_once( 'include/SD_Meeting_Tool_Participant_Field.php' );
require_once( 'include/SD_Meeting_Tool_Registration.php' );
require_once( 'include/SD_Meeting_Tool_Registration_UI.php' );

/*!
	@mainpage	SD Meeting Tool
	
	@section	sd_meeting_tool_introduction		Introduction
		
	@sdmt is a Wordpress plugin that can be used to administer meetings and conferences. It was originally written by the <a title="The Sweden Democrat site" href="http://sverigedemokraterna.se">Sweden Democrat party</a> for internal use.
	
	@sdmt is free software, as per the @ref sd_meeting_tool_license.
	
	@section	sd_meeting_tool_description			Description
	
	@sdmt was written to handle organizational and governmental meetings and conferences, by providing participant, voting, checkin, checkout, printing services, etc.
	
	@section	sd_meeting_tool_requirements		Requirements
	
	- At least one @b technically-minded person to administer the internals of the tool: creation of participants, lists, actions, registrations, etc.
	- A @b <a href="http://wordpress.org">Wordpress</a> installation.
	- @b Javascript for the administrators and, optionally, for visitors that want automatic refreshes.
	
	Some features, such as automatic updating of shortcodes for visitor pages, will require powerful hardware. The reason for this is
	that most shortcodes are completely dynamically generated without caching possibility. Having several hundred or thousand visitors
	requesting AJAX-updates of speaker lists or agendas will bog down Wordpress (being a generally slow CMS to start off with).
	
	Keeping the amount of visitors to a minimum, or even better: keeping the site internal and private, is recommended until further notice.
	
	@subsection	sd_meeting_tool_requirements_optional	Optional requirements
	
	- Functionaries to handle registrations, polls, etc.\n
		Having several funtionaries speeds up registrations, votes, etc. Each should have access to a computer.
	- <a href="http://www.reichelt.de/Barcodescanner-MDE/SCANNER-BCD-4U/index.html?;ACTION=3;LA=2;ARTICLE=106408;GROUPID=4904;SID=12TmXI938AAAIAAG37LYw4f33174a5be9eb77543f7a53fa119c74">Barcode scanners</a>.\n
		Some plugins use barcodes to quickly scan in participants. One scanner per functionary computer is suggested.
	
	@section	sd_meeting_tool_installation		Installation
	
	Activate at least @sdmt Base together with following plugins, which are needed for basic functionality: 
	- @ref sd_meeting_tool_actions creates and handles actions.
	- @ref sd_meeting_tool_display_formats controls how participants are displayed internally.
	- @ref sd_meeting_tool_lists puts participants into lists.
	- @ref sd_meeting_tool_list_sorts sorts lists.
	- @ref sd_meeting_tool_participants specifies who is partaking in the meeting.
	
	The following plugins are included in the base package: 
	- @ref sd_meeting_tool_agendas allows for several agendas.
	- @ref sd_meeting_tool_displays displays lists to visitors.
	- @ref sd_meeting_tool_elections allows participants to vote.
	- @ref sd_meeting_tool_printing prints participants as PDFs.
	- @ref sd_meeting_tool_registrations handles registration UIs and actions associated to UIs.
	- @ref SD_Meeting_Tool_Registration_UI_Text_Searches registers users by searching for text strings in the participant's display format.
	- @ref sd_meeting_tool_speakers maintains a speaker list for each agenda.

	Since @sdmt uses Wordpress actions and filters to transmit data between plugins, other, more advanced plugins, can be
	used instead of the provided modules.
		
	Activate the base plugin first and then the other plugins. Each plugin specifies which other plugins are necessary for
	correct function. See the documentation pages for each plugin.
	
	@section	sd_meeting_tool_usage				Usage
	
	There are two main concepts that are the foundations of @sdmt: participants and lists. 
	
	@par		Participants
	
	A participant is simple a number with a unique number of participant fields attached. The fields themselves have no meaning to @sdmt and act as random data. The number is an ID number that is passed between plugins that work with the participants.
	
	Participants can be easily imported from spreadsheets using the import function. As long as there is an ID column the participants will be imported.
	
	@par		Lists
	
	Lists are collections of participants - and sometimes even other lists. Almost all @sdmt plugins uses lists in some way or other so it is important that the admin begins thinking in "lists of participants" before using @sdmt. The flexiblity of lists, including the ability to include and exclude participants in other lists, allows lists to help with:
	
	- checking in (via registrations)
	- checking out (via registrations)
	- printing
	- speaking
	- voting in elections (registrations and results)
	- etc.
	
	See @ref SD_Meeting_Tool_Lists for more information on how to manipulate lists. 
	
	@par		Plugins
	
	@sdmt by itself does very little. Most of the grunt work is done by the various included plugins, depending on need. See
	the documentation of the respective plugins.
	
	@section	sd_meeting_tool_tips				Tips
	
	- A dedicated machine for the meeting is a good idea if you plan to allow for lots of visitors. Wordpress is notoriously slow to start up, therefore requiring quite a powerful machine to allow for many automatic AJAX updates of the speaker list and agenda and such.
	
	@section	sd_meeting_tool_developers			Developers
	
	SD IT welcomes patches and new features from the public. Send all patches to the author.
	
	If you wish to write new @sdmt plugins there are interfaces in the include/ directory that provide a quick refeference.
	
	Most of the code is relatively well documented using Doxygen syntax.
	
	@section	sd_meeting_tool_plugins				Plugins
	
	The plugin system of @sdmt uses the same plugins, hooks and filters that Wordpress uses.
	The included @sdmt plugins can be replaced with more advanced plugins if necessary, as long as the replacements use @sdmt filters and actions.
	
	@section	sd_meeting_tool_libraries			Libraries
	
	@sdmt uses the following libraries, included in the base package, that are written by others:
	
	- FPDI (http://www.setasign.de/products/pdf-php-solutions/fpdi/) to import exisiting PDFs. 
	- JQuery Tablesorter (http://tablesorter.com/) to sort html tables.
	- TCPDF (http://www.tcpdf.org/) to create PDFs.
	
	@section	sd_meeting_tool_license				License
	
	This software and all included plugins is licensed under the <a title="GNU General Public License v3" href="http://gplv3.fsf.org/">GPL, version 3</a>. 
	
	@author		Sverigedemokraterna	http://www.sverigedemokraterna.se
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/

/**
	SD Meeting Tool base.
	
	@par		Actions fired
	
	- sd_mt_admin_menu - Menu has been created, allow modules to create submenus.

	@par		Filters fired
	
	- sd_mt_overview_tabs - Allow modules to create their own overview tabs.
	
	@par		Changelog
	
	@par		1.1
	
	- Added: Settings tab.
	
	@brief		Base plugin for the Meeting Tool.
	@author		Edward Plainview	edward.plainview@sverigedemokraterna.se
**/
class SD_Meeting_Tool extends SD_Meeting_Tool_0
{
	/**
		Local options.
		
		- role_use Minimum role needed to access MT at all.
		
		@var	$local_options
	**/
	protected $local_options = array(
		'role_use' => 'administrator',
	);

	public function __construct()
	{
		parent::__construct( __FILE__ );
		add_action( 'admin_menu',					array( $this, 'admin_menu') );
		add_filter( 'sd_mt_overview_tabs',			array( $this, 'filter_overview_tabs' ), 5 );
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Admin
	// --------------------------------------------------------------------------------------------
	
	public function activate()
	{
		parent::activate();
	}

	/**
		@brief		Shows the admin menu.
	**/
	public function admin_menu()
	{
		if ($this->role_at_least( $this->get_local_option('role_use') ))
		{
			$this->load_language();
			add_menu_page(
				$this->_('Meeting Tool'),
				$this->_('Meeting Tool'),
				'read',
				'sd_mt',
				array( &$this, 'admin' ),
				null
			);

			$menus = $this->filters( 'sd_mt_admin_menu', array() );
			ksort( $menus );
			foreach( $menus as $menu )
				add_submenu_page( $menu[0], $menu[1], $menu[2], $menu[3], $menu[4], $menu[5] ); 
			
			wp_enqueue_style( 'sd_mt_css', '/' . $this->paths['path_from_base_directory'] . '/css/SD_Meeting_Tool.css', false, '1.0', 'screen' );
			wp_enqueue_script( 'sd_mt_js', '/' . $this->paths['path_from_base_directory'] . '/js/sd_meeting_tool.js', 'jquery', '1.0', true );
		}
	}

	public function admin()
	{
		if ( ! $this->check_cache_directory() )
		{
			$this->error( $this->_('The cache directory /cache does not exist or is not writeable. Please create it before continuing.') );
			return;
		}
		
		$tab_data = array(
			'tabs'		=>	array(),
			'functions' =>	array(),
		);
				
		$tab_data['tabs']['overview'] = $this->_( 'Overview' );
		$tab_data['functions']['overview'] = 'admin_overview';

		$tab_data['tabs']['settings'] = $this->_( 'Settings' );
		$tab_data['functions']['settings'] = 'admin_settings';

		$tab_data = $this->filters( 'sd_mt_overview_tabs', $tab_data );
		
		$this->tabs($tab_data);
	}
	
	/**
		@brief		An overview of the situation.
	**/
	public function admin_overview()
	{
		$rv = '';
		
		$rv .= $this->_( 'No overview yet.' );
		
		echo $rv;
	}
	
	/**
		@brief		General settings.
	**/
	public function admin_settings()
	{
		$rv = '';
		$form = $this->form();
		
		if ( isset( $_POST[ 'update' ] ) )
		{
			$this->update_site_option( 'role_use', $_POST[ 'role_use' ] );
			$this->message_( 'The settings have been updated!' );
		}
		
		$inputs = array(
			'role_use' => array(
				'name' => 'role_use',
				'type' => 'select',
				'label' => $this->_( 'Access role' ),
				'description' => $this->_( 'What is the minimum use role needed to access the plugin?' ),
				'options' => $this->roles_as_options(),
				'value' => $this->get_site_option( 'role_use' ),
			),
			'update' => array(
				'name' => 'update',
				'type' => 'submit',
				'value' => $this->_( 'Update settings' ),
				'css_class' => 'button-primary',
			),
		);
		
		$rv = $form->start();
		$rv .= $this->display_form_table( $inputs );
		$rv .= $form->stop();

		echo $rv;
	}
	
	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Filters
	// --------------------------------------------------------------------------------------------
	
	/**
		@brief		Add tabs to admin overview.
		
		@param		array		$tab_data		Tab data to add to.
	**/ 
	public function filter_overview_tabs( $tab_data )
	{
		$tab_data['default'] = 'overview';

		$tab_data['tabs']['overview'] = $this->_( 'Overview');
		$tab_data['functions']['overview'] = 'admin_overview';
		
		return $tab_data;
	}

	// --------------------------------------------------------------------------------------------
	// ----------------------------------------- Misc
	// --------------------------------------------------------------------------------------------

	/**
		@brief		Shows a message that links to a reload of this page.
	**/
	public static function reload_message()
	{
		global $SD_Meeting_Tool;
		$url = remove_query_arg( '4k3rgnlkegn' );
		$SD_Meeting_Tool->message(
			sprintf(
				'%s%s%s%s%s',
				'<a href="',
				$url,
				'">',
				$SD_Meeting_Tool->_('Reload this page!'),
				'</a>'
			)
		);
	}
	
	/**
		@brief		Makes a standard back link using a text string and a link.
		
		@param		string		$text		Text to show on link.
		@param		string		$url		URL
		@return		HTML string.
	**/
	public static function make_back_link( $text, $url )
	{
		return '<div class="sd_mt_back_link"><a href="' . $url . '">' . $text . '</a></div>';
	}

	/**
		@brief		Returns a random uuid.
		
		@return		A SHA512 of a random number.
	**/
	public static function random_uuid()
	{
		$value = rand(0, PHP_INT_MAX) . rand(0, PHP_INT_MAX);
		$value .= time();
		return hash( 'SHA512', $value );
	}
	
	/**
		Sorts an array of objects with the key stored in the array item's ->data field.
		
		@param		array		$array		Array to sort
		@param		mixed		$key		Which key in the array to sort by.
		@return		The sorted array.
	**/
	public static function sort_data_array( $array, $key )
	{
		// In order to be able to sort a bunch of objects, we have to extract the key and use it as a key in another array.
		// But we can't just use the key, since there could be duplicates, therefore we attach a random value.
		$sorted = array();
		foreach( $array as $index => $item )
		{
			do
			{
				$new_key = $item->data->$key;
				if ( is_int( $new_key ) )
					$new_key = str_pad( $new_key, 32, '0', STR_PAD_LEFT );
				$rand = rand(0, PHP_INT_MAX / 10);
				$random_key = $new_key . '_' . $rand;
			}
			while ( isset( $sorted[ $random_key ] ) );
			$sorted[ $random_key ] = array( 'key' => $index, 'value' => $item );
		}
		ksort( $sorted );
		
		// The array has been sorted, we want the original array again.
		$rv = array();
		foreach( $sorted as $item )
			$rv[ $item['key'] ] = $item['value'];
			
		return $rv;
	}

	/**
		@brief		Converts array values into ints.
		
		Can and will recurse subarrays.
		
		@param		array		$array		Array of values to convert to ints.
		@return		The integerized array.
	**/
	public static function array_intval( $array )
	{
		foreach( $array as $key => $value )
		{
			if ( is_array( $value ) )
				$array[ $key ] = self::array_intval( $array );
			else
				$array[ $key ] = intval( $value );
		}
		return $array;
	}
	
	/**
		@brief		Does the same thing as Wordpress' check_admin_referrer, but with even more checks.

		@param		string		$action		Nonce action name
		@param		string		$key		Key in POST where nonce is stored.
		@return		True if the nonce checks out.
	**/
	public static function check_admin_referrer( $action, $key = 'ajaxnonce' )
	{
		if ( !isset( $_POST[ $key ] ) )
			return false;
		return check_admin_referer( $action, $key );
	}

	/**
		@brief		Check that the response hash has changed.
		
		If not, the data key is emptied.
		
		@param		array		$response		An array containing the keys hash and html.
		@param		string		$hash			"Old" hash to check. If it's different the data is kept.
	**/
	public function optimize_response( &$response, $hash )
	{
		$response['hash'] = substr( md5( serialize($response['data']) ), 0, 4 );
		if ( $response['hash'] == $hash )
			unset( $response['data'] );
	}
}
$SD_Meeting_Tool = new SD_Meeting_Tool();