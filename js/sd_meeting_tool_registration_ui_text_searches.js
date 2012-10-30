jQuery.fn.reverse = [].reverse;

var sd_meeting_tool_registration_ui_text_searches;
(function($) {

sd_meeting_tool_registration_ui_text_searches = searches = {
	// Variables
	ajaxoptions : {},
	settings : {
		manually_selected : false,
		old_term : '543u90453u59034u9',
		input_list_refresh : 500,
		input_list :  {
			timeout : null,
			'hash' : '000',
			'participants' : {}
		},
		latest_list_refresh : 500,
		latest_list :  {
			timeout : null,
		},
		text_refocus :  {
			timeout : null,
		},
	},
	
	// Disables the input field if it isn't disabled already. Fades it out.
	disable : function()
	{		
		if ( $("#__text").attr('readonly') === undefined )
		{
			$("#__text")
				.attr('readonly', true)
				.animate({
					backgroundColor: searches.settings.disabled_color
				}, 100);
		}
	},

	// Enables the input field if it isn't enabled already. Fades it back in (=removed the background color).
	enable : function()
	{
		if ( $("#__text").attr('readonly') !== undefined )
		{
			$("#__text")
				.removeAttr('readonly')
				.animate({
					backgroundColor: "transparent"
				}, 100, function(){
					$(this).css('background-color', '');
				})
				.focus();
		}
	},
	
	// Fetches the list of latest registrations, if necessary (.latest_list exists).
	fetch_latest_list : function()
	{
		if ( $('.latest_list').length < 1 )
			return;
			
		clearTimeout( searches.settings.latest_list.timeout);
		
		options = searches.ajaxoptions;
		options.type = 'fetch_latest_list';
		$.post( options.ajaxurl, options, function(data)
		{
			$('.latest_list').html( data );
		} );
		searches.settings.latest_list.timeout = setTimeout( "sd_meeting_tool_registration_ui_text_searches.fetch_latest_list()", searches.settings.latest_list_refresh );
	},

	// Does a check of the participants and if there are changes will retrieve a new list.
	fetch_participants : function()
	{
		clearTimeout( searches.settings.input_list.timeout);
		var busy_selector = $(".ui_text_searches .text .input");
		sd_mt.busy( busy_selector );
		options = searches.ajaxoptions;
		options.type = 'fetch_participants';
		options.list_hash = searches.settings.input_list.hash;
		$.post( options.ajaxurl, options, function(data){
			try
			{
				new_list = $.parseJSON( data );
				sd_meeting_tool_registration_ui_text_searches.enable();
				if ( new_list.hash != searches.settings.input_list.hash )
					sd_meeting_tool_registration_ui_text_searches.set_list( new_list );
				sd_mt.not_busy( busy_selector );
			}
			catch ( exception )
			{
				sd_meeting_tool_registration_ui_text_searches.disable();
			}
			finally
			{
				searches.settings.input_list.timeout = setTimeout( "sd_meeting_tool_registration_ui_text_searches.fetch_participants()", searches.settings.input_list_refresh );
			}
		} );
	},
	
	// Handles the enter key being pressed.
	handle_enter : function( e )
	{
		e.preventDefault();

		// Selection is by either:
		// One hit in the select
		// or
		// Several hits and cursor used.

		var value = 0;
		var hits = $( 'option', '#__participant_lookup' );
		
		// Several hits and not manually chosen? Meh.
		if ( hits.length != 1 && ! searches.settings.manually_selected )
			return false;
		
		if ( hits.length == 1 )
			value = $(hits).first().attr('value');
		else
			value = $('#__participant_lookup').val();
		
		var value_text = $('#__participant_lookup option[value=' + value + ']').text();
		
		$("#__text")
			.attr('readonly', true)
			.addClass('search_successful')
			.val( value_text );
		
		if ( searches.settings.ajax_submit )
		{
			// Send the submit ajax
			options = searches.ajaxoptions;
			options.type = 'submit_registration';
			options.text = value;
			$.post( options.ajaxurl, options, function(data){
				searches.fetch_participants();
				setTimeout( function(){
					// Re-enable the search box.
					$("#__text")
						.attr('readonly', false)
						.removeClass('search_successful')
						.val( '' );

					// Force a reshow.
					searches.settings.old_term = '1';
					searches.show_hits();
				}, searches.settings.enter_delay );
			} );
		}
		else
		{
			setTimeout( function(){
				$(".ui_text_searches_" + searches.ajaxoptions.uuid + " form").submit();
			}, searches.settings.enter_delay );
		}
		return false;
	},
	
	// A key was pressed. What do we do?
	handle_keyup : function( e )
	{
		switch( e.which )
		{
			case 8:
				if ( e.shiftKey )
					$("#__text").val( '' );
				sd_meeting_tool_registration_ui_text_searches.show_hits();
				break;
			case 33:	// PageUp
			case 34:	// PageDown
			case 38:	// Up
			case 40:	// Down
				searches.settings.manually_selected = true;
				$('#__participant_lookup').focus().trigger( e );
				break;
			default:
				sd_meeting_tool_registration_ui_text_searches.show_hits();
				break;
		}
	},
	
	init : function( ajaxoptions, settings ) {
		sd_meeting_tool_registration_ui_text_searches.disable();
		
		searches.ajaxoptions = $.extend( true, {}, ajaxoptions );
		searches.settings = $.extend( true, searches.settings, settings );

		$("#__text")
			.keydown( function(e){
				if ( e.which == 13 )
					return sd_meeting_tool_registration_ui_text_searches.handle_enter(e);
			})
			.keyup( function(e){
				return sd_meeting_tool_registration_ui_text_searches.handle_keyup(e);
			})
			.focus()
			.attr('autocomplete', 'off')
			.blur( function(){
				sd_meeting_tool_registration_ui_text_searches.refocus_text();
			});
			

		$('#__participant_lookup')
			.dblclick( function(e){
				searches.settings.manually_selected = true;
				sd_meeting_tool_registration_ui_text_searches.handle_enter(e);
			})
			.keydown( function(e){
				if ( e.which == 13 )
				{
					e.preventDefault();
					$("#__text").focus();
				}
			})
			.change( function(){
				sd_meeting_tool_registration_ui_text_searches.refocus_text();
			});
		
		searches.settings.input_list.timeout = setTimeout( "sd_meeting_tool_registration_ui_text_searches.fetch_participants()", 1 );
		searches.settings.latest_list.timeout = setTimeout( "sd_meeting_tool_registration_ui_text_searches.fetch_latest_list()", 1 );
	},
	
	// Handles the timer for the text refocus
	refocus_text : function() {
		clearTimeout( searches.settings.text_refocus.timeout );
		searches.settings.text_refocus.timeout = setTimeout( function(){
			$("#__text").focus();
		}, 2000);
	},
	
	set_list : function( new_list )
	{
		searches.settings.input_list.hash = new_list.hash;
		searches.settings.input_list.participants = new_list.participants;
		searches.settings.old_term = '000';	// Set to force the list to refresh.
		sd_meeting_tool_registration_ui_text_searches.show_hits();
	},
	
	// Shows the hits in the list, if any.
	show_hits : function()
	{
		var term = $("#__text").val();
		
		if ( term == searches.settings.old_term )
			return;
		
		searches.settings.manually_selected = false;
		searches.settings.old_term = term;
		
		term = term.toLowerCase();
		
		// Remember the old value, because we're going to be emptying the list and filling it with "new" values.
		// Would be nice if the list still has the old selection.
		var old_value = $('#__participant_lookup').val();

		$('#__participant_lookup').find('option').remove();
		
		$.each( searches.settings.input_list.participants , function(key, value)
		{
			if ( value.toLowerCase().indexOf( term ) !== -1 ) 
				$('#__participant_lookup')
					.append( $('<option>', { value : key }).text(value) ); 
		});
		
		// Setting a value that doesn't exist anymore has no effect.
		$('#__participant_lookup').val( old_value );
	}
	
};
})(jQuery);
