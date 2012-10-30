var sd_mt_actions;
(function($) {

sd_mt_actions = actions = {
	/**
		Generic init function.
	**/
	init : function( ajaxoptions ){
		actions.ajaxoptions = $.extend( true, {}, ajaxoptions );
		actions.settings = {};
	},
	
	/**
		Generic admin init.
	**/
	init_admin : function( ajaxoptions ){
		actions.init( ajaxoptions );
		
		// Enable sorting of the action items.
		$('table.sd_mt_actions tbody').sortable({
			helper: 'clone',
			//handle: '> td.cb',
			stop: function(e,ui) {
				actions.save_item_order();
			}
		});
	},
	
	save_item_order : function()
	{		
		options = actions.ajaxoptions;
		options.type = "action_items_reorder";
		options.order = [];
		$.each( $('table.sd_mt_actions tbody th.check-column input'), function (index, item){
			options.order[ index ] = $(item).parentsUntil('tr').parent().attr( 'action_item_id' );
		});
		var busy_selector = $( 'table.sd_mt_actions' );
		sd_mt.busy( busy_selector );
		$.post( ajaxurl, options, function(data){
			sd_mt.not_busy( busy_selector );
		}, 'json' );
	},
	
	sort_items : function() {
	}
	
};

$(document).ready(function($){ sd_mt_actions.sort_items(); });

})(jQuery);
