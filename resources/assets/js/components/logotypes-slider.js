(function() {
    $('.js-logotypes-slider').each(function () {
        var logotypesSlider = $(this).bxSlider({
            infiniteLoop: true,
            pager: false,
            controls: false
        });

        $(this).closest('.logotypes-block-slider').find('.js-logotypes-slider-next').click(function(){
            logotypesSlider.goToNextSlide();
            return false;
        });

        $(this).closest('.logotypes-block-slider').find('.js-logotypes-slider-prev').click(function(){
            logotypesSlider.goToPrevSlide();
            return false;
        });

        $(window).resize(function(){
            logotypesSlider.reloadSlider();
        });
    });
})();