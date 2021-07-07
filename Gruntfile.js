/* eslint-env node, es6 */
module.exports = function ( grunt ) {
	var conf;
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	conf = grunt.file.readJSON( 'extension.json' );
	grunt.initConfig( {
		banana: conf.MessagesDirs,
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

	grunt.registerTask( 'test', [ 'stylelint', 'eslint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
