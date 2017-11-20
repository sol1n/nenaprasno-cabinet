(function() {
    $('.js-header-slider').each(function () {
        var headerSlider = $(this).bxSlider({
            infiniteLoop: true,
            pager: false,
            controls: false
        });

        $('.js-header-slider-next').click(function(){
            headerSlider.goToNextSlide();
            return false;
        });

        $('.js-header-slider-prev').click(function(){
            headerSlider.goToPrevSlide();
            return false;
        });

        $(window).resize(function(){
            headerSlider.reloadSlider();
        });
    });

    $('.js-header-about-slider').each(function () {
        var headerSliderAbout = $(this).bxSlider({
            infiniteLoop: true,
            pager: false,
            controls: true
        });

        $(window).resize(function(){
            headerSliderAbout.reloadSlider();
        });
    });
})();