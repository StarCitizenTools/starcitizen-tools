# MediaWiki WikiSEO extension

**This is not a drop-in replacement for v1.2.2 (the latest before this fork)**  
See section **Changes to v1.2.2**  

This is a simple MediaWiki extension to give you control over the HTML title 
and meta tags via a tag or parser function.  

## Steps to take

### Installation
You can get the extension via Git (specifying WikiSEO as the destination directory):

    git clone https://github.com/octfx/wiki-seo.git WikiSEO

Or [download it as zip archive](https://github.com/octfx/wiki-seo/archive/master.zip).

In either case, the "WikiSEO" extension should end up in the "extensions" directory 
of your MediaWiki installation. If you got the zip archive, you will need to put it 
into a directory called WikiSEO.

Add the following line to the end of your LocalSettings file:

    wfLoadExtension( 'WikiSEO' );

## Usage
Use this extension as described [on the extensions documentation page](https://www.mediawiki.org/wiki/Extension:WikiSEO).

## Changes to v1.2.2
Minimum required MediaWiki version is: 1.27.0  

Added features:
* $wgBingSiteVerificationKey
  * Key for Bing WebmasterTools, adds ``msvalidate.01`` meta tag
* $wgMetadataGenerators
  * Array of metadata generators to use, default: [OpenGraph, Twitter, JsonLD]

Removed tags:
* DC.date.created
* DC.date.issued
* google
* googlebot
* name
* og:title (automatically set)
* og:url (automatically set)
* twitter:card (automatically set)
* twitter:creator
* twitter:domain

Removed configs:
* $wgFacebookAdminIds (use $wgFacebookAppId instead)

Removed aliases:
* metakeywords / metak
  * use keywords instead
* metadescription / metad
  * use description instead
* titlemode / title mode
  * use title_mode instead

Changed tags:
* article:author -> author
* article:section -> section
* article:tag -> keywords
* article:published_time -> published_time
* article:modified_time / og:updated_time -> modified_time
* og:image / twitter:image:src -> image
* og:image:width -> image_width
* og:image:height -> image_height
* og:locale -> locale
* og:site_name -> site_name
* og:title -> title
* og:type -> type
* twitter:description -> description


## Extending this extension
Metadata generators live in the ``includes/Generator/Plugins`` directory.  
A generator has to implement the ``GeneratorInterface``.  
To load the generator simply add its name to ``$wgMetadataGenerators``.
