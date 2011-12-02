jQuery.fn.extend({
    insertAtCaret: function(valueToInsertAtCaret){
        return this.each( function(i) {
            if ( document.selection ) {
                this.focus();
                selection = document.selection.createRange();
                selection.text = valueToInsertAtCaret;
                this.focus();
            } else if ( this.selectionStart || this.selectionStart == "0" ) {
                var startPosition = this.selectionStart;
                var endPosition = this.selectionEnd;
                var scrollTop = this.scrollTop;
                this.value = this.value.substring(0, startPosition) + valueToInsertAtCaret + this.value.substring(endPosition, this.value.length);
                this.focus();
                this.selectionStart = startPosition + valueToInsertAtCaret.length;
                this.selectionEnd = startPosition + valueToInsertAtCaret.length;
                this.scrollTop = scrollTop;
            } else {
                this.value += valueToInsertAtCaret;
                this.focus();
            }
        })
    }
});
jQuery(document).ready(function($){
	
	// Make the keywords doubleclickable
	$("table.keywords td.keyword").dblclick( function(){
		var value = $(this).text();
		$("#__display_format").insertAtCaret( value );
		$("#__display_format").focus();
	});
});
