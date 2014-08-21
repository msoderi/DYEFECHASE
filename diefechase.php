<?php

// Config

$dbconn_hostname = "write down database hostname here";
$dbconn_username = "write down username for database connection here";
$dbconn_password = "write down password for database connection here";
$dbconn_dbname = "write down database name here";

$dbtable_channels = "admin___channels";
$dbtable_statistics = "admin___channelsd";
$dbtable_enw = "engine___new_websites";
$dbtable_ep = "engine___pages";
$dbtable_eil = "engine___interesting_links";
$dbtable_epl = "engine___previous_level";

$file_wsl = "swebsites";
$file_input = "cwebsites";
$file_stopcmd = "stop.cmd";
$file_tmp = "diefechase.tmp";
$file_orig = "diefechase.orig";
$folder_log = "log";

// Setup

$conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);

$query__admin_channels = <<<ADMINCHANNELSQUERY
CREATE TABLE IF NOT EXISTS `admin__channels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `channel` varchar(255) NOT NULL,
  `referer` varchar(255) NOT NULL,
  `LastModified` varchar(255) NOT NULL,
  `ContentLength` varchar(255) NOT NULL,
  `skip` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `channel` (`channel`)
) ENGINE=InnoDB AUTO_INCREMENT=335448 DEFAULT CHARSET=utf8;
ADMINCHANNELSQUERY;

mysqli_query($conn, $query__admin_channels);

$query__admin_channelsd = <<<ADMINCHANNELSDQUERY
CREATE TABLE IF NOT EXISTS `admin__channelsd` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `website` varchar(255) NOT NULL,
  `downloads` int(10) unsigned NOT NULL,
  `skipped` int(10) unsigned NOT NULL,
  `channels` int(10) unsigned NOT NULL,
  `exetime` int(10) unsigned NOT NULL,
  `datetime` datetime NOT NULL,
  `v` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13219 DEFAULT CHARSET=utf8;
ADMINCHANNELSDQUERY;

mysqli_query($conn, $query__admin_channelsd);

$query__engine_interesting_links = <<<ENGINEINTERESTINGLINKSQUERY
CREATE TABLE IF NOT EXISTS `engine__interesting_links` (
  `item` varchar(255) NOT NULL,
  PRIMARY KEY (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ENGINEINTERESTINGLINKSQUERY;

mysqli_query($conn, $query__engine_interesting_links);

$query__engine_new_websites = <<<ENGINENEWWEBSITESQUERY
CREATE TABLE IF NOT EXISTS `engine__new_websites` (
  `item` varchar(255) NOT NULL,
  PRIMARY KEY (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ENGINENEWWEBSITESQUERY;

mysqli_query($conn, $query__engine_new_websites);

$query__engine_pages = <<<ENGINEPAGESQUERY
CREATE TABLE IF NOT EXISTS `engine__pages` (
  `item` varchar(255) NOT NULL,
  PRIMARY KEY (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ENGINEPAGESQUERY;

mysqli_query($conn, $query__engine_pages);

$query__previous_level = <<<PREVIOUSLEVELQUERY
CREATE TABLE IF NOT EXISTS`engine__previous_level` (
  `item` varchar(255) NOT NULL,
  PRIMARY KEY (`item`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
PREVIOUSLEVELQUERY;

mysqli_query($conn, $query__previous_level);

mysqli_close($conn);

// Mandatory timezone

date_default_timezone_set("Europe/Rome");

// If requested, execution terminates.
// To request termination, create an empty file at the relative position $file_stopcmd.
// Note: this way, not only PHP script, but even shell script execution terminates.

if(file_exists("$file_stopcmd")) 
{
	mylog("generic", date("d/m/Y H:i:s")." INFO Stop command sent. Daemon killed.\n");
	unlink("$file_stopcmd");
	exit(0);
}

// Get URL at the first line of input text file named $file_input located where the PHP script also is.
// This script will search for RSS, ATOM and RDF channels starting from that URL.
// If the input text file is not available, execution terminates.

$websitesfile = fopen("$file_input","r");
$website = trim(fgets($websitesfile));
if(!$website) 
{
	mylog("generic", date("d/m/Y H:i:s")." INFO Daemon killed cause no more websites to be crawled.\n");
	dbInitNewWebsites();
	fclose($websitesfile);
	exit(1);
}
fclose($websitesfile);

$canonicWebsiteAddress = $website;

exec("sed -i 1d $file_input");

// Some initializations

$seedsCount = 0;
$monitorDownloads = 0;
$monitorSkipped = 0;
$monitorChannels = 0;
$monitorExetime = 0;

$start = time();
mylog($website, date("d/m/Y H:i:s")." [INFO] Start searching channels.\n");

$channels = array();

mylog($website, date("d/m/Y H:i:s")." [INFO] Initializing previous level.\n");
dbInitPreviousLevel();

mylog($website, date("d/m/Y H:i:s")." [INFO] Initializing pages.\n");
dbInitPages();

mylog($website, date("d/m/Y H:i:s")." [INFO] Initializing interesting links.\n");
dbInitInterestingLinks();

// Searching at depth 1...

mylog($website, date("d/m/Y H:i:s")." [INFO] Starting level 1.\n");

if(isset($level1)) unset($level1); $level1 = array();

mylog($website, date("d/m/Y H:i:s")." INFO Downloading from $website\n");
$actualWebsite = download($website,"$file_tmp"); $website = $actualWebsite;

if(!$website) 
{
	mylog($website, date("d/m/Y H:i:s")." [ERR] Unable to download.\n");	
	exit(1); 
}
else 
{
	mylog($website, date("d/m/Y H:i:s")." [INFO] Download complete.\n");
	$monitorDownloads++;
}

mylog($website, date("d/m/Y H:i:s")." [INFO] Computing path.\n");

$pWebsite = $website;
if(false !== strpos($pWebsite, "?")) $pWebsite = substr($pWebsite, 0, strpos($pWebsite, "?"));
if(false !== strpos($pWebsite, "#")) $pWebsite = substr($pWebsite, 0, strpos($pWebsite, "#"));
$path = substr($pWebsite,0,strrpos($pWebsite,"/"))."/";

if($path == "http://" || $path == "https://")
{
	$pWebsite.="/";
        $path = substr($pWebsite,0,strrpos($pWebsite,"/"))."/";
}

if(filesize("$file_tmp") > floor(0.8*33554432)-memory_get_usage(true)) 
{
	mylog($website, date("d/m/Y H:i:s")." [ERR] Memory problem.\n");
	exit(1);
} 

mylog($website, date("d/m/Y H:i:s")." [INFO] Putting downloaded page to string.\n");

$pagestr = file_get_contents("$file_tmp");

mylog($website, date("d/m/Y H:i:s")." [INFO] Computing basehref.\n");

$basehref = declaredBaseHref($pagestr);
if(false !== $basehref) $path = $basehref;

mylog($website, date("d/m/Y H:i:s")." [INFO] Composing links array.\n");

$level1 = array_merge
                        (
                                count(array_slice(explode("href",$pagestr),1)) < 10000 ? array_slice(explode("href",$pagestr),1) : array(),
                                count(array_slice(explode("HREF",$pagestr),1)) < 10000 ? array_slice(explode("HREF",$pagestr),1) : array(),
                                count(array_slice(explode("Href",$pagestr),1)) < 10000 ? array_slice(explode("Href",$pagestr),1) : array(),
                                count(array_slice(explode("src",$pagestr),1)) < 10000 ? array_slice(explode("src",$pagestr),1) : array(),
                                count(array_slice(explode("SRC",$pagestr),1)) < 10000 ? array_slice(explode("SRC",$pagestr),1) : array(),
                                count(array_slice(explode("Src",$pagestr),1)) < 10000 ? array_slice(explode("Src",$pagestr),1) : array(),
                                count(array_slice(explode("document.location.href",$pagestr),1)) < 10000 ? array_slice(explode("document.location.href",$pagestr),1) : array(),
                                count(array_slice(explode("window.location",$pagestr),1)) < 10000 ? array_slice(explode("window.location",$pagestr),1) : array()
                        );

mylog($website, date("d/m/Y H:i:s")." [INFO] Cleaning links array.\n");

if(isset($pagestr)) unset($pagestr);
$theyWas = count($level1);
clean($level1, $website, $path);
$theyAre = count($level1);
$monitorSkipped += $theyWas - $theyAre;

mylog($website, date("d/m/Y H:i:s")." [INFO] Stripping query strings.\n");

if(0 < count($level1)) array_walk($level1, "stripQs");

mylog($website, date("d/m/Y H:i:s")." [INFO] Inspecting links for new websites.\n");

foreach($level1 as $link)
{

	mylog($website, date("d/m/Y H:i:s")." [INFO] Checking current link.\n");

       if(false === stripos($link, $website) && !dbIsInterestingLink($link))
       {
                
		mylog($website, date("d/m/Y H:i:s")." [INFO] Adding link to new websites.\n");

                dbAddToNewWebsites(substr($link,0,strpos($link,"/",8))."/");

		mylog($website, date("d/m/Y H:i:s")." [INFO] Added link to new websites.\n");


       }

	mylog($website, date("d/m/Y H:i:s")." [INFO] Link check completed.\n");

}

mylog($website, date("d/m/Y H:i:s")." [INFO] Inspection completed.\n");

mylog($website, date("d/m/Y H:i:s")." [INFO] Adding level1 to previous level.\n");

dbAddToPreviousLevel($level1);

// Searching at depth 2...

if(isset($level2)) unset($level2); $level2 = array();

mylog($website, date("d/m/Y H:i:s")." [INFO] Start level 2.\n");

foreach($level1 as $link1)
{
	if(trim($link1) == "") continue;
mylog($website, date("d/m/Y H:i:s")." INFO Downloading from $link1\n");
	
	$actualLink1 = download($link1, "$file_tmp"); $link1 = $actualLink1;

mylog($website, date("d/m/Y H:i:s")." [INFO] Download completed from $link1.\n");

	if($link1)
	{

mylog($website, date("d/m/Y H:i:s")." [INFO] Download successfull from $link1.\n");

		$monitorDownloads++;	        
		
		if
		(
		        false !== stripos($link1, "rss") ||
	        	false !== stripos($link1, "rdf") ||
	        	false !== stripos($link1, "atom") ||
		        false !== stripos($link1, "feed") ||
		        ( false !== stripos($link1, "xml") && false === stripos($link1, "xmlrpc") ) ||
			false !== stripos($link1, "backend") ||
		        false !== stripos($link1, "channel") ||
		        false !== stripos($link1, "subscribe")
		)
		{

			mylog($website, date("d/m/Y H:i:s")." [INFO] $link1 could be interesting.\n");

		        $file = fopen("$file_tmp","r");
		        $fileFragment = fread($file, 1024);
	                
			mylog($website, date("d/m/Y H:i:s")." [INFO] Fetched first bytes.\n");

			if(false !== stripos($fileFragment, "<rss") || false !== stripos($fileFragment, "<rdf") || false !== stripos($fileFragment, "<feed"))
			{
		                
				mylog($website, date("d/m/Y H:i:s")." [INFO] $link1 is a channel looking at how it starts.\n");

				if(!in_array($link1, $channels)) 
				{

					mylog($website, date("d/m/Y H:i:s")." [INFO] $link1 has to be added to channels list.\n");

					$channels[] = $link1;
					$conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
					mysqli_query($conn, "INSERT INTO $dbtable_channels(channel, referer) VALUES ( '$link1', '$website')");
					mysqli_close($conn);

					mylog($website, date("d/m/Y H:i:s")." [INFO] $link1 was added to channels list.\n");

				}

                		continue;
        		}
		        else
        		{

				mylog($website, date("d/m/Y H:i:s")." [INFO] $link1 is not a channel looking at how it starts.\n");

		                $ss = false; if(substr($link1, strlen($link1)-1,1) == "/") { $link1 = substr($link1, 0, strlen($link1)-1); $ss = true; }
				$headers = @get_headers($link1,true);
                                if($headers) 
				{
				
					mylog($website, date("d/m/Y H:i:s")." [INFO] $link1 http response headers got.\n");

					$contentType = isset($headers["Content-Type"])?print_r($headers["Content-Type"], true):print_r($headers,true);
					if
        	        		(
			                        false !== stripos($contentType, "rss") ||
                			        false !== stripos($contentType, "rdf") ||
		                	        false !== stripos($contentType, "atom") ||
                		        	false !== stripos($contentType, "feed") 
			                )
        	        		{
						mylog($website, date("d/m/Y H:i:s")." [INFO] $link1 is a channel looking at its content type.\n");

			                        if(!in_array($link1, $channels)) 
						{
							mylog($website, date("d/m/Y H:i:s")." [INFO] $link1 has to be added to channels list.\n");

							$channels[] = $link1;
	                                	        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        	                                	mysqli_query($conn, "INSERT INTO $dbtable_channels(channel, referer) VALUES ( '$link1', '$website')");
	                	                        mysqli_close($conn);

							mylog($website, date("d/m/Y H:i:s")." [INFO] $link1 was added to channels list.\n");

						}
                			        continue; 
		        	        }
				}
				if($ss) $link1 = "$link1/";

		        }
		        fclose($file);
		}
	
		mylog($website, date("d/m/Y H:i:s")." [WARN] Memory risk.\n");

		if(filesize("$file_tmp") > floor(0.8*33554432)-memory_get_usage(true)) continue;
 
		mylog($website, date("d/m/Y H:i:s")." [INFO] Computing path.\n");

		$pLink = $link1;
		if(false !== strpos($pLink, "?")) $pLink = substr($pLink, 0, strpos($pLink, "?"));
		if(false !== strpos($pLink, "#")) $pLink = substr($pLink, 0, strpos($pLink, "#"));
		$path = substr($pLink,0,strrpos($pLink,"/"))."/";

                if($path == "http://" || $path == "https://")
                {
                        $pLink.="/";
                        $path = substr($pLink,0,strrpos($pLink,"/"))."/";
                }

		mylog($website, date("d/m/Y H:i:s")." [INFO] Downloaded page to string.\n");

		$pagestr = file_get_contents("$file_tmp");

		mylog($website, date("d/m/Y H:i:s")." [INFO] Computing basehref.\n");

		$basehref = declaredBaseHref($pagestr);
		if(false !== $basehref) $path = $basehref;

		mylog($website, date("d/m/Y H:i:s")." [INFO] Composing level2link1 links list.\n");

		$level2link1 = array_merge
                        (
                                count(array_slice(explode("href",$pagestr),1)) < 10000 ? array_slice(explode("href",$pagestr),1) : array(),
                                count(array_slice(explode("HREF",$pagestr),1)) < 10000 ? array_slice(explode("HREF",$pagestr),1) : array(),
                                count(array_slice(explode("Href",$pagestr),1)) < 10000 ? array_slice(explode("Href",$pagestr),1) : array(),
                                count(array_slice(explode("src",$pagestr),1)) < 10000 ? array_slice(explode("src",$pagestr),1) : array(),
                                count(array_slice(explode("SRC",$pagestr),1)) < 10000 ? array_slice(explode("SRC",$pagestr),1) : array(),
                                count(array_slice(explode("Src",$pagestr),1)) < 10000 ? array_slice(explode("Src",$pagestr),1) : array(),
                                count(array_slice(explode("document.location.href",$pagestr),1)) < 10000 ? array_slice(explode("document.location.href",$pagestr),1) : array(),
                                count(array_slice(explode("window.location",$pagestr),1)) < 10000 ? array_slice(explode("window.location",$pagestr),1) : array()
                        );


		if(isset($pagestr)) unset($pagestr);
	
		mylog($website, date("d/m/Y H:i:s")." [INFO] Cleaning level2link1 links list.\n");

		$theyWas = count($level2link1);
		clean($level2link1, $website, $path);

		$theyAre = count($level2link1);
		$monitorSkipped += $theyWas - $theyAre;

		$level2[$link1] = $level2link1;
		
		mylog($website, date("d/m/Y H:i:s")." [INFO] Stripping query string from level2link1.\n");

		if(0 < count($level2link1)) array_walk($level2link1, "stripQs");	

		mylog($website, date("d/m/Y H:i:s")." [INFO] Inspecting level2link1 links for new websites.\n");

		foreach($level2link1 as $link)
		{

			mylog($website, date("d/m/Y H:i:s")." [INFO] Start current link inspection.\n");

		        if(false === stripos($link, $website) && !dbIsInterestingLink($link)) 
			{
				
				mylog($website, date("d/m/Y H:i:s")." [INFO] It has to be added to new websites.\n");

		                dbAddToNewWebsites(substr($link,0,strpos($link,"/",8))."/");

				mylog($website, date("d/m/Y H:i:s")." [INFO] It was added to new websites.\n");

			}

			mylog($website, date("d/m/Y H:i:s")." [INFO] End of current link inspection.\n");

		}
		
		mylog($website, date("d/m/Y H:i:s")." [INFO] Inspection completed.\n");

		// Questo con l'uso del database cambia
		// if(floor(0.8*33554432) < memory_get_usage(true)) $previousLevel = array_splice($previousLevel, $seedsCount, count($level2link1));
		// $previousLevel = array_merge($previousLevel, $level2link1);
		dbAddToPreviousLevel($level2link1);

		mylog($website, date("d/m/Y H:i:s")." [INFO] Level2link1 successfully added to previous level.\n");


	}

}

unset($level1);

// Searching at depth 3...

mylog($website, date("d/m/Y H:i:s")." [INFO] Start level 3.\n");

if(isset($level3)) unset($level3); $level3 = array();

foreach($level2 as $key => $item)
{
	
	if(count($level2[$key]) > 100) continue;

	foreach($level2[$key] as $link2)
	{
		if(trim($link2) == "") continue;
	       mylog($website, date("d/m/Y H:i:s")." INFO Downloading from $link2\n");
 
		$actualLink2 = download($link2, "$file_tmp"); $link2 = $actualLink2;

		mylog($website, date("d/m/Y H:i:s")." [INFO] Download completed from $link2.\n");

		if($link2)
		{

			mylog($website, date("d/m/Y H:i:s")." [INFO] Download successfull from $link2.\n");

			$monitorDownloads++;
	                if
        	        (
                	        false !== stripos($link2, "rss") ||
                        	false !== stripos($link2, "rdf") ||
	                        false !== stripos($link2, "atom") ||
        	                false !== stripos($link2, "feed") ||
                        	( false !== stripos($link2, "xml") && false === stripos($link2, "xmlrpc") ) ||
				false !== stripos($link2, "backend") ||
	                        false !== stripos($link2, "channel") ||
        	                false !== stripos($link2, "subscribe")
                	)
	                {

				mylog($website, date("d/m/Y H:i:s")." [INFO] $link2 could be interesting.\n");

				$file = fopen("$file_tmp","r");
                	        $fileFragment = fread($file, 1024);

				mylog($website, date("d/m/Y H:i:s")." [INFO] Checking how it starts.\n");

	                        if(false !== stripos($fileFragment, "<rss") || false !== stripos($fileFragment, "<rdf") || false !== stripos($fileFragment, "<feed"))
				{

					mylog($website, date("d/m/Y H:i:s")." [INFO] it starts well.\n");

					if(!in_array($link2, $channels)) 
					{

						mylog($website, date("d/m/Y H:i:s")." [INFO] It has to be added.\n");

						$channels[] = $link2;
	                                        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        	                                mysqli_query($conn, "INSERT INTO $dbtable_channels(channel, referer) VALUES ( '$link2', '$key')");
                	                        mysqli_close($conn);

						mylog($website, date("d/m/Y H:i:s")." [INFO] It was added.\n");

					}
                	                continue;
                        	}
	                        else
        	                {
					mylog($website, date("d/m/Y H:i:s")." [INFO] It starts bad.\n");

					$ss = false; if(substr($link2, strlen($link2)-1,1) == "/") { $link2 = substr($link2, 0, strlen($link2)-1); $ss = true; }
                	                $headers = @get_headers($link2,true);

					mylog($website, date("d/m/Y H:i:s")." [INFO] Let's check to see its headers.\n");

					if($headers)
					{
						mylog($website, date("d/m/Y H:i:s")." [INFO] Got them properly.\n");

	                                	$contentType = isset($headers["Content-Type"])?print_r($headers["Content-Type"], true):print_r($headers, true);
						if
		                                (
        		                                false !== stripos($contentType, "rss") ||
                		                        false !== stripos($contentType, "rdf") ||
                        		                false !== stripos($contentType, "atom") ||
                                		        false !== stripos($contentType, "feed") 
	        	                        )
        	        	                {
							mylog($website, date("d/m/Y H:i:s")." [INFO] Good content type.\n");

                	        	                if(!in_array($link2, $channels)) 
							{
								mylog($website, date("d/m/Y H:i:s")." [INFO] It has to be added.\n");

								$channels[] = $link2;
	                        		                $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        	        	                	        mysqli_query($conn, "INSERT INTO $dbtable_channels(channel, referer) VALUES ( '$link2', '$website')");
                	                	        	mysqli_close($conn);

								mylog($website, date("d/m/Y H:i:s")." [INFO] It was added.\n");

							}
        	                        	        continue;
	        	                        }
					}		
					if($ss) $link2 = "$link2/";

                	        }
        	                fclose($file);
                	}

			mylog($website, date("d/m/Y H:i:s")." [INFO] Risk due to memory?\n");

	                if(filesize("$file_tmp") > floor(0.8*33554432)-memory_get_usage(true)) continue;

			mylog($website, date("d/m/Y H:i:s")." [INFO] No, let's go on and compue path.\n");

	                $pLink = $link2;
        	        if(false !== strpos($pLink, "?")) $pLink = substr($pLink, 0, strpos($pLink, "?"));
                	if(false !== strpos($pLink, "#")) $pLink = substr($pLink, 0, strpos($pLink, "#"));
	                $path = substr($pLink,0,strrpos($pLink,"/"))."/";

			if($path == "http://" || $path == "https://")
	                {
        	                $pLink.="/";
                	        $path = substr($pLink,0,strrpos($pLink,"/"))."/";
	                }
 
			mylog($website, date("d/m/Y H:i:s")." [INFO] Downloaded page to string.\n");

        		$pagestr = file_get_contents("$file_tmp");

			mylog($website, date("d/m/Y H:i:s")." [INFO] Computing basehref.\n");

	                $basehref = declaredBaseHref($pagestr);
        	        if(false !== $basehref) $path = $basehref;

			mylog($website, date("d/m/Y H:i:s")." [INFO] Composing level3link2 links list.\n");

			$level3link2 = array_merge
                        (
                                count(array_slice(explode("href",$pagestr),1)) < 10000 ? array_slice(explode("href",$pagestr),1) : array(),
                                count(array_slice(explode("HREF",$pagestr),1)) < 10000 ? array_slice(explode("HREF",$pagestr),1) : array(),
                                count(array_slice(explode("Href",$pagestr),1)) < 10000 ? array_slice(explode("Href",$pagestr),1) : array(),
                                count(array_slice(explode("src",$pagestr),1)) < 10000 ? array_slice(explode("src",$pagestr),1) : array(),
                                count(array_slice(explode("SRC",$pagestr),1)) < 10000 ? array_slice(explode("SRC",$pagestr),1) : array(),
                                count(array_slice(explode("Src",$pagestr),1)) < 10000 ? array_slice(explode("Src",$pagestr),1) : array(),
                                count(array_slice(explode("document.location.href",$pagestr),1)) < 10000 ? array_slice(explode("document.location.href",$pagestr),1) : array(),
                                count(array_slice(explode("window.location",$pagestr),1)) < 10000 ? array_slice(explode("window.location",$pagestr),1) : array()
                        );


			if(isset($pagestr)) unset($pagestr);
	
			mylog($website, date("d/m/Y H:i:s")." [INFO] Cleaning level3link2 links list.\n");

			$theyWas = count($level3link2);
			clean($level3link2, $website, $path);
			$theyAre = count($level3link2);
			$monitorSkipped += $theyWas - $theyAre;

	        	$level3[$link2] = $level3link2;

			mylog($website, date("d/m/Y H:i:s")." [INFO] Stripping query string.\n");

			if(0 < count($level3link2)) array_walk($level3link2, "stripQs");

			mylog($website, date("d/m/Y H:i:s")." [INFO] Start inspection for new websites.\n");

	                foreach($level3link2 as $link)
        	        {
				
				mylog($website, date("d/m/Y H:i:s")." [INFO] Inspecting current link.\n");

                	        if(false === stripos($link, $website) && !dbIsInterestingLink($link))
                        	{
	                                mylog($website, date("d/m/Y H:i:s")." [INFO] Has to be added.\n");

					
	                                dbAddToNewWebsites(substr($link,0,strpos($link,"/",8))."/");
					mylog($website, date("d/m/Y H:i:s")." [INFO] Was added.\n");

                        	}
				
	               	}

			mylog($website, date("d/m/Y H:i:s")." [INFO] adding level3link2 to previous level.\n");

			dbAddToPreviousLevel($level3link2);

			mylog($website, date("d/m/Y H:i:s")." [INFO] level3link2 added to previous level.\n");

		}
	}
}

unset($level2);

// Searching at depth 4...

mylog($website, date("d/m/Y H:i:s")." [INFO] Start level 4.\n");

if(isset($level4)) unset($level4); $level4 = array();

foreach($level3 as $key => $item)
{

	if(count($level3[$key]) > 100) continue;

	foreach($level3[$key] as $link3)
	{
	      
		if(trim($link3) == "") continue;
mylog($website, date("d/m/Y H:i:s")." INFO Downloading from $link3\n");
	
        	$actualLink3 = download($link3, "$file_tmp"); $link3 = $actualLink3;
		
mylog($website, date("d/m/Y H:i:s")." [INFO] Download completed from $link3.\n");

		if($link3)
		{

			mylog($website, date("d/m/Y H:i:s")." [INFO] Download successfull.\n");

			$monitorDownloads++;	
			       
                        if
                        (
                                false !== stripos($link3, "rss") ||
                                false !== stripos($link3, "rdf") ||
                                false !== stripos($link3, "atom") ||
                                false !== stripos($link3, "feed") ||
                                ( false !== stripos($link3, "xml") && false === stripos($link3, "xmlrpc") ) ||
				false !== stripos($link3, "backend") ||
                                false !== stripos($link3, "channel") ||
                                false !== stripos($link3, "subscribe")
                        )
                        {
				mylog($website, date("d/m/Y H:i:s")." [INFO] Seems interesting.\n");

                                $file = fopen("$file_tmp","r");
                                $fileFragment = fread($file, 1024);
				mylog($website, date("d/m/Y H:i:s")." [INFO] Looking at how it starts.\n");

	                        if(false !== stripos($fileFragment, "<rss") || false !== stripos($fileFragment, "<rdf") || false !== stripos($fileFragment, "<feed"))
				{
					mylog($website, date("d/m/Y H:i:s")." [INFO] It starts well.\n");

                                        if(!in_array($link3, $channels)) 
					{
						mylog($website, date("d/m/Y H:i:s")." [INFO] It has to be added.\n");

						$channels[] = $link3;
	                                        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        	                                mysqli_query($conn, "INSERT INTO $dbtable_channels(channel, referer) VALUES ( '$link3', '$key')");
                	                        mysqli_close($conn);

						mylog($website, date("d/m/Y H:i:s")." [INFO] It was added.\n");

					}
                                        continue;
                                }
                                else
                                {
	                                mylog($website, date("d/m/Y H:i:s")." [INFO] It starts bad let's check headers.\n");

					$ss = false; if(substr($link3, strlen($link3)-1,1) == "/") { $link3 = substr($link3, 0, strlen($link3)-1); $ss = true; }
                                        $headers = @get_headers($link3,true);
					if($headers){
					mylog($website, date("d/m/Y H:i:s")." [INFO] Headers got.\n");

                                        $contentType = isset($headers["Content-Type"])?print_r($headers["Content-Type"], true):print_r($headers,true);
					if
                                        (
                                                false !== stripos($contentType, "rss") ||
                                                false !== stripos($contentType, "rdf") ||
                                                false !== stripos($contentType, "atom") ||
                                                false !== stripos($contentType, "feed") 
                                        )
                                        {
						mylog($website, date("d/m/Y H:i:s")." [INFO] Content type is good.\n");

                                                if(!in_array($link3, $channels)) 
						{
							mylog($website, date("d/m/Y H:i:s")." [INFO] Has to be added.\n");

							$channels[] = $link3;
		                                        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
                		                        mysqli_query($conn, "INSERT INTO $dbtable_channels(channel, referer) VALUES ( '$link3', '$key')");
                                		        mysqli_close($conn);
							mylog($website, date("d/m/Y H:i:s")." [INFO] Was added..\n");

						}
                                                continue;
                                        }
					}
					if($ss) $link3 = "$link3/";

                                }
                                fclose($file);
                        }

			mylog($website, date("d/m/Y H:i:s")." [INFO] Memory problems?\n");


                        if(filesize("$file_tmp") > floor(0.8*33554432)-memory_get_usage(true)) continue;

			mylog($website, date("d/m/Y H:i:s")." [INFO] No let's compose path.\n");

	                $pLink = $link3;
        	        if(false !== strpos($pLink, "?")) $pLink = substr($pLink, 0, strpos($pLink, "?"));
                	if(false !== strpos($pLink, "#")) $pLink = substr($pLink, 0, strpos($pLink, "#"));
	                $path = substr($pLink,0,strrpos($pLink,"/"))."/";
 
	                if($path == "http://" || $path == "https://")
        	        {
                	        $pLink.="/";
                        	$path = substr($pLink,0,strrpos($pLink,"/"))."/";
	                }
	
			mylog($website, date("d/m/Y H:i:s")." [INFO] Downloaded page to string.\n");

			$pagestr = file_get_contents("$file_tmp");

			mylog($website, date("d/m/Y H:i:s")." [INFO] Computing basehref.\n");

	                $basehref = declaredBaseHref($pagestr);
        	        if(false !== $basehref) $path = $basehref;

			mylog($website, date("d/m/Y H:i:s")." [INFO] Composing level4link3 links list.\n");

//	        	$level4link3 = array_merge(array_slice(explode("href",$pagestr),1),array_slice(explode("HREF",$pagestr),1));
			$level4link3 = array_merge
                        (
                                count(array_slice(explode("href",$pagestr),1)) < 10000 ? array_slice(explode("href",$pagestr),1) : array(),
                                count(array_slice(explode("HREF",$pagestr),1)) < 10000 ? array_slice(explode("HREF",$pagestr),1) : array(),
                                count(array_slice(explode("Href",$pagestr),1)) < 10000 ? array_slice(explode("Href",$pagestr),1) : array(),
                                count(array_slice(explode("src",$pagestr),1)) < 10000 ? array_slice(explode("src",$pagestr),1) : array(),
                                count(array_slice(explode("SRC",$pagestr),1)) < 10000 ? array_slice(explode("SRC",$pagestr),1) : array(),
                                count(array_slice(explode("Src",$pagestr),1)) < 10000 ? array_slice(explode("Src",$pagestr),1) : array(),
                                count(array_slice(explode("document.location.href",$pagestr),1)) < 10000 ? array_slice(explode("document.location.href",$pagestr),1) : array(),
                                count(array_slice(explode("window.location",$pagestr),1)) < 10000 ? array_slice(explode("window.location",$pagestr),1) : array()
                        );


			if(isset($pagestr)) unset($pagestr);

			mylog($website, date("d/m/Y H:i:s")." [INFO] Cleaning level4link3 links list.\n");

			$theyWas = count($level4link3);
        		clean($level4link3, $website, $path);
			$theyAre = count($level4link3);
			$monitorSkipped += $theyWas - $theyAre;

	        	$level4[$link3] = $level4link3;
			
			mylog($website, date("d/m/Y H:i:s")." [INFO] Stripping query string.\n");

			if(0 < count($level4link3)) array_walk($level4link3, "stripQs");

			mylog($website, date("d/m/Y H:i:s")." [INFO] Inspecting for new websites.\n");

	                foreach($level4link3 as $link)
        	        {

				mylog($website, date("d/m/Y H:i:s")." [INFO] Inspecting current website.\n");

                	        if(false === stripos($link, $website) && !dbIsInterestingLink($link))
                        	{

					mylog($website, date("d/m/Y H:i:s")." [INFO] Has to be added to new websites.\n");

	                                dbAddToNewWebsites(substr($link,0,strpos($link,"/",8))."/");

					mylog($website, date("d/m/Y H:i:s")." [INFO] Was added to new websites.\n");

                        	}

				mylog($website, date("d/m/Y H:i:s")." [INFO] Current link ispection completed.\n");

                	}

			mylog($website, date("d/m/Y H:i:s")." [INFO] Inspection completed.\n");
			
			mylog($website, date("d/m/Y H:i:s")." [INFO] Adding level4link3 to previous level\n");

			dbAddToPreviousLevel($level4link3);

			mylog($website, date("d/m/Y H:i:s")." [INFO] Added level4link3 to previous level.\n");


		}
	}

}

unset($level3);

// Searching at depth 5...

mylog($website, date("d/m/Y H:i:s")." [INFO] Start level 5.\n");

if(isset($level5)) unset($level5); $level5 = array();

foreach($level4 as $key => $item)
{

	if(count($level4[$key]) > 100) continue;

        foreach($level4[$key] as $link4)
        {
	
		if(trim($link4) == "") continue;
                mylog($website, date("d/m/Y H:i:s")." INFO Downloading from $link4\n");

		$actualLink4 = download($link4, "$file_tmp"); $link4 = $actualLink4;
		
		mylog($website, date("d/m/Y H:i:s")." [INFO] Download complete from $link4.\n");

		if($link4)
                {
     
			mylog($website, date("d/m/Y H:i:s")." [INFO] Download was successfull.\n");

			$monitorDownloads++;
 
                        if
                        (
                                false !== stripos($link4, "rss") ||
                                false !== stripos($link4, "rdf") ||
                                false !== stripos($link4, "atom") ||
                                false !== stripos($link4, "feed") ||
                                ( false !== stripos($link4, "xml") && false === stripos($link4, "xmlrpc") ) ||
				false !== stripos($link4, "backend") ||
                                false !== stripos($link4, "channel") ||
                                false !== stripos($link4, "subscribe")
                        )
                        {

				mylog($website, date("d/m/Y H:i:s")." [INFO] It seems interesting.\n");

                                $file = fopen("$file_tmp","r");
                                $fileFragment = fread($file, 1024);

				mylog($website, date("d/m/Y H:i:s")." [INFO]  Let's check how it starts.\n");

	                        if(false !== stripos($fileFragment, "<rss") || false !== stripos($fileFragment, "<rdf") || false !== stripos($fileFragment, "<feed"))
				{

					mylog($website, date("d/m/Y H:i:s")." [INFO] It starts good.\n");

                                        if(!in_array($link4, $channels)) 
					{
						mylog($website, date("d/m/Y H:i:s")." [INFO] It has to be added.\n");

						$channels[] = $link4;
	                                        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        	                                mysqli_query($conn, "INSERT INTO $dbtable_channels(channel, referer) VALUES ( '$link4', '$key')");
                	                        mysqli_close($conn);

						mylog($website, date("d/m/Y H:i:s")." [INFO] It was added.\n");

					}
                                        continue;
                                }
                                else
                                {

					mylog($website, date("d/m/Y H:i:s")." [INFO] It starts bad.\n");

	                                $ss = false; if(substr($link4, strlen($link4)-1,1) == "/") { $link4 = substr($link4, 0, strlen($link4)-1); $ss = true; }
                                        $headers = @get_headers($link4,true);
					mylog($website, date("d/m/Y H:i:s")." [INFO] Let's check headers.\n");

					if($headers){
					mylog($website, date("d/m/Y H:i:s")." [INFO] Got headers.\n");

	                                $contentType = isset($headers["Content-Type"])?print_r($headers["Content-Type"], true):print_r($headers,true);
					if
                                        (
                                                false !== stripos($contentType, "rss") ||
                                                false !== stripos($contentType, "rdf") ||
                                                false !== stripos($contentType, "atom") ||
                                                false !== stripos($contentType, "feed") 
                                        )
                                        {
						mylog($website, date("d/m/Y H:i:s")." [INFO] Content type is good.\n");

                                                if(!in_array($link4, $channels)) 
						{
							mylog($website, date("d/m/Y H:i:s")." [INFO] It has to be added.\n");

							$channels[] = $link4;
		                                        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
                		                        mysqli_query($conn, "INSERT INTO $dbtable_channels(channel, referer) VALUES ( '$link4', '$key')");
                                		        mysqli_close($conn);
							mylog($website, date("d/m/Y H:i:s")." [INFO] It was added.\n");

						}
                                                continue;
                                        }
					}
					if($ss) $link4 = "$link4/";

                                }
                                fclose($file);
                        }

			mylog($website, date("d/m/Y H:i:s")." [INFO] Mwmory problems?.\n");

                        if(filesize("$file_tmp") > floor(0.8*33554432)-memory_get_usage(true)) continue;
 
			mylog($website, date("d/m/Y H:i:s")." [INFO] No lets compute path.\n");

                        $pLink = $link4;
	                if(false !== strpos($pLink, "?")) $pLink = substr($pLink, 0, strpos($pLink, "?"));
        	        if(false !== strpos($pLink, "#")) $pLink = substr($pLink, 0, strpos($pLink, "#"));
                	$path = substr($pLink,0,strrpos($pLink,"/"))."/";

	                if($path == "http://" || $path == "https://")
        	        {
                	        $pLink.="/";
                        	$path = substr($pLink,0,strrpos($pLink,"/"))."/";
	                }
 
			mylog($website, date("d/m/Y H:i:s")." [INFO] Downloaded page to string.\n");

			$pagestr = file_get_contents("$file_tmp");

			mylog($website, date("d/m/Y H:i:s")." [INFO] Computing basehref.\n");

	                $basehref = declaredBaseHref($pagestr);
        	        if(false !== $basehref) $path = $basehref;

			mylog($website, date("d/m/Y H:i:s")." [INFO] Composing level5link4 links list.\n");

			$level5link4 = array_merge
                        (
                                count(array_slice(explode("href",$pagestr),1)) < 10000 ? array_slice(explode("href",$pagestr),1) : array(),
                                count(array_slice(explode("HREF",$pagestr),1)) < 10000 ? array_slice(explode("HREF",$pagestr),1) : array(),
                                count(array_slice(explode("Href",$pagestr),1)) < 10000 ? array_slice(explode("Href",$pagestr),1) : array(),
                                count(array_slice(explode("src",$pagestr),1)) < 10000 ? array_slice(explode("src",$pagestr),1) : array(),
                                count(array_slice(explode("SRC",$pagestr),1)) < 10000 ? array_slice(explode("SRC",$pagestr),1) : array(),
                                count(array_slice(explode("Src",$pagestr),1)) < 10000 ? array_slice(explode("Src",$pagestr),1) : array(),
                                count(array_slice(explode("document.location.href",$pagestr),1)) < 10000 ? array_slice(explode("document.location.href",$pagestr),1) : array(),
                                count(array_slice(explode("window.location",$pagestr),1)) < 10000 ? array_slice(explode("window.location",$pagestr),1) : array()
                        );


			if(isset($pagestr)) unset($pagestr);

			mylog($website, date("d/m/Y H:i:s")." [INFO] Cleaning level5link4 links list.\n");

			$theyWas = count($level5link4);
			clean($level5link4, $website, $path);
			$theyAre = count($level5link4);
			$monitorSkipped += $theyWas - $theyAre;

			$level5[$link4] = $level5link4;
                        
			mylog($website, date("d/m/Y H:i:s")." [INFO] Stripping query string.\n");

			if(0 < count($level5link4)) array_walk($level5link4, "stripQs");

			mylog($website, date("d/m/Y H:i:s")." [INFO] Inspecting for add to new websites.\n");

	                foreach($level5link4 as $link)
        	        {
				mylog($website, date("d/m/Y H:i:s")." [INFO] Inspecting current link.\n");

                	        if(false === stripos($link, $website) && !dbIsInterestingLink($link))
                        	{
					mylog($website, date("d/m/Y H:i:s")." [INFO] Has to be added.\n");

	                                dbAddToNewWebsites(substr($link,0,strpos($link,"/",8))."/");

					mylog($website, date("d/m/Y H:i:s")." [INFO] Was added.\n");

                        	}

				mylog($website, date("d/m/Y H:i:s")." [INFO] Inspected current link.\n");

                	}

			mylog($website, date("d/m/Y H:i:s")." [INFO] Inspection complete.\n");

			mylog($website, date("d/m/Y H:i:s")." [INFO] Adding level5link4 to previous level.\n");

			dbAddToPreviousLevel($level5link4);

			mylog($website, date("d/m/Y H:i:s")." [INFO] Added level5link4 to previous level.\n");

                }
        }

}

// Search terminated. Outputting stats...

mylog($website, "\n\n>>> CHANNELS FOUND >>>\n\n");

mylog($website, print_r($channels,true));

$monitorChannels = count($channels);

$end = time();
$monitorExetime = $end-$start;
mylog($website, "\n\n>>> EXECUTION TIME >>>\n\n".($end-$start)." s\n\n");

$conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
//mysqli_query($conn, "DELETE FROM $dbtable_channelsd WHERE website = '$canonicWebsiteAddress'");
mysqli_query($conn, "INSERT INTO $dbtable_channelsd(website, downloads, skipped, channels, exetime, datetime) VALUES ( '$canonicWebsiteAddress', $monitorDownloads, $monitorSkipped, $monitorChannels, $monitorExetime, NOW() )");
mysqli_close($conn);

mylog($website, date("d/m/Y H:i:s")." [INFO] Exiting\n");

cleanLog();

exit(1);

// Function definitions 

function cleanLog()
{
	exec("rm -f $folder_log/*");
}

function clean(&$array, $website, $path)
{
	$interestingLinkFound = false;
	$interestingForItsTitle = array();
	$isFrameURL = array();
	$deleted = 0;
	for($i = 0; $i < count($array); $i++) $array[$i] = substr($array[$i],0,255);
	foreach($array as $key => &$item)
	{
		if
		(
			false !== strpos(substr($item,strripos($item,"<a ")),"rss") ||
                        false !== strpos(substr($item,strripos($item,"<a ")),"rdf") ||
                        false !== strpos(substr($item,strripos($item,"<a ")),"atom") ||
                        false !== strpos(substr($item,strripos($item,"<a ")),"feed") 
		)
		{
			$interestingForItsTitle[$key+1] = true;
		}
		if( ( false !== stripos($item, "<frame") && false === stripos(substr($item,strripos($item,"<frame")), "</frame") ))
		{
			$isFrameURL[$key+1] = true;
		}
		if(false !== stripos($item, "<frame")) { 
			$isFrameURL[$key] = true;
		}
		if(brutallyDiscarded($item)) { unset($array[$key]); $deleted++; continue; } 
		$item = preg_replace('/(\r\n|\r|\n)/s',"",$item);
		$item = str_replace("&amp;","&",$item);
		$item = str_replace("href","",$item);
		$item = str_replace("HREF","",$item);
		$item = preg_replace("/=/","",$item, 1);
		$item = trim($item);
		if(strpos($item,"\"") !== 0 && strpos($item, "'") !== 0) { unset($array[$key]); $deleted++; continue; } 
		$item = trim(substr($item, 1, strpos($item,substr($item,0,1),1)-1));
		if(false !== strpos($item, "#")) $item = substr($item, 0, strpos($item,"#"));
		if($item == "") { unset($array[$key]); $deleted++; continue; }

		if(stripos($item, "http://") !== 0 && stripos($item, "https://") !== 0 && strpos($item, "/") !== 0) $item = substr($path,0,strrpos($path,"/"))."/".$item;
		else 
		{
			if(strlen($path) < 9) { unset($array[$key]); $deleted++; continue; }
			if(0 === strpos($item, "/")) $item = substr($path,0,1+strpos($path,"/",8)).substr($item,1);
		}
		if(strlen($item) < 9) { unset($array[$key]); $deleted++; continue; }
		if(false === strpos($item,"/",8)) $item.="/";
		if(false !== strpos($item, "?"))
		{
			if(!dbIsInPages(substr($item,0,strpos($item,"?"))))
			{
				dbAddToPages(substr($item,0,strpos($item,"?")));
			}
			else 
			{ 
		                if
        		        (!(
		                        ( isset($interestingForItsTitle[$key]) && $interestingForItsTitle[$key] ) ||
					false !== stripos($item, "rss") ||
                		        false !== stripos($item, "rdf") ||
		                        false !== stripos($item, "atom") ||
                		        false !== stripos($item, "feed") ||
		                        ( false !== stripos($item, "xml") && false === stripos($item, "xmlrpc") ) ||
                		        false !== stripos($item, "backend") ||
		                        false !== stripos($item, "channel") ||
                		        false !== stripos($item, "subscribe")
                		))
				{
					unset($array[$key]); 
					$deleted++; 
					continue; 
				}
			} 
		}
		if(false !== strpos($item, ";"))
                {
                        if(!dbIsInPages(substr($item,0,strpos($item,";"))))
                        {
                                dbAddToPages(substr($item,0,strpos($item,";")));
                        }
                        else
                        {
                                if
                                (!(
					( isset($interestingForItsTitle[$key]) && $interestingForItsTitle[$key] ) ||
                                        false !== stripos($item, "rss") ||
                                        false !== stripos($item, "rdf") ||
                                        false !== stripos($item, "atom") ||
                                        false !== stripos($item, "feed") ||
                                        ( false !== stripos($item, "xml") && false === stripos($item, "xmlrpc") ) ||
					false !== stripos($item, "backend") ||
                                        false !== stripos($item, "channel") ||
                                        false !== stripos($item, "subscribe")
                                ))
                                {
                                        unset($array[$key]);
                                        $deleted++;
                                        continue;
                                }
                        }
                }
		
		if
                (
			(
                        	false !== stripos($item, "rss") ||
	                        false !== stripos($item, "rdf") ||
        	                false !== stripos($item, "atom") ||
                	        false !== stripos($item, "feed") ||
				( false !== stripos($item, "xml") && false === stripos($item, "xmlrpc") ) ||
	                        false !== stripos($item, "backend") ||
        	                false !== stripos($item, "channel") ||
                	        false !== stripos($item, "subscribe") ||
				(isset($interestingForItsTitle[$key]) && $interestingForItsTitle[$key] )
			)
			&&
			(!(
				false !== stripos($item, ".ico") ||
				false !== stripos($item, ".gif") ||
				false !== stripos($item, ".tif") ||
				false !== stripos($item, ".png") ||
				false !== stripos($item, ".jpg") ||
				false !== stripos($item, ".jpeg") ||
				false !== stripos($item, ".pdf") ||
				false !== stripos($item, ".zip") ||
				false !== stripos($item, ".tar") ||
				false !== stripos($item, ".tar.gz") ||
				false !== stripos($item, ".tar.bz2") ||
				false !== stripos($item, ".p7m") ||
				false !== stripos($item, ".css") ||
				false !== stripos($item, ".wma") ||
				false !== stripos($item, ".mpg") ||
				false !== stripos($item, ".mpeg") ||
				false !== stripos($item, ".wmv") ||
				false !== stripos($item, ".mp3") ||
				false !== stripos($item, ".mp4") ||
				( false !== stripos($item, ".js") && stripos($item, ".js") != stripos($item, ".jsp")) ||
				false !== stripos($item, "mailto:") ||
				false !== stripos($item, "download") ||
				false !== stripos($item, "/en/") ||
				false !== stripos($item, "/fr/") ||
				false !== stripos($item, "/de/") ||
				false !== stripos($item, "/es/") ||
				false !== stripos($item, "/en.") ||
				false !== stripos($item, "/fr.") ||
				false !== stripos($item, "/de.") ||
				false !== stripos($item, "/es.") ||
                                false !== stripos($item, "/index_en.") ||
                                false !== stripos($item, "/index_fr.") ||
                                false !== stripos($item, "/index_de.") ||
                                false !== stripos($item, "/index_es.") ||
				false !== stripos($item, "/image") ||
				false !== stripos($item, "/pdf") ||
				false !== stripos($item, ".ppt") ||
				false !== stripos($item, ".pps") ||
				false !== stripos($item, "flickr") ||
				false !== stripos($item, "facebook") ||
				false !== stripos($item, "youtube") ||
				false !== stripos($item, "twitter") ||
				false !== stripos($item, ".doc") ||
				false !== stripos($item, ".xls") ||
				false !== stripos($item, ".csv") ||
				(false !== stripos($item, "lang=") && false === stripos($item, "lang=it")) ||
				(false !== stripos($item, urlencode("lang=")) && false === stripos($item, urlencode("lang=it"))) ||
                        	(false !== stripos($item, "language=") && false === stripos($item, "language=it")) ||
	                        (false !== stripos($item, urlencode("language=")) && false === stripos($item, urlencode("language=it"))) ||
				false !== stripos($item, "europa.eu") ||
				false !== stripos($item, "javascript") ||
				false !== stripos($item, ".exe") ||
				false !== stripos($item, ".msi") ||
				false !== stripos($item, ".mov") ||
                                false !== stripos($item, ".wav") ||
                                false !== stripos($item, "(") ||
                                false !== stripos($item, ")") ||
				false !== stripos($item, ".flv") 
			))
                )
                {

			$interestingLinkFound = true;
			if(!dbIsInterestingLink($item))
			{
				dbAddToInterestingLinks($item);
			}
			else
			{
				unset($array[$key]);
				$deleted++;
				continue;
			} 
		}
		else
		{
			$qsStrippedItem = $item; 
			if(false !== strpos($item, "?")) $qsStrippedItem = substr($item, 0, strpos($item, "?"));
			if(false !== strpos($item, ";")) $qsStrippedItem = substr($item, 0, strpos($item, ";"));
			if(dbIsInPreviousLevel($qsStrippedItem)) { unset($array[$key]); $deleted++; continue; }
			if(dbIsInPreviousLevel($qsStrippedItem."/")) { unset($array[$key]); $deleted++; continue; }
			if(dbIsInPreviousLevel(substr(trim($qsStrippedItem),0,strlen(trim($qsStrippedItem))-1))) { unset($array[$key]); $deleted++; continue; }

			$itemdomain = substr($item,0,strpos($item,"/",8))."/";
			if(0 !== stripos($path, $itemdomain) && (!is_numeric(str_replace("/","",str_replace("https://","",str_replace("http://","",str_replace(".","",strtolower($itemdomain))))))) && (0 !== stripos($itemdomain, "http://www.halleyweb.com/")))
			{
				if
				(

					0 !== stripos($path, $website) &&
					0 !== stripos($path, str_replace("http://","http://www.",$website)) &&
					0 !== stripos($path, str_replace("https://","https://www.",$website)) &&
					0 !== stripos($path, str_replace("www.","",$website)) &&

					0 !== stripos($path, $itemdomain) &&
					0 !== stripos($path, str_replace("http://","http://www.",$itemdomain)) &&
					0 !== stripos($path, str_replace("https://","https://www.",$itemdomain)) &&
					0 !== stripos($path, str_replace("www.","",$itemdomain)) &&

					0 !== stripos($path, substr($website, 0, strpos($website."/","/",8)))

				)
				{
					unset($array[$key]); $deleted++; continue;
				}
				else if
				(
					dbIsInPreviousLevel($itemdomain) ||
					dbIsInPreviousLevel(str_replace("http://","http://www.",$itemdomain)) ||
					dbIsInPreviousLevel(str_replace("www.","",$itemdomain))
				)
				{
					unset($array[$key]); $deleted++; continue;
				}
				else
				{

				}
			}
			
			if($key > 0 && in_array($item, array_slice($array,0,$key-$deleted))) { unset($array[$key]); $deleted++; continue; }
			
			if
			(
				false !== stripos($item, ".xml") ||
				false !== stripos($item, ".ico") ||
				false !== stripos($item, ".gif") ||
				false !== stripos($item, ".tif") ||
				false !== stripos($item, ".png") ||
				false !== stripos($item, ".jpg") ||
				false !== stripos($item, ".jpeg") ||
				false !== stripos($item, ".pdf") ||
				false !== stripos($item, ".zip") ||
				false !== stripos($item, ".tar") ||
				false !== stripos($item, ".tar.gz") ||
				false !== stripos($item, ".tar.bz2") ||
				false !== stripos($item, ".p7m") ||
				false !== stripos($item, ".css") ||
				false !== stripos($item, ".wma") ||
				false !== stripos($item, ".mpg") ||
				false !== stripos($item, ".mpeg") ||
				false !== stripos($item, ".wmv") ||
				false !== stripos($item, ".mp3") ||
				false !== stripos($item, ".mp4") ||
				( false !== stripos($item, ".js") && stripos($item, ".js") != stripos($item, ".jsp") ) || 
				false !== stripos($item, "mailto:") ||
				false !== stripos($item, "download") ||
				false !== stripos($item, "/en/") ||
				false !== stripos($item, "/fr/") ||
				false !== stripos($item, "/de/") ||
				false !== stripos($item, "/es/") ||
				false !== stripos($item, "/en.") ||
				false !== stripos($item, "/fr.") ||
				false !== stripos($item, "/de.") ||
				false !== stripos($item, "/es.") ||
                                false !== stripos($item, "/index_en.") ||
                                false !== stripos($item, "/index_fr.") ||
                                false !== stripos($item, "/index_de.") ||
                                false !== stripos($item, "/index_es.") ||
				false !== stripos($item, "/image") ||
				false !== stripos($item, "/pdf") ||
				false !== stripos($item, ".ppt") ||
				false !== stripos($item, ".pps") ||
				false !== stripos($item, "flickr") ||
				false !== stripos($item, "facebook") ||
				false !== stripos($item, "youtube") ||
				false !== stripos($item, "twitter") ||
				false !== stripos($item, ".doc") ||
				false !== stripos($item, ".xls") ||
				false !== stripos($item, ".csv") ||
				(false !== stripos($item, "lang=") && false === stripos($item, "lang=it")) ||
				(false !== stripos($item, urlencode("lang=")) && false === stripos($item, urlencode("lang=it"))) ||
                        	(false !== stripos($item, "language=") && false === stripos($item, "language=it")) ||
	                        (false !== stripos($item, urlencode("language=")) && false === stripos($item, urlencode("language=it"))) ||
				false !== stripos($item, "europa.eu") ||
				false !== stripos($item, "javascript") ||
				false !== stripos($item, ".exe") ||
				false !== stripos($item, ".msi") ||
				false !== stripos($item, ".mov") ||
                                false !== stripos($item, ".wav") ||
				false !== stripos($item, ".flv") ||
				false !== stripos($item, "(") ||
				false !== stripos($item, ")")
			)
			{
				unset($array[$key]); 
				$deleted++; 
				continue;
			}
		
		}

	}
	
	if(false && $interestingLinkFound)
	{
		foreach($array as $key => &$item)
		{
			if
			(
				(!(
	                        false !== stripos($item, "rss") ||
        	                false !== stripos($item, "rdf") ||
                	        false !== stripos($item, "atom") ||
                        	false !== stripos($item, "feed") ||
	                        ( false !== stripos($item, "xml") && false === stripos($item, "xmlrpc") ) ||
        	                false !== stripos($item, "backend") ||
                	        false !== stripos($item, "channel") ||
                        	false !== stripos($item, "subscribe") ||
				(isset($interestingForItsTitle[$key]) && $interestingForItsTitle[$key] )
				))
				&&
				(
					0 === strpos($item, substr($path,0,strpos($path,"/",8))) ||
                                        0 === strpos($item, str_replace("http://","http://www.",substr($path,0,strpos($path,"/",8)))) ||
                                        0 === strpos($item, str_replace("www.","",substr($path,0,strpos($path,"/",8)))) 
				)
			)
			{
				unset($array[$key]);
			}
		}
	}

}

function download($url, $path)
{

exec("rm $file_orig");
exec("rm $folder_log/download");
mylog("download", date("d/m/Y H:i:s")." [INFO] Start download from $url\n");
mylog("download", date("d/m/Y H:i:s")." [INFO] Downloading page through wget for debug purposes.\n");
exec("wget -O $file_orig -t 1 -T 60 \"$url\" &>/dev/null || echo \"DOWNLOAD FAILED\"");
mylog("download", date("d/m/Y H:i:s")." [INFO] Debug download completed.\n");


if(!seemsWebPageToBeFollowed($url)) { 
mylog("download", date("d/m/Y H:i:s")." [INFO] It does not seem a web page to be downloaded. Download fails.\n");
return false; 
}

mylog("download", date("d/m/Y H:i:s")." [INFO] Seems a web page to be downloaded.\n");

$timeout = 60;

	$url = str_replace("&amp;","&",$url);
	if(false !== stripos($url, "(") || false !== stripos($url, ")") ) return false;
	if(strlen($url) > 2083) return false;
	$redirectionTracker = array();
	$responseHeaders = array();
	$rawResponseHeaders = @http_head($url, array('timeout'=>$timeout), $responseHeaders);
	if(trim($rawResponseHeaders) == "") return false;
	$responseHeaders = http_parse_message($rawResponseHeaders);
	while($responseHeaders->responseCode == 301 || $responseHeaders->responseCode == 302 || $responseHeaders->responseCode == 303)
	{

		if(in_array($url, $redirectionTracker)) return false;

		$redirectionTracker[] = $url;

        	if($responseHeaders->responseCode == 302 && isset($responseHeaders->headers["Location"]) && trim($responseHeaders->headers["Location"]) != "" && trim($responseHeaders->headers["Location"]) != trim($url))
	        {
			if(0 === strpos($responseHeaders->headers["Location"],"http://") || 0 === strpos($responseHeaders->headers["Location"],"https://"))
                	{
	                        $url = $responseHeaders->headers["Location"];
        	        }
                	else
	                {
        	                if(substr($responseHeaders->headers["Location"],0,2) == ".." && substr($url,strlen($url)-1) != "/") $responseHeaders->headers["Location"] = substr($responseHeaders->headers["Location"],2);
				else if(substr($responseHeaders->headers["Location"],0,1) != "/" && substr($url,strlen($url)-1) != "/") $responseHeaders->headers["Location"] = "/".$responseHeaders->headers["Location"];
				else if(substr($responseHeaders->headers["Location"],0,1) == "/") $url = substr($url,0,strpos($url,"/",8));
				$url = $url.($responseHeaders->headers["Location"]);
                	}
	        }

        	if($responseHeaders->responseCode == 301 && isset($responseHeaders->headers["Location"]) && trim($responseHeaders->headers["Location"]) != ""  && trim($responseHeaders->headers["Location"]) != trim($url)) 
	        {
        	        if(0 === strpos($responseHeaders->headers["Location"],"http://") || 0 === strpos($responseHeaders->headers["Location"],"https://"))
                	{
	                        $url = $responseHeaders->headers["Location"];
        	        }
                	else
	                {
                                if(substr($responseHeaders->headers["Location"],0,2) == ".." && substr($url, strlen($url)-1) != "/") $responseHeaders->headers["Location"] = substr($responseHeaders->headers["Location"],2);
                                else if(substr($responseHeaders->headers["Location"],0,1) != "/" && substr($url,strlen($url)-1) != "/") $responseHeaders->headers["Location"] = "/".$responseHeaders->headers["Location"];
                                else if(substr($responseHeaders->headers["Location"],0,1) == "/") $url = substr($url,0,strpos($url,"/",8));

        	                $url = $url.($responseHeaders->headers["Location"]);
                	}
	        }



                if($responseHeaders->responseCode == 303 && isset($responseHeaders->headers["Location"]) && trim($responseHeaders->headers["Location"]) != ""   && trim($responseHeaders->headers["Location"]) != trim($url)) 
                {
                        if(0 === strpos($responseHeaders->headers["Location"],"http://") || 0 === strpos($responseHeaders->headers["Location"],"https://"))
                        {
                                $url = $responseHeaders->headers["Location"];
                        }
                        else
                        {
                                if(substr($responseHeaders->headers["Location"],0,2) == ".." && substr($url, strlen($url)-1) != "/") $responseHeaders->headers["Location"] = substr($responseHeaders->headers["Location"],2);
                                else if(substr($responseHeaders->headers["Location"],0,1) != "/" && substr($url,strlen($url)-1) != "/") $responseHeaders->headers["Location"] = "/".$responseHeaders->headers["Location"];
                                else if(substr($responseHeaders->headers["Location"],0,1) == "/") $url = substr($url,0,strpos($url,"/",8));

                                $url = $url.($responseHeaders->headers["Location"]);
                        }
                }


		if(!(isset($responseHeaders->headers["Location"]) && trim($responseHeaders->headers["Location"]) != "")) return false;
		if(strlen($url) > 2083) return false;
		mylog("download", date("d/m/Y H:i:s")." [INFO] Redirecting...\n");

		if(!seemsWebPageToBeFollowed($url)) return false;

        	$responseHeaders = array();
		$rawResponseHeaders = @http_head($url, array('timeout'=>$timeout), $responseHeaders);
	        if(trim($rawResponseHeaders) == "") return false;
	        $responseHeaders = http_parse_message($rawResponseHeaders);

		mylog("download", date("d/m/Y H:i:s")." [INFO] Redirected to $url\n");

	}


	exec("rm -f $path");

        $newfname = $path;
	$htmlStarts = false;
	$htmlEnds = false;

	if(strlen($url) > 2083) 
	{
		mylog("download", date("d/m/Y H:i:s")." [INFO] Invalid URL. Download fails.\n");
		return false;
	}

	mylog("download", date("d/m/Y H:i:s")." [INFO] Beginning actual download.\n");


        $context = stream_context_create( array(
                'http'=>array(
                    'timeout' => $timeout
                  )
        ));
       
	mylog("download", date("d/m/Y H:i:s")." [INFO] Stream context setup complete.\n");
 
	$emptyCounter = 0;
	$downloadFailedInProgress = false;

	$file = @fopen($url, "rb",false, $context);

	mylog("download", date("d/m/Y H:i:s")." [INFO] Attempted to open fopen stream.\n");

        if($file)
        {

		mylog("download", date("d/m/Y H:i:s")." [INFO] Fopen stream opened.\n");

                $newf = fopen($newfname, "wb");

		mylog("download", date("d/m/Y H:i:s")." [INFO] Attemped to open destination fopen stream.\n");

                if($newf)
                {

			mylog("download", date("d/m/Y H:i:s")." [INFO] Destination fopen stream opened.\n");

			mylog("download", date("d/m/Y H:i:s")." [INFO] Downloading via fopen.\n");

                        while(!feof($file))
                        {

				$fragment =  fread($file, 1024 * 8 );
				if(!trim($fragment)) $emptyCounter++;
				if($emptyCounter == 10)
				{
					$downloadFailedInProgress = true;
					break;
				} 
				mylog("download", date("d/m/Y H:i:s")." [INFO] $fragment\n");

		                if(false !== stripos($fragment, "<html")) $htmlStarts = true;
                		if(false !== stripos($fragment, "</html")) $htmlEnds = true;

				mylog("download", date("d/m/Y H:i:s")." [INFO] Writing it\n");

				fwrite($newf, $fragment, 1024 * 8 );

                        }

                        fclose($newf);

                }

                fclose($file);

        }
        else
        {
		mylog("download", date("d/m/Y H:i:s")." [INFO] Downloading via wget.\n");

		$wget_attempt = shell_exec("wget -O $path -t 1 -T $timeout \"$url\" &>/dev/null || echo \"DOWNLOAD FAILED\"");
                if(false !== strpos($wget_attempt,"DOWNLOAD FAILED")) return false;

        }

	if($downloadFailedInProgress)
	{
		exec("rm -f $newfname");
		mylog("download", date("d/m/Y H:i:s")." [INFO] fopen download failed in progress. Downloading via wget.\n");
                $wget_attempt = shell_exec("wget -O $path -t 1 -T $timeout \"$url\" &>/dev/null || echo \"DOWNLOAD FAILED\"");
                if(false !== strpos($wget_attempt,"DOWNLOAD FAILED")) return false;	
	}
	
	mylog("download", date("d/m/Y H:i:s")." [INFO] Actual download completed.\n");

	if($htmlStarts && (!$htmlEnds))
	{
		mylog("download", date("d/m/Y H:i:s")." [INFO] Bad download. Retrying...\n");

		unlink($path);
                $wget_attempt = shell_exec("wget -O $path -t 1 -T $timeout \"$url\" &>/dev/null || echo \"DOWNLOAD FAILED\"");
                if(false !== strpos($wget_attempt,"DOWNLOAD FAILED")) 
		{
			mylog("download", date("d/m/Y H:i:s")." [INFO] Untidy. Download fails.\n");
			return false;
		}
		mylog("download", date("d/m/Y H:i:s")." [INFO] Successfull retry.\n");

	}
	
	mylog("download", date("d/m/Y H:i:s")." [INFO] Uncompressing...\n");

	if(!uncompress($path, "$path.unzip")) 
	{
		mylog("download", date("d/m/Y H:i:s")." [INFO] Failed. Download fails.\n");
		return false;
	}
	exec("rm -f $path");
	exec("mv $path.unzip $path");

	if(!file_exists($path)) 
	{
		mylog("download", date("d/m/Y H:i:s")." [INFO] Failed! Download fails.\n");
		return false;
	}

	mylog("download", date("d/m/Y H:i:s")." [INFO] Checking MIME type...\n");

	if
	(
		false === stripos(mime_content_type($path),"text") &&
		false === stripos(mime_content_type($path),"xml")
	) 
	{
		mylog("download", date("d/m/Y H:i:s")." [INFO] Unexpected MIME type. Download fails.\n");
		return false;
	}
	
	if(filesize($path) < 10000000)
	{
	
		mylog("download", date("d/m/Y H:i:s")." [INFO] Starting computations on downloaded page.\n");
	
		$pathfile = fopen($path, "rb");
		
		$pagestr = fread($pathfile, 10000000);


		fclose($pathfile);

		mylog("download", date("d/m/Y H:i:s")." [INFO] Checking for javascript redirection...\n");

		if(false === stripos($pagestr, "<html") && false === stripos($pagestr, "<?xml") && false === stripos($pagestr, "<meta") && false === stripos($pagestr, "<rss") && false === stripos($pagestr, "<feed") && false === stripos($pagestr, "<rdf"))  
		{
			if(false !== stripos($pagestr, "<script") && false !== stripos($pagestr, "http"))
			{
				$redirectionAttempt = substr($pagestr, stripos($pagestr, "http"));
				if
				(
					( false !== stripos($redirectionAttempt,"\"") && false === stripos($redirectionAttempt,"'") ) ||
					( false !== stripos($redirectionAttempt,"\"") && false !== stripos($redirectionAttempt, "'") && stripos($redirectionAttempt,"\"") < stripos($redirectionAttempt,"'"))
				)
				{
					$redirectionAttempt = substr($redirectionAttempt,0,stripos($redirectionAttempt,"\""));
					$actualRedirectionAttemptUrl = download($redirectionAttempt, $path);
					return $actualRedirectionAttemptUrl;
				}
				else if
				(
					(false !== stripos($redirectionAttempt, "'") && false === stripos($redirectionAttempt,"\"") ) ||
					( false !== stripos($redirectionAttempt, "'") && false !== stripos($redirectionAttempt, "\"") && stripos($redirectionAttempt,"'") < stripos($redirectionAttempt, "\""))
				)
				{
					$redirectionAttempt = substr($redirectionAttempt, 0, stripos($redirectionAttempt, "'"));
					$actualRedirectionAttemptUrl = download($redirectionAttempt,$path);
					return $actualRedirectionAttemptUrl;
				}
				else
				{
					return false;
				}
			}
			return false;
		}
		
		mylog("download", date("d/m/Y H:i:s")." [INFO] Removing comments.\n");


		$pagestr = explode("<!--",$pagestr);
		for($i = 0; $i < count($pagestr); $i++) 
		{
			if(false !== strpos($pagestr[$i],"<!--") && false !== strpos($pagestr[$i],"-->")) 
			{
				if
				(!(
					$i > 0 &&
					(
						(false !== stripos($pagestr[$i-1],"<script") && false === stripos($pagestr[$i-1],"</script")) ||
						(false !== stripos($pagestr[$i-1],"<script") && false !== stripos($pagestr[$i-1],"</script") && strrpos($pagestr[$i-1],"<script") > strrpos($pagestr[$i-1],"</script"))
					)
				))
				{
					$pagestr[$i] = substr($pagestr[$i], 3+strpos($pagestr[$i],"-->"));
				}
			}
		}
		$pagestr = implode(" ",$pagestr);

		mylog("download", date("d/m/Y H:i:s")." [INFO] Checking for META redirection...\n");


		if(stripos(trim($pagestr),"<meta") !== 0) $metas = array_merge(array_slice(explode("<meta",$pagestr),1),array_slice(explode("<META",$pagestr),1));
		else $metas = array_merge(explode("<meta",$pagestr),explode("<META",$pagestr));
		
		foreach($metas as $meta)
		{
        
			if
			(
				false !== stripos($meta, "http-equiv") && 
				false !== stripos($meta, "refresh") && 
				false !== stripos($meta, "content") && 
				false !== stripos($meta, "url")
			)
			{
		        	$murl = substr($meta, stripos($meta, "url"));
			        $murl = str_replace("url","",$murl);
			        $murl = str_replace("URL","",$murl);
			        $murl = preg_replace("/=/","",$murl,1);
		        	if(false !== strpos($murl, "\"")) $murl = substr($murl,0,strpos($murl,"\""));
			        else $murl = substr($murl, 0, strpos($murl, "'"));
				$murl = trim($murl);
				if(0 === stripos($murl, "http://") || 0 === stripos($murl, "https://"))
		        	{
			                if(false === strpos($murl,"/",8)) { $murl.="/"; }
			        }
			        else if(0 === stripos($murl, "www"))
       				{
			                $murl = "http://$murl";
			                if(false === strpos($murl,"/",8)) { $murl.="/"; }
       				}
		        	else if(0 === stripos($murl, "/"))
	       			{
					$murl = substr($url,0,strpos($url,"/",8)).$murl;
			        }
		        	else
			        {
					$murl = substr($url,0,strrpos($url,"/"))."/".$murl;
			        }

				$murl = html_entity_decode($murl);
				if(dbIsInPreviousLevel($murl)) return false;
				else dbAddToPreviousLevel(array($murl));
				if(!seemsWebPageToBeFollowed($murl)) return false;
				$actualurl = download($murl, $path);
				return $actualurl;

			}

		}

		$file = fopen($path, "w"); fwrite($file, $pagestr); fclose($file);
	
	}
	else
	{
		mylog("download", date("d/m/Y H:i:s")." [INFO] File too big. Download fails.\n");

		return false;
	}
        
	mylog("download", date("d/m/Y H:i:s")." [INFO] Checking if page was already explored...\n");

	while(false !== strpos($url, "/../"))
        {
                $backpos = strpos($url, "/../");
		$suboff = 0;
                if(-1-(strlen($url)-$backpos) > -strlen($url)) $suboff = strrpos($url, "/", -1-(strlen($url)-$backpos));
                $url = substr($url, 0, $suboff).substr($url,3+$backpos);
	        if(dbIsInPreviousLevel($url)) 
		{
			mylog("download", date("d/m/Y H:i:s")." [INFO] Yes it was. Download fails.\n");	
			return false;
		}
        	dbAddToPreviousLevel(array($url));
        }

	mylog("download", date("d/m/Y H:i:s")." [INFO] Download was successfull! Returning...\n");

	return $url;

}

function stripQs(&$array, $key)
{
	
	if(isset($array[$key]) && trim($array[$key]) != "")
	{
		
		if(false !== strpos($array[$key], "?")) 
		{
			$array[$key] = substr($array[$key], 0, strpos($array[$key], "?"));
		}
                
		if(false !== strpos($array[$key], ";"))
                {
                        $array[$key] = substr($array[$key], 0, strpos($array[$key], ";"));
                }

	}

}

function mylog($website, $entry)
{
	$logfile = fopen("$folder_log/".preg_replace("/[^A-Za-z0-9]/", "",$website),"a+");
	fwrite($logfile, $entry);
	fclose($logfile);
}

function brutallyDiscarded($rawLink)
{	

	if(false !== stripos($rawLink, "http://www.sitiarcheologici.palazzochigi.it/")) return true;
	if(false !== stripos($rawLink, "http://www.adobe.com/")) return true;
	if(false !== stripos($rawLink, "http://validator.w3.org/")) return true;
	if(false !== stripos($rawLink, "http://rete.comuni-italiani.it/")) return true;
	if(false !== stripos($rawLink, "http://www.comuni-italiani.it/")) return true;
	if(false !== stripos($rawLink, "http://www.tuttitalia.it/")) return true;	
	if(false !== stripos($rawLink, "http://www.w3.org/")) return true;
	return false;

}

function uncompress($srcName, $dstName) {
    $sfp = gzopen($srcName, "rb");
    if(!$sfp) return false;
    $fp = fopen($dstName, "w");

    while ($string = gzread($sfp, 4096)) {
        fwrite($fp, $string, strlen($string));
    }
    gzclose($sfp);
    fclose($fp);
    return true;
}

function seemsWebPageToBeFollowed($item)
{

			if
			(
				(!(
                                        false !== stripos($item, "rss") ||
                                        false !== stripos($item, "rdf") ||
                                        false !== stripos($item, "atom") ||
                                        false !== stripos($item, "feed") ||
                                        ( false !== stripos($item, "xml") && false === stripos($item, "xmlrpc") ) ||
                                        false !== stripos($item, "backend") ||
                                        false !== stripos($item, "channel") ||
                                        false !== stripos($item, "subscribe")
				)) 
				&&
				(
				false !== stripos($item, ".ico") ||
				false !== stripos($item, ".gif") ||
				false !== stripos($item, ".tif") ||
				false !== stripos($item, ".png") ||
				false !== stripos($item, ".jpg") ||
				false !== stripos($item, ".jpeg") ||
				false !== stripos($item, ".pdf") ||
				false !== stripos($item, ".zip") ||
				false !== stripos($item, ".tar") ||
				false !== stripos($item, ".tar.gz") ||
				false !== stripos($item, ".tar.bz2") ||
				false !== stripos($item, ".p7m") ||
				false !== stripos($item, ".css") ||
				false !== stripos($item, ".wma") ||
				false !== stripos($item, ".mpg") ||
				false !== stripos($item, ".mpeg") ||
				false !== stripos($item, ".wmv") ||
				false !== stripos($item, ".mp3") ||
				false !== stripos($item, ".mp4") ||
				false !== stripos($item, ".js") ||
				false !== stripos($item, "mailto:") ||
				false !== stripos($item, "download") ||
				false !== stripos($item, "/en/") ||
				false !== stripos($item, "/fr/") ||
				false !== stripos($item, "/de/") ||
				false !== stripos($item, "/es/") ||
				false !== stripos($item, "/po/") ||
				false !== stripos($item, "/py/") ||
				false !== stripos($item, "/en.") ||
				false !== stripos($item, "/fr.") ||
				false !== stripos($item, "/de.") ||
				false !== stripos($item, "/es.") ||
				false !== stripos($item, "/po.") ||
				false !== stripos($item, "/py.") ||
                                false !== stripos($item, "/index_en.") ||
                                false !== stripos($item, "/index_fr.") ||
                                false !== stripos($item, "/index_de.") ||
                                false !== stripos($item, "/index_es.") ||
                                false !== stripos($item, "/index_po.") ||
                                false !== stripos($item, "/index_py.") ||	
				false !== stripos($item, "/image") ||	
				false !== stripos($item, "/pdf") ||
				false !== stripos($item, ".ppt") ||
				false !== stripos($item, ".pps") ||
				false !== stripos($item, "flickr") ||
				false !== stripos($item, "facebook") ||
				false !== stripos($item, "youtube") ||
				false !== stripos($item, "twitter") ||
				false !== stripos($item, ".doc") ||
				false !== stripos($item, ".xls") ||
				false !== stripos($item, ".csv") ||
				(false !== stripos($item, "lang=") && false === stripos($item, "lang=it")) ||
				(false !== stripos($item, urlencode("lang=")) && false === stripos($item, urlencode("lang=it"))) ||
                        	(false !== stripos($item, "language=") && false === stripos($item, "language=it")) ||
	                        (false !== stripos($item, urlencode("language=")) && false === stripos($item, urlencode("language=it"))) ||
				false !== stripos($item, "europa.eu") ||
				false !== stripos($item, "javascript") ||
				false !== stripos($item, ".exe") ||
				false !== stripos($item, ".msi") ||
				false !== stripos($item, ".mov") ||
                                false !== stripos($item, ".wav") ||
				false !== stripos($item, ".flv") ||
				false !== stripos($item, ".cgi") ||
				false !== stripos($item, "(") ||
				false !== stripos($item, ")")
				)
			)
			{
				return false;
			}
			else
			{
				return true;
			}
	
}

function trimAll(&$item, $key) 
{
	$item = trim($item);
}

function dbAddToNewWebsites($item)
{
	$conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        $item = mysqli_escape_string($conn, trim($item));
        mysqli_query($conn, "INSERT INTO $dbtable_enw(item) VALUES ('$item')");
        mysqli_close($conn);
}

function dbAddToPages($item)
{
        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        $item = mysqli_escape_string($conn, trim($item));
        mysqli_query($conn, "INSERT INTO $dbtable_ep(item) VALUES ('$item')");
        mysqli_close($conn);
}

function dbAddToInterestingLinks($item)
{
        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        $item = mysqli_escape_string($conn, trim($item));
        mysqli_query($conn, "INSERT INTO $dbtable_eil(item) VALUES ('$item')");
        mysqli_close($conn);
}

function dbAddToPreviousLevel($items)
{
        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        foreach($items as $item) mysqli_query($conn, "INSERT INTO $dbtable_epl(item) VALUES ('".mysqli_escape_string($conn, trim($item))."')");
        mysqli_close($conn);
}

function dbIsInPreviousLevel($item)
{
	if(false !== stripos($item, "halleyweb.com")) return false;
        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        $item = mysqli_escape_string($conn, trim($item));
	$response = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM $dbtable_epl WHERE item = '$item'"));
        mysqli_close($conn);
	return $response;
}

function dbIsInPages($item)
{
        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        $item = mysqli_escape_string($conn, trim($item));
        $response = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM $dbtable_ep WHERE item = '$item'"));
        mysqli_close($conn);
        return $response;
}

function dbIsInNewWebsites($item)
{
        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        $item = mysqli_escape_string($conn, trim($item));
	$response = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM $dbtable_enw WHERE item = '$item'"));
        mysqli_close($conn);
        return $response;
}

function dbIsInterestingLink($item)
{
        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        $item = mysqli_escape_string($conn, trim($item));
        $response = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM $dbtable_eil WHERE item = '$item'"));
        mysqli_close($conn);
        return $response;
}

function dbInitPreviousLevel()
{
        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        mysqli_query($conn, "TRUNCATE TABLE $dbtable_epl");
	mysqli_query($conn, "INSERT INTO $dbtable_epl(item) SELECT item FROM $dbtable_enw");
        mysqli_close($conn);

}

function dbInitPages()
{
        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        mysqli_query($conn, "TRUNCATE TABLE $dbtable_ep");
        mysqli_close($conn);

}

function dbInitInterestingLinks()
{
        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
        mysqli_query($conn, "TRUNCATE TABLE $dbtable_eil");
        mysqli_close($conn);
}

function dbInitNewWebsites()
{
        $conn = mysqli_connect($dbconn_hostname,$dbconn_username,$dbconn_password,$dbconn_dbname);
	mysqli_query($conn, "TRUNCATE TABLE $dbtable_enw");
	$newWebsites = array();
        if(file_exists("$file_wsl")) 
	{
		$newWebsites = explode("\n",file_get_contents("$file_wsl"));
		exec("cp $file_wsl $file_input");
	}
	foreach($newWebsites as $website) mysqli_query($conn, "INSERT INTO $dbtable_enw(item) VALUES ('".mysqli_escape_string($conn, trim($website))."')");
        mysqli_close($conn);
}

function declaredBaseHref($pagestr)
{
                if(false !== stripos($pagestr, "<base "))
                {
                        $basehref = substr($pagestr, stripos($pagestr,"<base "), stripos($pagestr, ">", stripos($pagestr,"<base "))-stripos($pagestr,"<base "));
                        $basehref = trim(substr($basehref,5));
                        if(stripos($basehref,"href") === 0)
                        {
                                $basehref = trim(substr($basehref,4));
                                if(stripos($basehref,"=") === 0)
                                {
                                        $basehref = trim(substr($basehref,1));
                                        if(stripos($basehref, "\"") === 0) $basehref = substr($basehref,1);
                                        if(stripos($basehref, "'") === 0) $basehref = substr($basehref,1);
                                        $endingspacepos = stripos($basehref, " ");
                                        $endingquotepos = stripos($basehref, "'");
                                        $endingdquotepos = stripos($basehref, "\"");
                                        if($endingspacepos !== false && ($endingquotepos === false || $endingquotepos > $endingspacepos) && ($endingdquotepos === false || $endingdquotepos > $endingspacepos)) return substr($basehref,0,stripos($basehref," "));
                                        if($endingquotepos !== false && ($endingspacepos === false || $endingspacepos > $endingquotepos) && ($endingdquotepos === false || $endingdquotepos > $endingquotepos)) return substr($basehref,0,stripos($basehref,"'"));
                                        if($endingdquotepos !== false && ($endingspacepos === false || $endingspacepos > $endingdquotepos) && ($endingquotepos === false || $endingquotepos > $endingdquotepos)) return substr($basehref,0,stripos($basehref,"\""));
                                }

                        }


                }
return false;
}
?>
