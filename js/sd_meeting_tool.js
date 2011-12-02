var sd_meeting_tool;

(function($) {

sd_meeting_tool = sd_mt = {
	/**
		Assigned a busy-class to a CSS selector.
	**/
	busy : function( selector )
	{
		$( selector ).addClass( 'sd_mt_busy');
	},
	
	/**
		Removes the busy-class from the selector.
	**/
	not_busy : function( selector )
	{
		$( selector ).removeClass( 'sd_mt_busy');
	},
	
	/**
		Tries to parse some json data.
		
		Throws an exception if it fails or is empty.
	**/
	parseJSON : function ( data )
	{
		json = $.parseJSON( data );
		if ( json === null )
			throw "Empty result";
		return json;
	}
};

})(jQuery);
