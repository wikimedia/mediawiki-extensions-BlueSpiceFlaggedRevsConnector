/* eslint-env node, es6 */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-jsonlint' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	// var conf = grunt.file.readJSON( 'extension.json' );
	grunt.initConfig( {
	// banana: conf.MessagesDirs,
		jsonlint: {
			all: [
				'**/*.json',
				'!node_modules/**',
				'!vendor/**'
			]
		},
		eslint: {
			recommended: {
				options: {
					extensions: [ '.js' ],
					cache: true
				},
				src: [
					'**/*.js',
					'!{vendor,node_modules}/**'
				]
			},
			cc: {
				options: {
					configFile: '.eslintrc_cc.json',
					extensions: [ '.js' ],
					cache: true
				},
				src: [
					'**/*.js',
					'!{vendor,node_modules}/**'
				]
			}
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

	grunt.registerTask( 'test', [ 'jsonlint', 'stylelint', 'eslint:recommended' ] );
	grunt.registerTask( 'testcc', [ 'eslint:cc' ] );
	grunt.registerTask( 'default', 'test' );
};
