var sd_mt_list_sorts;
(function($) {

sd_mt_list_sorts = {
	ajaxoptions : {},
	
	save_order : function() {
		var order = {};
		$.each( $('table.sd_mt_list_sort_orders tbody td.name'), function (index, item){
			order[ index ] = $(item).attr('name');
		});
		options = sd_mt_list_sorts.ajaxoptions;
		options.type = 'reorder_orders';
		options.order = order;

		var busy_selector = $( 'table.sd_mt_list_sort_orders tr' );
		sd_mt.busy( busy_selector );

		$.post( ajaxurl, options, function(data){
			sd_mt.not_busy( busy_selector );
		}, 'json' );
	},
	
	init : function( ajaxoptions )
	{
		sd_mt_list_sorts.ajaxoptions = $.extend( true, {}, ajaxoptions );

		$('table.sd_mt_list_sort_orders tbody').sortable({
			helper: 'clone',
			//handle: '> td.cb',
			stop: function(e,ui) {
				sd_mt_list_sorts.save_order();
			}
		});

		$('table.sd_mt_list_sort_orders tbody td.ascending').dblclick( function(){
			var field = $( "td.name", $(this).parent() ).attr('name');
			
			options = ajaxoptions;
			options.type = 'switch_ascending';
			options.field = field;

			$.post( ajaxurl, options, function(data){
				window.location.reload()
			} );
		});
	},
};

})(jQuery);
