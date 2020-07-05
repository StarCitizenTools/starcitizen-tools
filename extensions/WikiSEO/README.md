# MediaWiki WikiSEO extension

**Version 2.0 is not a drop-in replacement for v1.2.2 (the last version before this fork).**  

The WikiSEO extension allows you to replace, append or prepend the html title tag content, and allows you to add common SEO meta keywords and a meta description.  

**Extension Page: [Extension:WikiSEO](https://www.mediawiki.org/wiki/Extension:WikiSEO)**

## Installation
* [Download](https://www.mediawiki.org/wiki/Special:ExtensionDistributor/WikiSEO) and place the file(s) in a directory called WikiSEO in your extensions/ folder.
* Add the following code at the bottom of your [LocalSettings.php](https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:LocalSettings.php):
```
wfLoadExtension( 'WikiSEO' );
```
* Configure as required.
* Done – Navigate to Special:Version on your wiki to verify that the extension is successfully installed.

## Configuration
The following variables are in use by this extension.

### $wgWikiSeoDefaultImage
Set a default image to use if no image is set on the site. If this variable is not set the sites logo will be used.  
Usage: $wgWikiSeoDefaultImage = 'File:Localfile.jpg';.

### $wgGoogleSiteVerificationKey
Setting this variable will add a ``<meta name="google-site-verification" content="CODE">`` tag to every page.  
Usage: $wgGoogleSiteVerificationKey = 'CODE';.

### $wgBingSiteVerificationKey
Setting this variable will add a ``<meta name="msvalidate.01" content="CODE">`` tag to every page.  
Usage: $wgBingSiteVerificationKey= 'CODE';.

### $wgFacebookAppID
Setting this variable will add a ``<meta property="fb:app_id" content="ID">`` tag to every page.  
Usage: $wgFacebookAppID= 'App_ID';.

### $wgFacebookAdmins
Setting this variable will add a ``<meta property="fb:admins" content="ID1,ID2,...">`` tag to every page.  
Usage: $wgFacebookAdmins= 'ID1,ID2,...';.

### $wgYandexSiteVerificationKey
Setting this variable will add a ``<meta name="yandex-verification" content="CODE">`` tag to every page.  
Usage: $wgYandexSiteVerificationKey= 'CODE';.

### $wgAlexaSiteVerificationKey
Setting this variable will add a ``<meta name="alexaVerifyID" content="CODE">`` tag to every page.  
Usage: $wgAlexaSiteVerificationKey= 'CODE';.

### $wgPinterestSiteVerificationKey
Setting this variable will add a ``<meta name="p:domain_verify" content="CODE">`` tag to every page.  
Usage: $wgPinterestSiteVerificationKey= 'CODE';.

### $wgNortonSiteVerificationKey
Setting this variable will add a ``<meta name="norton-safeweb-site-verification" content="CODE">`` tag to every page.  
Usage: $wgNortonSiteVerificationKey= 'CODE';.

### $wgTwitterSiteHandle
*Only used when Twitter generator is loaded.*  
Setting this variable will add a ``<meta property="twitter:site" content="@SITE_HANDLE">`` tag to every page.  
Usage: $wgTwitterSiteHandle = '@SITE_HANDLE';.

### $wgMetadataGenerators
Array containing the metadata generator names to load.  
Default: ["OpenGraph", "Twitter", "SchemaOrg"].  
If you only want to change the page title and add 'description', 'keywords', 'robots' tags set $wgMetadataGenerators = [];

### $wgWikiSeoDefaultImage
Default image. Local image, if not set $wgLogo will be used.

### $wgWikiSeoDisableLogoFallbackImage
Disables setting `$wgLogo` as the fallback image if no image was set.

### $wgTwitterCardType
Defaults to `summary_large_image` for the twitter card type.  
Usage: $wgTwitterCardType = 'summary';

### $wgWikiSeoDefaultLanguage
A default language code with area to generate a `<link rel="alternate" href="current Url" hreflang="xx-xx">` for.  
Usage: $wgWikiSeoDefaultLanguage = 'de-de';  


## Usage
The extension can be used via the ``{{#seo}}`` parser function. It accepts the following named parameters in any order.

* title
  * The title you want to appear in the html title tag
* title_mode
  * Set to append, prepend, or replace (default) to define how the title will be amended.
* title_separator
  * The separator in case titlemode was set to append or prepend; " - " (default)
* keywords
  * A comma separated list of keywords for the meta keywords tag
* description
  * A text description for the meta description tag
* robots
  * Controls the behavior of search engine crawling and indexing
* googlebot
  * Controls the behavior of the google crawler
* hreflang_xx-xx[]
  * Adds `<link rel="alternate" href="url" hreflang="xx-xx">` elements 

**Tags related to the Open Graph protocol**  
* type
  * The type of your object, e.g., "video.movie". Depending on the type you specify, other properties may also be required.
* image
  * An image URL which should represent your object within the graph. The extension will automatically add the right image url, width and height if an image name is set as the parameter. Example ``image = Local_file_to_use.png``. Alternatively a full url to an image can be used, image_width and image_height will then have to be set manually. If no parameter is set, the extension will use ``$wgLogo`` as a fallback or the local file set through ``$wgWikiSeoDefaultImage``.
* image_width
  * The image width in px. (Automatically set if an image name is set in image)
* image_height
  * The image height in px. (Automatically set if an image name is set in image)
* image_alt
  * Alternative description for the image.
* locale
  * The locale these tags are marked up in. Of the format language_TERRITORY.
* site_name
  * If your object is part of a larger web site, the name which should be displayed for the overall site. e.g., "IMDb".

**Tags related to Open Graph type "article"**
* author
  * Writers of the article.
* keywords
  * Translates into article:tag
* section
  * A high-level section name. E.g. Technology
* published_time
  * When the article was first published. ISO 8601 Format.

**tags related to Twitter Cards (see OpenGraph Tags)**
* twitter_site
  * If you did not set a global site name through $wgTwitterSiteHandle, you can set a site handle per page. If a global site handle is set this key will be ignored.

### Examples
#### Adding static values
```
{{#seo:
 |title=Your page title
 |titlemode=append
 |keywords=these,are,your,keywords
 |description=Your meta description
 |image=Uploaded_file.png
 |image_alt=Wiki Logo
}}
```

#### Adding dynamic values
If you need to include variables or templates you should use the parser function to ensure they are properly parsed. This allows you to use Cargo or Semantic MediaWiki, with Page Forms, for data entry, or for programmatic creation of a page title from existing variables or content...
```
{{#seo:
 |title={{#if: {{{page_title|}}} | {{{page_title}}} | Welcome to WikiSEO}}
 |titlemode={{{title_mode|}}}
 |keywords={{{keywords|}}}
 |description={{{description|}}}
 |published_time={{REVISIONYEAR}}-{{REVISIONMONTH}}-{{REVISIONDAY2}}
}}
```

#### Hreflang Attributes
```
{{#seo:
 |hreflang_de-de=https://example.de/page
 |hreflang_nl-nl=https://example.nl/page-nl
 |hreflang_en-us=https://website.com/
}}
```
Will generate the following `<link>` elements:
```html
<link rel="alternate" href="https://example.de/page" hreflang="de-de">
<link rel="alternate" href="https://example.nl/page-nl" hreflang="nl-nl">
<link rel="alternate" href="https://website.com/" hreflang="en-us">
```


## Migrating to v2
### Removed tags
* DC.date.created
* DC.date.issued
* google
* name
* og:title (automatically set)
* og:url (automatically set)
* twitter:card (automatically set)
* twitter:creator
* twitter:domain
* article:modified_time / og:updated_time (automatically set)

### Removed configuration settings
* $wgFacebookAdminIds (use $wgFacebookAppId instead)

### Removed aliases
* metakeywords / metak
  * use keywords instead
* metadescription / metad
  * use description instead
* titlemode / title mode
  * use title_mode instead

### Changed tags
* article:author -> author
* article:section -> section
* article:tag -> keywords
* article:published_time -> published_time
* og:image / twitter:image:src -> image
* og:image:width -> image_width
* og:image:height -> image_height
* og:locale -> locale
* og:site_name -> site_name
* og:title -> title
* og:type -> type
* twitter:description -> description

## Known Issues
[Extension:PageImages](https://www.mediawiki.org/wiki/Extension:PageImages) will add an og:image tag if an image is found on the page. This overwrites any og:image tag set using this extension.  
There is currently no way to disable PageImages setting the meta tag.

## Notes
If you only want to override the display title on pages (not append words to it), you might also look at the DISPLAYTITLE tag in combination with the [Manual:$wgAllowDisplayTitle](https://www.mediawiki.org/wiki/Manual:$wgAllowDisplayTitle) and [Manual:$wgRestrictDisplayTitle](https://www.mediawiki.org/wiki/Manual:$wgRestrictDisplayTitle) settings.

### schema.org
The ``SchemaOrg`` generator will set a SearchAction property based on Special:Search.  
The properties publisher and author will be set to Organization with the name set to the content of ``$wgSitename``.  
``dateModified`` will be automatically set by fetching the latest revision timestamp. If no published_time is set, datePublished will be set to the latest revision timestamp.

### OpenGraph
``article:modified_time`` will be automatically set by fetching the latest revision timestamp. If no ``published_time`` is set, ``article:published_time`` will be set to the latest revision timestamp.

## Extending this extension
Metadata generators live in the ``includes/Generator/Plugins`` directory.  
A generator has to implement the ``GeneratorInterface``.  
To load the generator simply add its name to ``$wgMetadataGenerators``.
