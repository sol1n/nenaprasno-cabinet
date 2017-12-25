const { mix } = require('laravel-mix');

/*
 |--------------------------------------------------------------------------
 | mix asset management
 |--------------------------------------------------------------------------
 |
 | mix provides a clean, fluent api for defining some webpack build steps
 | for your laravel application. by default, we are compiling the sass
 | file for the application as well as bundling up all the js files.
 |
 */

// mix.js('resources/assets/js/files.js', 'public/js');
// mix.js('resources/assets/js/files-upload.js', 'public/js');
// mix.js('resources/assets/js/permissions.js', 'public/js');
// mix.js('resources/assets/js/push.js', 'public/js');
// mix.js('resources/assets/js/app.js', 'public/js')
//    .sass('resources/assets/sass/app.scss', 'public/css');

//mix.js('resources/assets/js/meetings/meetings.js', 'public/js');

mix.js('resources/assets/vue/restore.js', 'public/js');