/*jshint node:true */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-jscs' );

	grunt.initConfig( {
		banana: {
			all: 'i18n/'
		},
		jsonlint: {
			all: [
				'**/*.json',
				'!node_modules/**'
			]
		},
		jshint: {
			all: [
				'*.js'
			]
		},
		jscs: {
			src: '<%= jshint.all %>'
		}
	} );

	grunt.registerTask( 'test', [ 'jsonlint', 'banana', 'jshint', 'jscs' ] );
	grunt.registerTask( 'default', 'test' );
};
