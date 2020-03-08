jQuery(document).ready(

    function($) {

        // init Isotope
        var $grid = $('.wp-unsplash-profile-grid').isotope({
            itemSelector: '.wp-unsplash-profile-element-item',
            percentPosition: true,
            layoutMode: 'masonry'
        });

        $grid.imagesLoaded().progress( function() {
            $grid.isotope('layout');
        });

        var $lg = $('#lightgallery');


        $lg.lightGallery({
            selector: '.wp-unsplash-profile-anchor',
        });

        /*
            This is intended to follow Unsplash API guidelines:
            https://help.unsplash.com/en/articles/2511245-unsplash-api-guidelines
            Please do not remove it. It could cause application ban from the API.
         */
        $(document).on('contextmenu', 'img', function() {
            return false;
        })

    }
);
