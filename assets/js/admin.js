jQuery( document ).ready( function( $ ) {
	 console.log("I am here");
	 var syncedProducts = bizappSettings.syncedProducts;
	 console.log(syncedProducts);
	
    var checkboxes = $('input[type="checkbox"][name="bizapp[products][]"]');
    checkboxes.each(function() {
        var sku = $(this).val();
        if (syncedProducts.includes(sku)) {
            $(this).prop('checked', true).prop('disabled', true);
        }
    });
    // Prepend Select All checkbox
    $( '.bizapp-woocommerce-settings .csf-field-checkbox ul' ).prepend( '<li><label><input type="checkbox" class="csf-checkbox-check"><span class="csf--text">Select All</span></label></li>' );

    // Toggle the checkbox
    $( '.csf-checkbox-check' ).on( 'click', function() {
        $( this ).closest( '.csf-field-checkbox' ).find( 'input:checkbox' ).prop('checked', $( this ).prop( 'checked' ) );
    } );
} );
