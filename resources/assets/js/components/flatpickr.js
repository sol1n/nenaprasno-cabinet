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

        var untilToday = $(this).data('untiltoday');

        if (untilToday) {
            options['disable'] = [
                function(date) {
                    return date > new Date();
                }
            ];
        }

        $(this).flatpickr(options);
    })
})();
