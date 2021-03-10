/*!
 * Grunt file
 *
 * @package Thanks
 */

/* eslint-env node, es6 */
module.exports = function ( grunt ) {

	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-jsonlint' );

	grunt.initConfig( {
		eslint: {
			all: [
				'*.js',
				'modules/**/*.js',
				'tests/qunit/**/*.js'
			]
		},
		banana: {
			all: 'i18n/'
		},
		watch: {
			files: [
				'.eslintrc.json',
				'<%= eslint.all %>'
			],
			tasks: 'test'
		},
		jsonlint: {
			all: [
				'*.json',
				'i18n/*.json'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'jsonlint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
