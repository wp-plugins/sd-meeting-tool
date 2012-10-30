function sd_meeting_tool_speakers()
{
	$ = jQuery;

	/**
		Adds a participant to the speaker list.
	**/
	this.create_speaker = function( participant_id )
	{
		var caller = this;
		options = jQuery.extend( true, {}, this.ajaxoptions );
		options.type = "create_speaker";
		options.participant_id = participant_id;
		
		if ( $(".speakers .clicked").length > 0 )
			options.parent = $(".speakers .clicked").parentsUntil('li.speaker').parent().attr( 'speaker_id' );
		else
			options.parent = 0;
		
		if ( caller.settings.time_to_speak != '' )
			options.time_to_speak = caller.settings.time_to_speak;
		
		this.busy( "#speakers" );
		$.post( caller.ajaxurl, options, function(data){
			try
			{
				result = $.parseJSON( data );
				if ( result === null )
					throw "Empty result";
				caller.save_order( function(){
					caller.get_speakers();
				});
			}
			catch ( exception )
			{
			}
		} );
	},
	
	/**
		Deletes a speaker, assuming one has been clicked.
	**/
	this.delete_speaker = function()
	{
		if ( $(".speakers .clicked").length < 1 )
			return;
		
		var caller = this;
		options = jQuery.extend( true, {}, this.ajaxoptions );

		options.type = "delete_speaker";
		options.speaker_id = $(".speakers .clicked").parentsUntil('li.speaker').parent().attr( 'speaker_id' );
		this.busy( "#speakers" );
		$.post( caller.ajaxurl, options, function(data){
			try
			{
				result = $.parseJSON( data );
				if ( result === null )
					throw "Empty result";
				caller.get_speakers();
			}
			catch ( exception )
			{
			}
		} );
		
	},
	
	/**
		Only enable the delete speaker button if there is a speaker chosen
	**/
	this.toggle_delete_speaker_button = function( top )
	{
		var $button = $("#__delete_speaker");
		if ( $(".speakers .clicked").length < 1 )
			$button.attr( 'disabled', 'disabled' ).fadeTo( 500, 0.0 );
		else
		{
			if ( top === undefined )
				top = $button.position().top;
			$button.css( 'top', top ).removeAttr( 'disabled' ).fadeTo( 500, 1.0 );
		}
	}
	
	this.display_time_left = function( $div )
	{
		if ( this.settings.current_speaker.time_left < 0 )
		{
			$div.addClass( 'speaking_too_long' );
			return;
		}
		$div.html( this.seconds_to_time( this.settings.current_speaker.time_left ) );
	},
	
	this.get_agenda = function()
	{
		var caller = this;
		options = jQuery.extend( true, {}, this.ajaxoptions );
		options.type = "get_agenda";
		options.hash = caller.settings.agenda.hash;
		this.busy( "#agenda" );
		$.post( caller.ajaxurl, options, function(data)
		{
			try
			{
				var new_agenda = $.parseJSON( data );
				
				if ( new_agenda.data !== undefined )
				{
					caller.settings.agenda = $.extend( true, caller.settings.agenda, new_agenda );
					var $new_agenda = $( caller.settings.agenda.data.html );
					var $agenda = $("#__agenda_items");
					
					$( 'option', $agenda ).remove();
					$( 'option', $new_agenda ).appendTo( $agenda );
					
					if ( caller.ajaxoptions.agenda_item_id === undefined )
						caller.ajaxoptions.agenda_item_id = $("#__agenda_items option").first().attr( 'value' );
					
					$("#__agenda_items").val( caller.ajaxoptions.agenda_item_id );
					
					caller.toggle_agenda_select_button();
				}
			}
			catch ( exception )
			{
			}
			finally
			{
				caller.not_busy( "#agenda" );
				clearTimeout( caller.settings.agenda.timeout );
				caller.settings.agenda.timeout = setTimeout( function()
				{
					caller.get_agenda();
				}, caller.settings.agenda.refresh );
			}
		} );
	},
		
	// Fills the list of available participants	
	this.get_participants = function()
	{
		var caller = this;
		options = jQuery.extend( true, {}, this.ajaxoptions );

		options.type = "get_participants";
		options.hash = this.settings.participants.hash;
		this.busy( "#participant_list" );
		$.post( caller.ajaxurl, options, function(data)
		{
			try
			{
				new_participants = $.parseJSON( data );
				
				if ( new_participants.hash != caller.settings.participants.hash )
				{
					// Clear the target participants.
					caller.settings.participants.data = {};
					
					caller.settings.participants = $.extend( true, caller.settings.participants, new_participants );
					// Force search to do a new search.
					caller.settings.search.old_term = 'xxx';
					caller.search();
				}
				caller.not_busy( "#participant_list" );
			}
			catch ( exception )
			{
			}
			finally
			{
				clearTimeout( caller.settings.timeout_list_refresh );
				caller.settings.timeout_list_refresh = setTimeout( function(){
					caller.get_participants();
				}, caller.settings.participants.refresh );
			}
		} );
	},
	
	this.get_speakers = function ()
	{
		var caller = this;
		
		clearTimeout( caller.settings.speakers.timeout );
		
		options = jQuery.extend( true, {}, this.ajaxoptions );

		options.type = "get_speakers";
		options.hash = caller.settings.speakers.hash;
		this.busy( "#speakers" );
		$.post( caller.ajaxurl + "?get_speakers", options, function(data){
			try
			{
				var result = $.parseJSON( data );
				
				// Same hash = no differences = no need to continue.
				if ( ( result.hash == caller.settings.speakers.hash ) )
				{
					caller.not_busy( "#speakers" );
					throw Exception( "" );
				}

				caller.settings.speakers.hash = result.hash;

				if ( caller.settings.speakers.expecting_new_hash )
				{
					caller.settings.speakers.expecting_new_hash = false;
					caller.not_busy( "#speakers" );
					throw Exception( "" );
				}

				$(".speakers .inside .container").fadeTo( 250, 0.5 );
				
				var $previously_clicked = $(".speakers .clicked");
				if ( $previously_clicked.length > 0 )
				{
					var previously_clicked = $previously_clicked.parentsUntil('li.speaker').parent().attr( 'speaker_id' );
				}
				else
					previously_clicked = undefined;
				
				$(".speakers .inside .container").empty();
				$(".speakers .inside .container").html( result.data );
				
				// Make them clickable.

				$(".speakers .clickable .header .quick_add").click(function( e )
				{
					e.preventDefault();
					var participant_id = $(this).parentsUntil('li.speaker').parent().attr('participant_id');
					caller.create_speaker( participant_id );
					return false;	// Prevent the header from being clicked.
				});

				$(".speakers .clickable .header").click(function()
				{
					var $this = $(this);
					var was_clicked = $this.hasClass('clicked');
					$('.extra_rows', $(".speakers .clicked").parent() ).animate({
							height: 'toggle'
						}, 250, function() {});
					$(".speakers .clicked").removeClass( 'clicked' );
					if ( ! was_clicked )
					{
						$this.addClass( 'clicked' );
						$('.extra_rows', $(this).parent()).animate({
							height: 'toggle'
						}, 250, function() {});
					}
					// Make the button follow the clicked speaker: top of scrollbar + position of speaker.
					caller.toggle_delete_speaker_button( $this.parentsUntil( '.inside' ).parent().scrollTop() + $this.position().top );
				});
				
				// Reclick if necessary
				if ( previously_clicked !== undefined )
					$( "div.speaker_id_" + previously_clicked + " >  .header" ).click();
				
				// Toggle the delete button
				caller.toggle_delete_speaker_button();

				// Make those can be sorted, sortable.
				$( ".speakers .speaker_group" ).sortable({
					axis : 'y',
					helper : 'clone',
					items : 'li.sortable',
					update : function( event, ui )
					{
						caller.save_order();
					}
            	});
				
				$( ".speakers input.time_add" ).click( function(){
					var speaker_id = $(this).parentsUntil('li.speaker').parent().attr('speaker_id');
					caller.time_modify( speaker_id, "+30" );
				});

				$( ".speakers input.time_subtract" ).click( function(){
					var speaker_id = $(this).parentsUntil('li.speaker').parent().attr('speaker_id');
					caller.time_modify( speaker_id, "-30" );
				});
				
				// Set the time on pressing enter.
				$( ".speakers input.time" ).keyup( function(e){
					switch( e.which )
					{
						case 13:
							var new_time = $( ".speakers input.time" ).val();
							var speaker_id = $(this).parentsUntil('li.speaker').parent().attr('speaker_id');
							caller.time_modify( speaker_id, new_time );
							$(this).blur();
							break;
						default:
					}
				});

				if ( $(".speakers .speaking").length > 0 )
				{
					caller.speaking();
				}

				$(".speakers input.time_restop").click( function(){
					$(this).attr('disabled', true );
					var speaker_id = $(this).parentsUntil('li.speaker').parent().attr('speaker_id');
					caller.restop_speaking( speaker_id );
				});

				$( ".speakers input.time_start" ).click( function(){
					$(this).attr('disabled', true );
					var speaker_id = $(this).parentsUntil('li.speaker').parent().attr('speaker_id');
					caller.start_speaking( speaker_id );
				});

				$(".speakers input.time_stop").click( function(){
					$(this).attr('disabled', true );
					var speaker_id = $(this).parentsUntil('li.speaker').parent().attr('speaker_id');
					caller.stop_speaking( speaker_id );
				});

				$(".speakers .inside .container").fadeTo( 250, 1.0 );
				caller.not_busy( "#speakers" );
			}
			catch ( exception )
			{
			}
			finally
			{
				caller.settings.speakers.timeout = setTimeout( function(){
					caller.get_speakers();
				}, caller.settings.speakers.refresh );
			}
		} );
	},
	
	this.init = function ( ajaxoptions, settings )
	{
		this.ajaxoptions = $.extend( true, {}, ajaxoptions );
		this.ajaxurl = ajaxoptions.ajaxurl;
		this.ajaxoptions.ajaxurl = null;
	},
	
	this.init_admin = function( ajaxoptions ) {
		this.init( ajaxoptions, {} );
		this.settings = {		
			"agenda" : {
				"data" : "",
				"hash" : "",
				"refresh" : 30000,
				"timeout" : undefined,
			},
			"current_speaker" : {},
			"participants" : {
				"refresh" : 30000,
				"data" : {},
				"hash" : "",
			},
			"search" : {
				"old_term" : 'xxx',
			},
			"speakers" :
			{
				"expecting_new_hash" : false,
				"hash" : "",
				"refresh" : 5000,
				"timeout" : undefined,
			},
			'time_to_speak' : '',
			"timeout_init_heights" : undefined,
		}
		this.init_heights();
		var caller = this;
		$(window).resize( function(){
			clearTimeout( caller.settings.timeout_init_heights );
			caller.settings.timeout_init_heights = setTimeout( function(){
				caller.init_heights();
			}, 1000 );
		});

		this.init_agenda();
		this.get_speakers();
		this.get_participants();
		this.init_buttons();
		this.init_search();
		this.init_settings();
		return;
	},
	
	/**
		Start the agenda.
	**/
	this.init_agenda = function()
	{
		this.ajaxoptions.agenda_item_id = $("#__agenda_items option").first().attr( 'value' );
		this.settings.agenda = {};
		this.settings.agenda.hash = '0';
		this.settings.agenda.refresh = 30000;

		var caller = this;
		this.settings.agenda.$agenda_items = $( "#__agenda_items" );
		this.settings.agenda.$agenda_items.change( function(){
			caller.ajaxoptions.agenda_item_id = $(this).val();
			caller.get_speakers();
			caller.toggle_agenda_select_button();
		}).keypress( function( e )
		{
			$(this).change();
		});
		
		// Handle the choosing of a new agenda item.
		this.settings.agenda.$change_agenda_item = $( '#__change_agenda_item' );
		this.settings.agenda.$change_agenda_item.fadeTo( 0, 0 ).click( function()
		{
			options = jQuery.extend( true, {}, caller.ajaxoptions );
			options.type = "change_agenda_item";
			options.agenda_item_id = caller.settings.agenda.$agenda_items.val();
			caller.busy( "#agenda" );
			$.post( caller.ajaxurl, options, function(data)
			{
				caller.get_agenda();
				caller.not_busy( "#agenda" );
			});
		});
		
		this.get_agenda();
	},
	
	this.init_buttons = function()
	{
		var caller = this;
		$("#__delete_speaker").click( function()
		{
			caller.delete_speaker();
		});
	},
	
	this.init_heights = function()
	{
		var $manage_speakers = $(".manage_speakers");
		// Our maximum height is the viewport minus the header.
		var $footer = $( "#footer" );
		max = $footer.position().top - $footer.height() - 5;
		max -= $manage_speakers.position().top;
		$manage_speakers.css( 'height', max + 'px' );
		
		// Left
		var left_bottom = $(".overview_left .bottom").height();
		var left_height = max - left_bottom - padding;
		var heading_height = $("#speakers .heading").height();
		var padding = 5;
		
		// Speakers part 1
		var height = $(".overview_left_content").height() - left_bottom - padding;
		$("#speakers").css({
			'height' : height + 'px',
		});
		
		// Speakers part 2
		var height = $("#speakers").height() - heading_height;
		$("#speakers .inside").css({
			'height' : height + 'px'
		});
		
		// Left bottom width.
		$("#agenda").css({
			"width" :  $("#speakers").width() + "px"			
		});

		// Right bottom width.
		$("#settings").css({
			"width" :  $("#participant_search").width() + "px"
		});

		var $participant_list = $("#participant_list");

		// Calculate height for participant list.
		var height = $(".overview_left_content").height()
			- ( $(".inside", $participant_list ).position().top )
			- padding;
		
		$participant_list.css( 'height', height + 'px' );
		var inside_height = $participant_list.height() - heading_height - $( ".inside ul", $participant_list ).position().top;
		$( ".inside ul", $participant_list ).css( 'height', inside_height + "px" );

	},
	
	// Hook in an event handler
	this.init_search = function ()
	{
		var that = this;
		$("#__participant_search")
			.keyup( function(e)
			{
				return that.search_keyup(e);
			})
			.focus()
			.attr('autocomplete', 'off');
	},

	this.init_settings = function()
	{
		var caller = this;
		var $settings = $( "#settings" );
		var $settings_dialog = undefined;
		
		$settings.click( function()
		{
			$settings_dialog = $( "#settings_dialog" ).dialog({
				'close' : function ( event, ui )
				{
					caller.settings.time_to_speak = $( "#__default_time", $settings_dialog ).val();
				},
				'modal' : true,
				'width' : 700
			});
		});
	},
	
	/**
		Save the order of the this.
	**/
	this.save_order = function( callback )
	{
		var caller = this;
		options = jQuery.extend( true, {}, this.ajaxoptions );

		options.order = [];
		var speakers_to_order = $( ".speakers .speaker_group li.speaker" );
		// No point in trying to save an empty list.
		if( speakers_to_order.length < 1 )
		{
			if ( callback !== undefined )
				callback();
			return;
		}
		$.each( speakers_to_order , function( index, item ){
			options.order[ index ] = $(item).attr('speaker_id');
		});
		options.type = "save_order";
		this.busy( "#speakers" );
		$.post( caller.ajaxurl, options, function(data)
		{
			try
			{
				result = $.parseJSON( data );
				if ( result === null )
					throw "Empty result";
				if ( callback !== undefined )
					callback();
				caller.not_busy( "#speakers" );
			}
			catch ( exception )
			{
			}
		});
	},
	
	/**
		Searches the participant list for a term.
		
		Displays the hits in the ul.
	**/
	this.search = function()
	{
		var caller = this;
		var term = $("#__participant_search").val();

		if ( term == this.settings.search.old_term )
			return;
		
		this.settings.search.old_term = term;

		term = term.toLowerCase();
		
		$("#participant_list .inside li").remove();
		
		$.each( this.settings.participants.data , function(index, item)
		{
			if ( item.toLowerCase().indexOf( term ) !== -1 )
				$("#participant_list .inside ul")
					.append( '<li participant="' + index + '" class="participant">' + item + '</li>' ); 
		});
		
		// Make them double click-able.
		$("#participant_list .inside li").dblclick( function(){
			var participant_id = $(this).attr('participant');
			caller.create_speaker( participant_id );
		});
	},
	
	this.search_keyup = function( e )
	{
		switch( e.which )
		{
			case 8:
				if ( e.shiftKey )
				{
					$("#__participant_search").val( '' );
					this.search();
				}
				break;
			case 13:
				var item = $("#participant_list .inside li");
				if ( item.length == 1 )
				{
					this.create_speaker( $(item).attr( 'participant' ) );
					$("#__participant_search").val('');
					this.search();
				}
				break;
			default:
				this.search();
				break;
		}
	},
	
	this.seconds_to_time = function( secondz )
	{
		hours = String( Math.floor( secondz / 3600 ) );
		minutes = String( Math.floor( ( secondz - ( hours * 3600 ) ) / 60 ) );
		seconds = String( Math.floor( secondz % 60 ) );
		var returnValue = '';
		if ( hours > 0 )
		{
			returnValue += hours + ':';
		}
		
		if ( minutes.length < 2 )
			minutes = '0' + minutes;
		returnValue += minutes + ':';

		if ( seconds < 10 )
			seconds = '0' + seconds;
		returnValue += seconds;
		
		return returnValue;
	},
	
	this.speaking = function()
	{		
		var speaker_id = $("div.speaking").parent().attr( 'speaker_id' );

		// Disable the buttons
		$(".speaker input.time_start").attr( 'disabled', true );

		// Remove the buttons and such.
		$("div.speaker_id_" + speaker_id + " .time_controls_stopped").remove();
		$("div.speaker_id_" + speaker_id + " .time_controls_started").removeClass( 'screen-reader-text' );
		
		var caller = this;
		options = jQuery.extend( true, {}, this.ajaxoptions );

		options.type = "get_speaker";
		$.post( caller.ajaxurl, options, function(data)
		{
			try
			{
				result = $.parseJSON( data );
				caller.settings.current_speaker = $.extend( true, caller.settings.current_speaker, result );
				
				// Start a countdown timer?
				if ( caller.settings.current_speaker.interval_countdown === undefined )
				{
					clearInterval( caller.settings.current_speaker.interval_countdown );
					// Do we have to keep a counter?
					if ( caller.settings.current_speaker.time_to_speak > 0 )
					{
						caller.settings.current_speaker.interval_countdown = setInterval( function()
						{
							caller.speaker_countdown();
						}, 1000 );
					}
				} 
			}
			catch ( exception )
			{
			}
		});
	},
	
	this.speaker_countdown = function()
	{
		var time_spoken = this.settings.current_speaker.time - this.settings.current_speaker.time_start;
		this.settings.current_speaker.time++; 
		
		var speaker_id = $("div.speaking").parent().attr( 'speaker_id' );

		if ( time_spoken > this.settings.current_speaker.time_to_speak )
		{
			$("div.speaker_id_" + speaker_id + " .time_left").addClass( 'speaking_too_long' );
		}

		var time_spoken_display = this.seconds_to_time( time_spoken );
		$("div.speaker_id_" + speaker_id + " .time_left").val( time_spoken_display );
		
	},
	
	this.restop_speaking = function( speaker_id )
	{
		var caller = this;
		options = jQuery.extend( true, {}, this.ajaxoptions );

		options.type = "restop";
		options.speaker_id = speaker_id;
		$.post( caller.ajaxurl, options, function(data)
		{
			try
			{
				result = $.parseJSON( data );
				if ( result === null )
					throw "Empty result";
				
				caller.get_speakers();
			}
			catch ( exception )
			{
			}
		});
	},
	
	this.start_speaking = function( speaker_id )
	{
		var caller = this;
		caller.settings.speakers.expecting_new_hash = true;
		options = jQuery.extend( true, {}, this.ajaxoptions );

		options.type = "start_speaking";
		options.speaker_id = speaker_id;
		$.post( caller.ajaxurl, options, function(data)
		{
			try
			{
				result = $.parseJSON( data );
				if ( result === null )
					throw "Empty result";
				
				// Mark the box as speaking
				$(".speaker_id_" + speaker_id).addClass( 'speaking' );

				caller.speaking();
			}
			catch ( exception )
			{
			}
		});
	},
	
	this.stop_speaking = function( speaker_id )
	{
		clearInterval( this.settings.current_speaker.interval_countdown );
		this.settings.current_speaker = this.new_current_speaker();

		var caller = this;
		options = jQuery.extend( true, {}, this.ajaxoptions );

		options.type = "stop_speaking";
		options.speaker_id = speaker_id;
		$.post( caller.ajaxurl, options, function(data)
		{
			try
			{
				result = $.parseJSON( data );
				if ( result === null )
					throw "Empty result";
				
				caller.get_speakers();
			}
			catch ( exception )
			{
			}
		});
	},
	
	this.time_modify = function( speaker_id, time )
	{
		var caller = this;
		
		// Changing a person's time will cause a new hash to break out.
		// Tell ourselves to expect a new hash and to not update the speaker list, since that's already done.
		caller.settings.speakers.expecting_new_hash = true;

		options = jQuery.extend( true, {}, this.ajaxoptions );

		options.type = "modify_time";
		options.speaker_id = speaker_id;
		options.time = time;
		$.post( caller.ajaxurl, options, function(data)
		{
			try
			{
				result = $.parseJSON( data );
				if ( result === null )
					throw "Empty result";
				// Put the new time in the text box.
				$(".speakers .speaker_id_" + speaker_id + " input.time").val( result.time ); 
			}
			catch ( exception )
			{
			}
		});
	},
	
	this.toggle_agenda_select_button = function()
	{
		var selected_agenda_item_id = $( '#__agenda_items' ).val();
		var current_item_id = this.settings.agenda.data.current_item_id;
		var fade = ( selected_agenda_item_id == current_item_id ? 0 : 1 );
		this.settings.agenda.$change_agenda_item.fadeTo( 250, fade );
	},
	
	/**
		Busy animation.
	**/
	this.busy = function( selector )
	{
		$( selector ).addClass( 'loading ');
	},

	this.not_busy = function( selector )
	{
		$( selector ).removeClass( 'loading ');
	},
	
	/**
		User functions
	**/
	this.init_current_speaker = function ( ajaxoptions )
	{
		this.init( ajaxoptions, {} );
		this.ajaxurl = ajaxoptions.ajaxurl;
		ajaxoptions.ajaxurl = null;
		this.ajaxoptions = $.extend( true, {}, ajaxoptions );
		
		this.settings = {};
		this.settings.current_speaker = this.new_current_speaker();		
		var caller = this;
		this.settings.current_speaker.interval_refresh = setInterval( function(){
			caller.get_current_speaker();
		}, 2000 );
		this.get_current_speaker();
	},
	
	this.get_current_speaker = function ()
	{
		var caller = this;
		options = jQuery.extend( true, {}, this.ajaxoptions );

		options.type = "get_current_speaker";
		options.hash = this.settings.current_speaker.hash;
		$.post( caller.ajaxurl + "?current_speaker", options, function(data)
		{
			try
			{
				result = $.parseJSON( data );
				if ( result === null )
					throw "Empty result";
				
				if ( result.data !== undefined )
				{
					caller.settings.current_speaker.hash = result.hash;
					caller.settings.current_speaker.time_left = parseInt( result.time_left );
					$( "#current_speaker_" + caller.ajaxoptions.div_id ).html( result.data );
					
					// Clear the old timer at any rate.
					clearInterval( caller.settings.current_speaker.interval_time );

					// Maybe start a new timer. The correct span has to exist.
					var $time_left = $( "#current_speaker_" + caller.ajaxoptions.div_id + " div.time_left" );
					if ( $time_left.length > 0 )
					{
						caller.display_time_left( $time_left );
						if ( caller.settings.current_speaker.time_left > 0 )
						{
							caller.settings.current_speaker.interval_time = setInterval( function(){
								caller.settings.current_speaker.time_left--;
								if ( caller.settings.current_speaker.time_left < 0 )
								{
									caller.display_time_left( $time_left );
									clearInterval( caller.settings.current_speaker.interval_time );
									return;
								}
	
								if ( caller.settings.current_speaker.time_left > -1 )
									caller.display_time_left( $time_left );
							}, 1000 );
						}
					}
				}				
			}
			catch ( exception )
			{
			}
		});
	},

	this.init_current_speaker_list = function ( ajaxoptions )
	{
		this.init( ajaxoptions, {} );
		
		this.settings = {};		
		this.settings.current_speaker_list = {};
		this.settings.current_speaker_list.hash = "";
		var caller = this;
		this.settings.current_speaker_list.interval_refresh = setInterval( function(){
			caller.get_current_speaker_list();
		}, 4000 );
		this.get_current_speaker_list();
	},

	this.get_current_speaker_list = function ()
	{
		var caller = this;
		options = jQuery.extend( true, {}, this.ajaxoptions );

		options.type = "get_current_speaker_list";
		options.hash = this.settings.current_speaker_list.hash;
		$.post( caller.ajaxurl + "?current_speaker_list", options, function(data)
		{
			try
			{
				result = $.parseJSON( data );
				if ( result === null )
					throw "Empty result";
				
				if ( result.hash != caller.settings.current_speaker_list.hash )
				{
					caller.settings.current_speaker_list.hash = result.hash;
					$( "#current_speaker_list_" + caller.ajaxoptions.div_id ).html( result.data );

					$("li.speaking").parentsUntil('li.has_spoken').parent().addClass('child_is_speaking');
				}
			}
			catch ( exception )
			{
			}
		});
	},
	
	this.new_current_speaker = function()
	{
		current_speaker = {};
		current_speaker.hash = "";
		return current_speaker;
	}

};
