"use strict";

module.exports = function (grunt) {
  const sass = require("node-sass");

  grunt.initConfig({
    pkg: grunt.file.readJSON("package.json"),
    sass: {
      options: {
        implementation: sass,
        sourceMap: true,
        // outputStyle: "compressed",
      },
      build: {
        files: {
          "css/style.css": "scss/style.scss",
        },
      },
    },
    watch: {
      scss: {
        files: "scss/**/*.scss",
        tasks: "sass",
        atBegin: true,
      },
    },
  });

  grunt.loadNpmTasks("grunt-sass");
  grunt.loadNpmTasks("grunt-contrib-watch");

  grunt.registerTask("default", ["watch"]);
};
