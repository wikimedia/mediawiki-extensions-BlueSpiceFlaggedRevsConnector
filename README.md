This package requires third party extensions. They need to be installed the regulat way in `<mediawiki>/extensions` directory

## FlaggedRevs

Using git (in your mediawiki webroot):

    git clone -b REL1_27 https://github.com/wikimedia/mediawiki-extensions-FlaggedRevs.git extensions/FlaggedRevs

Using `Special:ExtensionDistributor` on mediawiki.org

    https://www.mediawiki.org/wiki/Special:ExtensionDistributor/FlaggedRevs

or

    wget https://extdist.wmflabs.org/dist/extensions/FlaggedRevs-REL1_27-2723016.tar.gz


# How to turn on ReadConfirmation after review successfully finished

Add into LocalSettings.php the following line of code:

`$bsgReadConfirmationMechanism = '\\BlueSpice\\FlaggedRevsConnector\\ReadConfirmation\\Mechanism\\PageApproved::factory';`