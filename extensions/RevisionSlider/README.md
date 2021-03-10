# RevisionSlider extension

This MediaWiki extension shows a slider allowing selecting and comparing of revisions on a diff page

## Installation
Check out this extension into the `extensions` folder of your MediaWiki installation and add the following line to your `LocalSettings.php`:

    wfLoadExtension( 'RevisionSlider' );

## Tests
Before executing tests run the following in the root directory of the extension once:

    composer install
    npm install

For the tests run:

    composer test
    node_modules/.bin/grunt test

## QUnit Tests
See https://www.mediawiki.org/wiki/Manual:JavaScript_unit_testing#Run_the_tests