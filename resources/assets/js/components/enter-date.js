(function() {
    $('[data-enter-date]').on('click', function() {
    	var row = $(this).parents('.cabinet-risks-recommendation');
    	row.find('.cabinet-risks-recommendation-default-state').hide();
    	row.find('.cabinet-risks-recommendation-enter-date-state').show();
    	return false;
    });

    $('[data-save-date]').on('click', function() {
    	var procedure = $(this).data('save-date');
    	var row = $(this).parents('.cabinet-risks-recommendation');
    	var date = row.find('.cabinet-risks-recommendation-enter-date input').val();

    	if (! date) {
			row.find('.cabinet-risks-recommendation-enter-date input').focus();
			return false;  		
    	}
    	
    	$.ajax({
    		method: 'POST',
    		url: '/procedure',
    		dataType: 'json',
    		data: {
    			date: date,
    			procedure: procedure,
    			_token: $('input[name=_token]').val()
    		},
    		success: function(response) {
    			row.find('.cabinet-risks-recommendation-date').text(response.nextDate);
    			row.find('.cabinet-risks-recommendation-enter-date-state').hide();
    			row.find('.cabinet-risks-recommendation-default-state').show();
    		}
    	});

    	return false;
    })
})();