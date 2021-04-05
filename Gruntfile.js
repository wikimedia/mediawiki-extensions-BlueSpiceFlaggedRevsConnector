/* eslint-env node, es6 */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	// var conf = grunt.file.readJSON( 'extension.json' );
	grunt.initConfig( {
	// banana: conf.MessagesDirs,
		eslint: {
			options: {
				cache: true
			},
			src: [
				'**/*.{js,json}',
				'!{vendor,node_modules}/**'
			]
		},
		stylelint: {
			all: [
				'**/*.css',
				'!**/*.generated.css',
				'!vendor/**',
				'!node_modules/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'stylelint', 'eslint' ] );
	grunt.registerTask( 'default', 'test' );
};
