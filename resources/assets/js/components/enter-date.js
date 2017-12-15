(function() {
    var showDateState = function(button) {
        var row = $(button).parents('.cabinet-risks-recommendation');
        row.find('.cabinet-risks-recommendation-default-state').hide();
        row.find('.cabinet-risks-recommendation-enter-date-state').show();

        return false;
    }

    var showDefaultState = function(button) {
        var row = $(button).parents('.cabinet-risks-recommendation');
        row.find('.cabinet-risks-recommendation-default-state').show();
        row.find('.cabinet-risks-recommendation-enter-date-state').hide();

        return false;
    }

    $('[data-enter-date]:enabled').on('click', function(){
        return showDateState(this);
    });

    $('[data-save-date]').on('click', function() {
        var button = this;
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
    			showDefaultState(button);
    		}
    	});

    	return false;
    });

    $('[data-close-enter-date]').on('click', function(){
        return showDefaultState(this);
    });
})();