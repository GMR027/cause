'use strict';

const { src, dest, watch, series, parallel } = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const cleanCss = require('gulp-clean-css');
const rename = require('gulp-rename');
const concat = require('gulp-concat');
const terser = require('gulp-terser');
const browserSync = require('browser-sync').create();

let imageminPlugin = null;

const paths = {
    scss: 'assets/scss/**/*.scss',
    cssDir: 'public/assets/css',
    jsVendor: [
        'node_modules/bootstrap/dist/js/bootstrap.bundle.min.js',
    ],
    jsApp: [
        'assets/js/00-core.js',
        'assets/js/10-layout.js',
        'assets/js/15-help.js',
        'assets/js/20-theme.js',
        'assets/js/30-onboarding.js',
        'assets/js/40-settings.js',
        'assets/js/50-income.js',
        'assets/js/60-expenses.js',
        'assets/js/70-finance.js',
        'assets/js/75-moves.js',
        'assets/js/80-notifications.js',
        'assets/js/85-wishlist.js',
        'assets/js/88-debts.js',
        'assets/js/89-reports.js',
        'assets/js/90-calendar.js',
        'assets/js/91-lessons.js',
        'assets/js/95-progress.js',
    ],
    jsDir: 'public/assets/js',
    qrSrc: 'node_modules/qrcode-generator/qrcode.js',
    qrDir: 'public/assets/js',
    imgSrc: 'assets/img/**/*.{jpg,jpeg,png,gif,svg,webp}',
    imgDir: 'public/assets/img',
    iconsCss: 'node_modules/bootstrap-icons/font/bootstrap-icons.min.css',
    iconsFonts: 'node_modules/bootstrap-icons/font/fonts/*',
    iconsDir: 'public/assets/icons',
    php: ['app/**/*.php', 'public/*.php'],
};

function styles() {
    return src('assets/scss/app.scss')
        .pipe(
            sass({
                includePaths: ['node_modules'],
                quietDeps: true,
                // Bootstrap 5.3 aún distribuye SCSS con @import y gulp-sass 5
                // usa la API legacy: silenciamos solo esos avisos conocidos.
                silenceDeprecations: ['legacy-js-api', 'import'],
            }).on('error', sass.logError)
        )
        .pipe(dest(paths.cssDir));
}

function css() {
    return src(`${paths.cssDir}/app.css`)
        .pipe(cleanCss({ level: 2 }))
        .pipe(rename({ suffix: '.min' }))
        .pipe(dest(paths.cssDir));
}

function scripts() {
    return src([...paths.jsVendor, ...paths.jsApp], { allowEmpty: true })
        .pipe(concat('app.min.js'))
        .pipe(terser())
        .pipe(dest(paths.jsDir));
}

function qr() {
    return src(paths.qrSrc)
        .pipe(rename('qrcode.js'))
        .pipe(dest(paths.qrDir));
}

function iconsCss() {
    return src(paths.iconsCss, { allowEmpty: true }).pipe(dest(paths.iconsDir));
}

function iconsFonts() {
    return src(paths.iconsFonts, { allowEmpty: true }).pipe(dest(`${paths.iconsDir}/fonts`));
}

const icons = series(iconsCss, iconsFonts);

async function images() {
    // gulp-imagemin 8 es ESM: se importa dinámicamente para mantener el
    // gulpfile en CommonJS y ser compatible con Node 18+.
    if (imageminPlugin === null) {
        imageminPlugin = (await import('gulp-imagemin')).default;
    }

    return src(paths.imgSrc, { allowEmpty: true })
        .pipe(imageminPlugin())
        .pipe(dest(paths.imgDir));
}

function reload(done) {
    if (browserSync.active) {
        browserSync.reload();
    }

    done();
}

function serve(done) {
    browserSync.init(
        {
            proxy: process.env.BROWSER_SYNC_PROXY || 'http://localhost:8000',
            port: Number(process.env.BROWSER_SYNC_PORT || 3000),
            open: false,
            notify: false,
            ui: false,
        },
        done
    );
}

function watchFiles() {
    watch(paths.scss, series(styles, css, reload));
    watch(paths.jsApp, series(scripts, reload));
    watch(paths.imgSrc, series(images, reload));
    watch(paths.php, reload);
}

const build = series(styles, css, scripts, qr, images, icons);
const dev = series(build, parallel(watchFiles, serve));

exports.styles = styles;
exports.css = css;
exports.js = scripts;
exports.images = images;
exports.icons = icons;
exports.qr = qr;
exports.watch = watchFiles;
exports.serve = serve;
exports.build = build;
exports.default = dev;
