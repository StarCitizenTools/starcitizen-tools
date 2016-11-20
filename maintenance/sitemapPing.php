<?php
/*
* Sitemap Submitter
* Use this script to submit your site maps automatically to Google, Bing.MSN and Ask
* Trigger this script on a schedule of your choosing or after your site map gets updated.
*/

//Set this to be your site map URL
$sitemapUrl = "https://starcitizen.tools/sitemap/sitemap-index-starcitizen_wiki-wiki.xml";

// cUrl handler to ping the Sitemap submission URLs for Search Engines…
function myCurl($url){
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return $httpCode;
}

//Google
$url = "http://www.google.com/webmasters/sitemaps/ping?sitemap=".$sitemapUrl;
$returnCode = myCurl($url);
echo "<p>Google Sitemaps has been pinged (return code: $returnCode).</p>";

//Bing / MSN
$url = "http://www.bing.com/webmaster/ping.aspx?siteMap=".$sitemapUrl;
$returnCode = myCurl($url);
echo "<p>Bing / MSN Sitemaps has been pinged (return code: $returnCode).</p>";

//ASK
$url = "http://submissions.ask.com/ping?sitemap=".$sitemapUrl;
$returnCode = myCurl($url);
echo "<p>ASK.com Sitemaps has been pinged (return code: $returnCode).</p>";