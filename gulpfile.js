/*
MIT License

Copyright (c) 2017 Jorge Matricali

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
*/

var fs = require('fs')
var gulp = require('gulp')
var del = require('del')
var inject = require('gulp-inject-string')
var sequence = require('run-sequence')
var exec = require('gulp-exec')
var uglify = require('gulp-uglify')
var htmlmin = require('gulp-htmlmin')
var cleanCSS = require('gulp-clean-css')
var rename = require('gulp-rename')

var dir = {
  dist: './dist'
}
var distFilename = 'x3n4.php'

gulp.task('copy', function () {
  gulp.src('x3n4.core.php').pipe(gulp.dest(dir.dist))
  gulp.src('x3n4.template.php').pipe(gulp.dest(dir.dist))
  gulp.src('x3n4.css').pipe(gulp.dest(dir.dist))
  gulp.src('x3n4.js').pipe(gulp.dest(dir.dist))
})

gulp.task('minify', function () {
  gulp.src('x3n4.js')
    .pipe(uglify())
    .pipe(gulp.dest(dir.dist))
  gulp.src('x3n4.css')
    .pipe(cleanCSS({compatibility: 'ie8'}))
    .pipe(gulp.dest(dir.dist))
  gulp.src('x3n4.core.php')
    .pipe(exec('php -r "echo php_strip_whitespace(\'<%= file.path %>\');"', {pipeStdout: true}))
    .pipe(exec.reporter({stdout: false}))
    .pipe(gulp.dest(dir.dist))
  return gulp.src('x3n4.template.php')
      .pipe(htmlmin({collapseWhitespace: true}))
      .pipe(gulp.dest(dir.dist))
})

gulp.task('merge', ['minify'], function () {
  var x3n4_css = fs.readFileSync(dir.dist + '/x3n4.css', 'utf8')
  var x3n4_js = fs.readFileSync(dir.dist + '/x3n4.js', 'utf8')
  var x3n4_core = fs.readFileSync(dir.dist + '/x3n4.core.php', 'utf8') + ' ?>'

  gulp.src(dir.dist + '/x3n4.template.php')
    .pipe(inject.replace(
      '<\\?php include\\(\'x3n4\\.core\\.php\'\\); \\?>',
      x3n4_core
    ))
    .pipe(inject.replace(
      '<\\?php readfile\\(\'x3n4\\.js\'\\); \\?>',
      x3n4_js
    ))
    .pipe(inject.replace(
      '<\\?php readfile\\(\'x3n4\\.css\'\\); \\?>',
      x3n4_css
    ))
    .pipe(rename(distFilename))
    .pipe(gulp.dest(dir.dist))
})

gulp.task('clean', function () {
  return del([
    dir.dist + '/x3n4.core.php',
    dir.dist + '/x3n4.template.php',
    dir.dist + '/x3n4.js',
    dir.dist + '/x3n4.css'
  ])
})

gulp.task('build', function (callback) {
  console.log('Building...')
  sequence(['clean'], ['minify'], ['merge'], callback)
})

gulp.task('default', ['build'])
