function sd_meeting_tool_agendas()
{
	$ = jQuery;
	
	/**
		Make the agenda item clickable.
	**/
	this.enable_select = function()
	{
		var caller = this;
		$('table.sd_mt_agendas tbody tr').dblclick( function(){
			var object = $(this);
			
			var busy_selector = $( this );
			sd_mt.busy( busy_selector );
			
			if ( $(this).hasClass( 'current_item' ) )
			{
				var current_item_id = 0;
			}
			else
			{
				// Find the ID for this tr
				var cb = $('th.check-column input', this);
				var current_item_id = $(cb).attr('name');
				var current_item_id = current_item_id.replace('items[', '').replace(']','');
			}

			options = jQuery.extend( true, {}, caller.ajaxoptions );
			options.type = "select_current_item";
			options.current_item_id = current_item_id;
	
			$.post( ajaxurl, options, function(data){
				try
				{
					result = sd_mt.parseJSON( data );
					$('table.sd_mt_agendas tbody tr').removeClass( 'current_item' );
					if ( current_item_id > 0 )
						$( object ).addClass( 'current_item' );
					sd_mt.not_busy( busy_selector );
				}
				catch ( exception )
				{
					alert( "JSON Error @ select: " + exception );
				}
			} );
		});
	},
	
	/**
		Generic init function.
	**/
	this.init = function( ajaxoptions, settings )
	{
		this.ajaxoptions = $.extend( true, {}, ajaxoptions );
		this.settings = $.extend( true, {}, settings );
	},
	
	/**
		Make a displayed agenda autorefresh itself.
	**/
	this.init_agenda_autorefresh = function( ajaxoptions, settings )
	{
		this.init( ajaxoptions, settings );
		this.settings.agenda = {		
			"div_id" : settings.div_id,
			"refresh" : 2000,
			"interval" : undefined,
		};
		this.refresh_agenda();
		var caller = this;
		this.settings.agenda.interval = setInterval( function(){
			caller.refresh_agenda();
		}, this.settings.agenda.refresh );
	},
	
	/**
		Make a displayed agenda item autorefresh itself.
	**/
	this.init_agenda_item_autorefresh = function( ajaxoptions, settings )
	{
		this.init( ajaxoptions, settings );
		this.settings.agenda_item = {		
			"refresh" : 2000,
			"interval" : undefined,
		};
		this.refresh_agenda_item();
		var caller = this;
		this.settings.agenda_item.interval = setInterval( function() {
			caller.refresh_agenda_item()
		}, caller.settings.agenda_item.refresh );
	},
	
	/**
		Generic admin init.
	**/
	this.init_admin = function( ajaxoptions, settings )
	{
		this.init( ajaxoptions, settings );
		this.enable_select();
		
		// Enable sorting of the agenda items.
		var caller = this;
		$('table.sd_mt_agendas tbody').sortable({
			helper: 'clone',
			//handle: '> td.cb',
			stop: function(e,ui) {
				caller.save_item_order();
			}
		});
	},
	
	/**
		Generic user init.
	**/
	this.init_user = function( ajaxoptions, settings )
	{
		this.init( ajaxoptions, settings );
	},
	
	this.refresh_agenda = function()
	{
		var caller = this;
		$.ajax({
			'method' : 'get',
			'cache' : false,
			'datatype' : 'html',
			'ifModified' : false,	// Else it will return no text at all, which makes .ajax cough up an "no element found" error.
			'statusCode': {
				200 : function(data){
					$( "div.sd_mt_agenda_" + caller.settings.agenda.div_id + " ol" ).empty().append( data );
				}, 
			},
			'url' : caller.settings.urls.agenda
		});
	},

	this.refresh_agenda_item = function()
	{
		var caller = this;
		try
		{
			$.ajax({
				'method' : 'get',
				'cache' : false,
				'datatype' : 'text',
				'ifModified' : false,
				'statusCode': {
					200 : function(data){
						$( "span." + caller.settings.span_class ).html( data );
					},
				},
				'url' : this.settings.urls.current_agenda_item
			});
		} catch( exception )
		{
		}
	},
	
	/**
		Reads and saves the order of the items.
	**/
	this.save_item_order = function()
	{
		var ids = {};
		$.each( $('table.sd_mt_agendas tbody th.check-column input'), function (index, item){
				var item_id = $(item).attr('name');
				var item_id = item_id.replace('items[', '').replace(']','');
				ids[ index ] = item_id;
		});

		var busy_selector = $('table.sd_mt_agendas tbody tr').first();
		sd_mt.busy( busy_selector );

		options = this.ajaxoptions;
		options.type = "agenda_items_reorder";
		options.order = ids;
		
		$.post( ajaxurl, options, function(data){
			try
			{
				result = sd_mt.parseJSON( data );
				sd_mt.not_busy( busy_selector );
			}
			catch ( exception )
			{
			}
		} );
	}
};
