$(document).ready(function () {
    $('[data-toggle]').toggler();
    $(':input').inputmask();
    tippy('[data-tooltip]', {
        theme: 'light'
    });
});