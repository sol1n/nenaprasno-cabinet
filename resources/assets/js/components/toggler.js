(function( $ ) {
    $.fn.toggler = function() {
        this.each(function() {
            $(this).on('click', function (e) {
                var toggleTarget = $(this).data('toggle') || $(this).attr('href'),
                    toggleClass = $(this).data('toggle-class') || 'active',
                    toggleGroup = $(this).data('toggle-group'),
                    toggleSelfDeactive = $(this).is('[data-toggle-self-deactive]');

                console.log(toggleTarget, toggleClass, toggleGroup);

                $('[data-toggle-group="' + toggleGroup + '"]').not(this).each(function () {
                    var target = $(this).data('toggle') || $(this).attr('href'),
                        targetClass = $(this).data('toggle-class') || 'active';

                    $(target).removeClass(targetClass);
                    $(this).removeClass(targetClass);
                });

                if ($(this).hasClass(toggleClass) && toggleSelfDeactive) {
                    $(this).removeClass(toggleClass);
                    $(toggleTarget).removeClass(toggleClass);
                } else {
                    $(this).addClass(toggleClass);
                    $(toggleTarget).addClass(toggleClass);
                }

                return false;
            });
        });

        return this;
    };
}( jQuery ));