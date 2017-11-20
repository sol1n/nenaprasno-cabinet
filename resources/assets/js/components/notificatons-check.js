(function() {
    $('#recommendations-subscribe').on('change', function(){
        var form = $(this).parents('form');

        if (window.recommendationTimeout) {
            clearTimeout(window.recommendationTimeout);
        }

        window.recommendationTimeout = setTimeout(function(){
            form.submit();
        }, 1300);
    })
})();