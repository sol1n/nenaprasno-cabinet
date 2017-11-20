(function() {
    //https://chmln.github.io/flatpickr/

    $('[data-flatpickr]').each(function() {
        var options = {
                locale: 'ru',
                dateFormat: 'd.m.Y'
            },
            altOptions = $(this).data('flatpickr');

        for (var option in altOptions) {
            options[option] = altOptions[option];
        }

        $(this).flatpickr(options);
    })
})();
