jQuery.fn.reverse = [].reverse;

var sd_meeting_tool_elections;

(function($) {

sd_meeting_tool_elections = elections = {
	ajaxoptions : {},
	settings : {
		list_have_voted : {
			hash : 123,
			refresh : 2000,
			timeout : null,
		},
	},
	
	// Does a check of have voted and if there are changes will retrieve a new list.
	fetch_participants : function()
	{
		options = jQuery.extend( true, {}, elections.ajaxoptions );
		options.type = 'fetch_have_voted';
		options.have_voted_list_hash = elections.settings.list_have_voted.hash;
		$.post( ajaxurl, options, function(data){
			try
			{
				new_list = $.parseJSON( data );
				if ( new_list.hash != elections.settings.list_have_voted.hash )
					sd_meeting_tool_elections.show_list( new_list );
			}
			catch ( exception )
			{
			}
			finally
			{
				elections.list_have_voted_enable_refresh();
			}
		} );
	},
	
	init : function( ajaxoptions, settings ) {
		elections.ajaxoptions = $.extend( true, elections.ajaxoptions, ajaxoptions );
		elections.settings = $.extend( true, elections.settings, settings );
		elections.list_have_voted_enable_refresh( 1 ); 
	},
	
	// Enables the refresh of the have voted list.
	
	list_have_voted_enable_refresh : function( refresh )
	{
		elections.list_have_voted_disable_refresh();
		if ( refresh === undefined )
			refresh = elections.settings.list_have_voted.refresh;
		elections.settings.list_have_voted.timeout = setTimeout( "elections.fetch_participants()", refresh );
	},
	
	list_have_voted_disable_refresh : function()
	{
		clearTimeout(elections.settings.list_have_voted.timeout);
	},

	show_list : function( list )
	{
		elections.settings.list_have_voted.hash = list.hash;
		
		$("#have_voted").html( list.text );
		
		$("#have_voted").hover( function(){
			// Hovering!
			elections.list_have_voted_disable_refresh();
		},
		function(){
			// Not hovering anymore.
			elections.fetch_participants();
			elections.list_have_voted_enable_refresh();
		} );
		
		$("#have_voted td.option input.checkbox").click(function(){
			var $tr = $(this).parentsUntil('tr').parent();
			$( 'input.checkbox', $tr ).attr( 'disabled', true );
			
			options = jQuery.extend( true, {}, elections.ajaxoptions ); 
			options.type = 'set_vote';
			options.participant_id = $(this).parent().attr('participant_id');
			options.option_id = $(this).parent().attr('option_id');
			var td = $(this).parent();
			$.post( ajaxurl, options, function(data){
				try
				{
					result = $.parseJSON( data );
					$(td).removeClass('clicked');
					if ( result.result == 'reload' )
					{
						// Election is finished.
						window.location.reload();
						return;
					} 
					if ( result.result != 'ok' )
						throw  'Error setting option! OK failure.';
					else
					{
						$( 'input.checkbox', $tr ).removeAttr( 'disabled' );
					}
				}
				catch ( exception )
				{
					alert( 'Error setting option! JSON failure. ' + exception ); 
					return;
				}
			} );
		});
	},
};

})(jQuery);
