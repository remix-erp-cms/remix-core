const mix = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.styles([
    '../assets/plugins/fontawesome-free/css/all.min.css',
    '../assets/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css',
    '../assets/plugins/icheck-bootstrap/icheck-bootstrap.min.css',
    '../assets/plugins/overlayScrollbars/css/OverlayScrollbars.min.css',
    '../assets/plugins/daterangepicker/daterangepicker.css',
    '../assets/plugins/summernote/summernote-bs4.css',
    '../assets/plugins/ion-rangeslider/css/ion.rangeSlider.min.css',
    '../assets/plugins/bootstrap-slider/css/bootstrap-slider.min.css',
    '../assets/plugins/bootstrap-tag/css/bootstrap-tagsinput.css',
    '../assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css',
    '../assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css',
    '../assets/plugins/ekko-lightbox/ekko-lightbox.css',
    '../assets/plugins/jqvmap/jqvmap.min.css',
    '../assets/plugins/sweet2/sweet2.css',
    '../assets/plugins/waitme/css/waitMe.min.css',
    '../assets/plugins/app/css/animate.css',
    '../assets/plugins/app/css/adminlte.min.css',
], '../assets/mix/css/plugin.css')
    .options({
        processCssUrls: true
    });

mix.scripts([
    '../assets/plugins/jquery/jquery.min.js',
    '../assets/plugins/jquery-ui/jquery-ui.min.js',
    '../assets/plugins/jquery.cookie/js/jquery.cookie.js',
    '../assets/plugins/bootstrap/js/bootstrap.bundle.min.js',
    '../assets/plugins/bootstrap-notify/bootstrap-notify.js',
    '../assets/plugins/moment/moment.min.js',
    '../assets/plugins/daterangepicker/daterangepicker.js',
    '../assets/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js',
    '../assets/plugins/summernote/summernote-bs4.min.js',
    '../assets/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js',
    '../assets/js/adminlte.js',
    '../assets/plugins/ion-rangeslider/js/ion.rangeSlider.min.js',
    '../assets/plugins/bootstrap-slider/bootstrap-slider.min.js',
    '../assets/plugins/bootstrap-tag/js/bootstrap-tagsinput.js',
    '../assets/plugins/ekko-lightbox/ekko-lightbox.min.js',
    '../assets/plugins/filterizr/jquery.filterizr.min.js',
    '../assets/plugins/datatables/jquery.dataTables.min.js',
    '../assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js',
    '../assets/plugins/datatables-responsive/js/dataTables.responsive.min.js',
    '../assets/plugins/datatables-responsive/js/responsive.bootstrap4.js',
    '../assets/plugins/parsleyjs/js/parsley.js',
    '../assets/plugins/waitme/js/waitMe.min.js',
    '../assets/plugins/sweet2/sweet2.js',
], '../assets/mix/js/plugin.js');

mix.styles([
    '../assets/plugins/bootstrap/css/bootstrap.css',
    '../assets/plugins/owl-carousel/owl.carousel.css',
    '../assets/plugins/owl-carousel/owl.theme.default.css',
    '../assets/plugins/fontawesome-free/css/all.min.css',
], '../assets/mix/css/common.css')
    .options({
        processCssUrls: true
    });

mix.styles([
    '../assets/plugins/app/css/flaticon.css',
    '../assets/plugins/app/css/slick.css',
    '../assets/plugins/app/css/slick-theme.css',
    '../assets/plugins/app/css/swiper.min.css',
    '../assets/plugins/app/css/jquery.fancybox.css',
    '../assets/plugins/app/css/odometer-theme-default.css',
    '../assets/plugins/app/css/themify-icons.css',
], '../assets/mix/css/blockchain.css')
    .options({
        processCssUrls: true
    });

mix.scripts([
    '../assets/plugins/jquery/jquery.min.js',
    '../assets/plugins/bootstrap/js/bootstrap.bundle.min.js',
    '../assets/plugins/jquery-lazy/jquery.lazy.min.js',
    '../assets/plugins/moment/moment.min.js',
    '../assets/plugins/analytic/analytic.js',
    '../assets/plugins/owl-carousel/device.js',
    '../assets/plugins/owl-carousel/owl.carousel.min.js',
    '../assets/plugins/notify/notify.js',
], '../assets/mix/js/common.js');

mix.copyDirectory('../assets/plugins/fontawesome-free/webfonts', '../assets/mix/webfonts');
mix.copyDirectory('../assets/plugins/summernote/font', '../assets/mix/css/font');
mix.copyDirectory('../assets/plugins/app/fonts', '../assets/mix/css/font');

