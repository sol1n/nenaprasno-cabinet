var gulp = require('gulp'),
    less = require('gulp-less'),
    cssmin = require('gulp-cssmin'),
    rename = require('gulp-rename'),
    concat = require('gulp-concat'),
    uglify = require('gulp-uglify'),
    autoprefixer = require('gulp-autoprefixer'),
    sourcemaps = require('gulp-sourcemaps');

gulp.task('less', function() {
    return gulp.src('resources/assets/less/style.less')
        .pipe(sourcemaps.init())
            .pipe(less().on('error', function(err) {
                console.log(err);
            }))
            .pipe(autoprefixer({
                browsers: ['last 2 versions'],
                cascade: false
            }))
            .pipe(cssmin().on('error', function(err) {
                console.log(err);
            }))
            .pipe(rename({
                suffix: '.min'
            }))
        .pipe(sourcemaps.write())
        .pipe(gulp.dest('./public/build/'));
});

var jsFiles = [
        './node_modules/jquery/dist/jquery.js',
        './node_modules/bxslider/dist/jquery.bxslider.js',
        './node_modules/@fancyapps/fancybox/dist/jquery.fancybox.js',
        './node_modules/inputmask//dist/jquery.inputmask.bundle.js',
        './node_modules/flatpickr/dist/flatpickr.js',
        './node_modules/flatpickr/dist/l10n/ru.js',
        './node_modules/tippy.js/dist/tippy.js',
        './resources/assets/js/components/**/*.js',
        './resources/assets/js/scripts.js'
    ],
    jsDest = './public/build';

gulp.task('scripts', function() {
    return gulp.src(jsFiles)
        .pipe(sourcemaps.init())
            .pipe(concat('scripts.js'))
            .pipe(gulp.dest(jsDest))
            .pipe(rename('scripts.min.js'))
            .pipe(uglify())
        .pipe(sourcemaps.write())
        .pipe(gulp.dest(jsDest));
});

gulp.task('default', ['less', 'scripts'], function() {
    gulp.watch('./less/**/*.less', ['less']);
    gulp.watch('./js/**/*.js', ['scripts']);
});