$(document).ready(function () {
    $('[data-toggle]').toggler();
    $('[data-inputmask]').inputmask();
    tippy('[data-tooltip]', {
        theme: 'light'
    });
    $('#test-again-btn').click(function() {
        $('#test-again-modal').removeClass('hidden');
        setTimeout(function() {
            $('#test-again-modal').addClass('show');
        }, 50)

    })
    $('#test-again-close').click(function() {
        $('#test-again-modal').removeClass('show');
        setTimeout(function() {
            $('#test-again-modal').addClass('hidden');
        }, 100)
    });

    $('[data-html]').click(function() {
        var elemntId = $(this).data('html');
        $('#' + elemntId).toggle();
    });
});