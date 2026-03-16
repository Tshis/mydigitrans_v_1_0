const {src,dest,watch,gulp} = require('gulp');
const minifyJs = require('gulp-uglify');

const devJs = ()=>{
    return src('./assets/js/**/*.js')
    .pipe(minifyJs())
    .pipe(dest('./public/js/'));
}

const devWatch = ()=>{
    watch('./assets/**/*.js',devJs);
}

exports.devJs = devJs;
exports.devWatch = devWatch;