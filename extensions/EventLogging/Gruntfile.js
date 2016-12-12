/*jshint node:true */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-jscs' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );

	grunt.initConfig( {
		jshint: {
			options: {
				jshintrc: true
			},
			all: '.'
		},
		jscs: {
			all: [
				'*.js',
				'{modules,tests}/**/*.js'
			]
		},
		jsonlint: {
			all: [
				'**/*.json',
				'!node_modules/**'
			]
		},
		banana: {
			core: 'i18n/core/',
			jsonschema: 'i18n/jsonschema/'
		}
	} );

	grunt.registerTask( 'test', [ 'jshint', 'jscs', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
