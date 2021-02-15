const gulp = require( 'gulp' );

const autoprefixer = require( 'gulp-autoprefixer' );
const cleanCSS     = require( 'gulp-clean-css' );
const rename       = require( 'gulp-rename' );
const sass         = require( 'gulp-sass' );
const sourcemaps   = require( 'gulp-sourcemaps' );
const uglifyEs     = require( 'gulp-uglify-es' ).default;
const zip          = require( 'gulp-zip' );

// Directories
var dir_assets = 'modules/*/assets/';

/**
 * TASK: styles
 */
function styles() {
    const rename_style = (path) => {
        path.dirname   = path.dirname.replace( '\/scss', '\/build\/css' );
        path.extname   = '.min.css';
    };

    return gulp.src( dir_assets + 'scss/*.scss' )
        .pipe( sourcemaps.init() )
        .pipe( sass().on( 'error', sass.logError ) )
        .pipe( autoprefixer() )
        .pipe( rename( rename_style ) )
        .pipe( gulp.dest( 'modules' ) );
}

gulp.task( 'styles', styles );

/**
 * TASK: scripts
 */
function scripts() {
    const rename_scripts = (path) => {
        path.dirname   = path.dirname.replace( '\/js', '\/build\/js' );
        path.extname   = '.min.js';
    };

    return gulp.src( dir_assets + 'js/*.js' )
        .pipe( rename( rename_scripts ) )
        .pipe( uglifyEs() )
        .pipe( gulp.dest( 'modules' ) );
}

gulp.task( 'scripts', scripts );

/**
 * TASK: watch
 *
 * Keep watching for changes in directories to automate tasks
 */

function watch() {
    gulp.watch( dir_assets + 'scss/*.scss', gulp.series( 'styles' ) );
    gulp.watch( dir_assets + 'js/*.js', gulp.series( 'scripts' ) );
}

gulp.task( 'watch', watch );

/**
 * TASK: default
 *
 * Run tasks and generate a ZIP to be published
 */
var trunk_files = [
    './**/*',
    '!./modules/*/assets/scss/*',
    '!./modules/*/assets/scss',
    '!./modules/*/assets/js/*',
    '!./modules/*/assets/js',
    '!node_modules/**/*',
    '!vendor/**/*',
    '!*',
    './index.php',
    './LICENSE.txt',
    './readme.txt',
    './shipping-loggi-for-woocommerce.php'
];

function build() {
    return gulp.src( trunk_files )
        .pipe( zip( 'trunk.zip' ) )
        .pipe( gulp.dest( '.' ) );
}

gulp.task( 'default', gulp.series( styles, scripts, build ) );
