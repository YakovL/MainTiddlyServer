<?php
// MainTiddlyServer
$version = '1.6.1';
// MIT-licensed (see https://yakovl.github.io/MainTiddlyServer/license.html)
$debug_mode = false;

// "no cache" headers to always get up-to-date TW content (not loaded from cache)
// especially important on Adroid, since aggressive task killer unloads browsers from RAM quite often
// important: avoid BOM in this script: that causes warnings instead of setting headers
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies
// solution was taken from https://stackoverflow.com/q/49547/

/*
This PHP script allows TiddlyWiki to save directly onto an HTTP server.
To install, simply copy the index.php and the TiddlyWiki HTML file onto your web server,
then open the address of this script in a web browser.
You will then be asked to perform some initial configuration, after which you can save your wiki file on your website.

	to do:
	! collect user scenarios (+), design interfaces, make them simple and straight-forward
	 - minimize pages and clicks (showTW: remove extra page..)
	 - improve description of memory_limit in ?options + comment source better /.oO can we increase automatically?
	 - tell user password protection won't work when it is so
	 - when saving options/changes fails, notify, don't fail silently (file_put_contents does so)
	- improve interface for the case of no TWs in the workingFolder (both ?options and ?wikis)
	- add settings: server title (instead of MainTiddlyServer), color scheme (2-3-4 colors)
	- make interface look close to that of MTS site: navbar with ?wikis, ?config, ?usage?, ?about (put version history of changes there)
	 - hightlight the current page in navbar
	 * make the interfaces be really shared between MTS and its site (how to?)
	- add docs and history of MTS changes as html served by MTS itself (showDocPage)

	- try http://www.clickteam.com/install-creator-2 for simplifying the installation process on Windows (look for alternatives, too: https://alternativeto.net/software/clickteam-install-creator/)
	- learn how installation can be simplified for Unix-like OSes
	
	- go on implementing working with other folders (see after $workingFolder)
	 - process in $_POST['options'] section the choice of the workingFolder in the options interface
	  - next: make interface consistent (either update the wikis <select> of location choice or remove at all)
	 - retest usage with workingFolder switched to ph: debug current slow including in FF for microrepos
	 - allow including from other dataFolders (next: by w.f.'s aliases /implemented: by relative address)
	 - next: allow choosing workingFolder in interface, visit subfolders (for microrepos), ..

	- allow to switch off proxying until all requests are processed properly
	- either proxy non-GET requests (use $_REQUEST instead?) or limit httpReq hijacking to GET requests
	- pass _any_ request that is not processed directly, through proxy (now those to the same domain won't work)
	- gather proxy implementation issues, test stuff
	 - add checks if getFolderAndFileNameFromPath returned empty folder (for instance, including from http://site.com – with no path at all, like http://site.com/TW.html → http://site.com?wiki=otherTW.thml)
	 * calc $mtsHost in a more reliable way: get rid of port manually: https://stackoverflow.com/a/8909559
	   (wrong values like containing :port can cause MTS infinite loops because of proxying)
	 * go through the proxy_to code and analyse its algorithm for custom ports
	
	- test opportunities of sending requests to web from localhost/proxy:
	 - .oO and try including from other devices
	 - retest (re-implement?) import from remote TWs
	 - (re-)implement TW core upgrading (in the core, not in MTS)
	 .oO what sync did, re-implement? integrate with git?
	 .oO simple interface for getting a list of available plugins and installing them (where to index? .oO UX)
	 - try with various services like CrossRef, scrapping (~GET) and ~social/with push (RSS, mail, BC, etc with back-ends)
	
	.oO updating MTS from a repo (security is paramount!)
	
	- implement image uploading (in MTS + TW)
	 - improve security, error handling 
	  - make sure we got an image, without injected code (or resave it to ~sanitize)
	  . previously (bad idea): try to convert every image to base64 on the client-side instead,
	    see: https://stackoverflow.com/a/37690794/ (retest)
	  . for consideration: [SO question and] https://jehy.github.io/mami/infosec-lab3.html
	 - add request handling
	 - add front-end part, inject into TW ..may be useful: https://codepen.io/anon/pen/mpKaJe?editors=1010
	 - start with uploading favicon (.ico, .png); .oO about security for uploading arbitrary
	 . big goal: create a paste-place for all sorts of files and materials via TW + MTS
	
	- review debug dumps/logging, now controlled by $debug_mode:
	 ? what user may need, what's needed for maintaining (and whether some parts should be switched on/off separately),
	   what should be removed/substituted by autotests; add configuring and review through an interface
	 - remove conflicting dumps to test_store_area_locating.txt
	 * see https://www.loggly.com/ultimate-guide/php-logging-basics/ and http://www.phptherightway.com/ #errors_and_exceptions and #testing
	 * add logging of errors for the POST requests (and all requests themselves? use for sync editing?)
	! add conflict checks to reading/writing TW to prevent data corruption
	 ? does it take place for big TWs?
	- implement real-time updating of content (on the front-end) when used by multiple users
	- test compression by Apache or PHP (see https://stackoverflow.com/q/1862641/) in showTW (online and offline)
	 ! extracting js and css to separate "files" so that they get cached may be much more effective
	- retest readOnly with opening both MTS and html in the same folder,
	  prevent saving/loading chkHttpReadOnly cookie (probably inject into setOption: 'if(name == "chkHttpReadOnly") return')
	- remake core overwriting: change window.saveFile, not (only) saveChanges,
	  security: start with allowing only saving TWs in the . folder (currently supported request) and backups
	* test and add support of TW below 2.6.5 (build autotests)
	* extend isTwLike to recognize PureStore
	* test with IE: is encoding of non-latin letters broken? (change the convertUnicodeToFileFormat patch accordingly)
	* reduce async implementation of asyncLoadOriginal, updateAndSendMain via httpReq
	 . use httpReq("GET",url,callback,paramsToPassToCallback,null,data,contentType) (already written in comment)
	  . set contentType to "application/x-www-form-urlencoded" or omit (this is the default value)
	  ? when httpReq was introduced? (what TW versions we support?)
	 . much code is shared with the new ~saving by patching~ – make it more DRY
	password-protection to-dos:
	- make password field type="password" and add a duplicate field to check if those values coincide
	. until we stop relying on Apache:
	 - add an option to protect only the .php, options and .ht files with password (see TW for details)
	! try to find an external lib to avoid using .htaccess/apache
	  like may be https://github.com/delight-im/PHP-Auth
	 . to make password protection work on Windows, Android, via Apache 2.2.18 and above
	  ? there's no support of htaccess/Apache implementation on Android, right?
	 . crypt is a unix-only solution, non-reliable
	  * support password-protection for Apache 2.2.18 and above, see https://stackoverflow.com/q/41078702/
	    and https://stackoverflow.com/q/11815121/
	  * for implementation, see https://searchengines.guru/archive/index.php/t-234844.html (using htpasswd, 28.05.2008, 05:32) and http://httpd.apache.org/docs/current/misc/password_encryptions.html,
	    or Apache module that uses DB http://httpd.apache.org/docs/2.2/mod/mod_authn_dbd.html
	- implement non-\w containing passwords (or improve ~visibility of the notification) [either non-Apache or non-crypt solution]
	- fix: not all letters of password are used (since crypt uses only the first 8 ones) [either non-Apache or non-crypt solution]
	
	(forked from MTS v2.8.1.0, see https://groups.google.com/forum/#!topic/tiddlywiki/25LbvckJ3S8)
	changes from the original version:
	+ made 'unavailable' error pages respond with 404 (fix an issue with removable storages)
	+ change: now request to MTS without ?.. opens options page if those are not set and wikis otherwise,
	  removed unnecessary "bookmark this" links
	+ added hardcoded $debug_mode flag for further improvement of ~debug logging
	+ fixed: global $baselink missing in showWikisList (causes errors in elder versions of php)
	+ fixed path processing of the proxy (broke including in some cases)
	+ secure data in case server/TW wasn't available during asyncLoadOriginal but was available afterwards during saving (and other cases)
	1.6.0
	+ introduced simple proxy to enable including TWs from TWs served through MTS and to request stuff from web
	  to even overcome CORS! (request to CORS-enabled sites are already available from localhost, though)
	  httpReq is hijacked by the injected JS so that it makes requests to MTS and it proxies those
	+ introduced a template for server interfaces including error pages, improved (colomn wrapper, viewport, ...)
	+ fixed links to TWs with '+' in their name at ?wikis
	+ added an interface to set memory_limit for larger TWs
	+ added support of TW 2.9.1
	+ fixed granulated saving failed to update title when it is not set (<title>\n\n</title>)
	+ renamed to MainTiddlyServer
	1.5.2
	+ added doctype and viewport to ?options and ?wikis interfaces for better view on mobile
	+ fixed major bug of 1.5.x: saving failed for TWs below 2.8.0
	+ fixed major bug of 1.5.x: saving changes to an empty TW 2.8.0+ corrupted TW
	1.5.1
	+ when TW is not chosen, show ?options interface instead of a separate page with a link to it
	+ fix .htaccess creating for paths containing the space symbol
	+ tweaked interfaces to make them look less horrible
	1.5.0
	+ implement "saving by patching" to reduce traffic and further use for sync editing
	 . now if 2+ users can edit /different/ tiddlers and save without interfering, although they won't know about
	   other editors online and won't get the updates made by others (yet)
	 . error messages are now much more helpful
	before 1.5.0
	+ made possible non-conflicting work with multiple TWs simultaneously via 1 MTS (to do so, open them via ?wiki=.. requests)
	 . now saving arbitrary .html in the workingFolder (but not in subfolders) is supported – by ?save=yes&wiki=wikiname.html
	   requests (if MTS/TW is opened through the ?wiki request, this kind of saving is used automatically)
	 . only tw-like htmls (that have a supported version) are listed/saved
	 . it would be better if it's not quite clear from JS that saving any other wiki in the same folder is possible,
	   to use POST ?wiki: this way, JS won't show other server options; it seems not quite possible: we can add the whole
	   .search part to the request to hide the "wiki=" keyword, but it's still visible that .search is used
	+ added no-cache headers to prevent loading non-up-to-date content
	+ added messages on successful saving
	+ backups are saved or not according to the chkSaveBackups option
	+ reduced code by using core updateOriginal method + added a patch for FireFox (that corrupted non-latin letters)
	 . now SiteTitle, SiteSubtitle, MarkupPreHead and such correctly update the HTML of TW
	 . now symbols like non-latin letters are not encoded like л → &#1083; (which reduces filesize)
	+ added usage of location.host instead of location.hostname in getOriginalUrl
	  so now saving works with servers with custom ports as well
	+ added support of TW 2.6.5, 2.9.0
	+ ?wiki=tw_name sets current TW (both opens immediately and saves to options.txt)
	+ support addresses with port number in server messages (add to $baselink, $optionsLink)
	+ ?wikis shows the list of available htmls as ...?wiki=... links,
	  on screens large enough, navigation via keyboard can be used to open a wiki
	+ (experimental) added adaptive font size to the wikis page
	+ added image grab helpers
	+ the chkHttpReadOnly patch is now removed on saving
	+ made saving asynchronous
	+ rewrote options saving using JSON format with indents so that they are easy to edit manually
	+ fixed json_decode problem (added second argument), other minor stuff
	+ set dirty: false on saving response, not when sending request
*/

$injectedJsHelpers = 'function saveOnlineChanges()
{
	asyncLoadOriginal(function(original){
		// on successful original load
		updateAndSendMain(original,confirmMainSaved);
	});
};

// patch so that FireFox does not corrupt the content
//# to be tested with IE, Edge
convertUnicodeToFileFormat = function(s) { return config.browser.isIE ? convertUnicodeToHtmlEntities(s) : s; };

function asyncLoadOriginal(onSuccess) {
	// Load the original and proceed on success
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function() {
		if (this.readyState == 4 && this.status == 200)
			onSuccess(this.responseText);
	}
	xmlhttp.open("GET", getOriginalUrl() + document.location.search, true);
	xmlhttp.send();
};
function updateAndSendMain(original,onSuccess) //rather current HTML than original
{
	// Skip any comment at the start of the file
	var documentStart = original.indexOf("<!DOCTYPE");
	original = original.substring(documentStart);

	var storePosition = locateStoreArea(original);
	var localPath = document.location.toString(); // url to display in the ~saving failed~ message
	var newStore = updateOriginal(original,storePosition,localPath); // new html
	if(!newStore)
		return; // don`t notify: updateOriginal alerts already
	
	var currentPageRequestMatch = (/\?(?:[^&].+)*?(wiki=[^&]+)(&|$)/mg).exec(window.location.search);
	var currentPageRequest = currentPageRequestMatch ? currentPageRequestMatch[1] : "";
	var urlEncodedRequestBody = 
		"save=yes&content=" + encodeURIComponent(newStore)+
		(currentPageRequest ? "&"+currentPageRequest : "")+
		(config.options.chkSaveBackups ? ("&backupid=" + (new Date().convertToYYYYMMDDHHMMSSMMM())) : "");
	
	// And save the new document using a HTML POST request
	var xmlhttp = new XMLHttpRequest();
	xmlhttp.onreadystatechange = function()
	{
		if (this.readyState == 4 && this.status == 200)
			onSuccess();
	}
	xmlhttp.open("POST", getOriginalUrl(), true);
	xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
	xmlhttp.send(urlEncodedRequestBody);
	/*httpReq("POST",getOriginalUrl(),function(success,params,responseText,url,xhr){
		if(success) {
			if(responseText == "saved")
				onSuccess();
			else
				displayMessage("Error while saving. Server:\n"+responseText);
		} else
			displayMessage("Error while saving, failed to reach the server, status: "+xhr.status);
	},null,null,urlEncodedRequestBody);*/
};
function confirmMainSaved() {
	// like in saveMain
	displayMessage(config.messages.mainSaved);
	store.setDirty(false);
};
function getOriginalUrl() {
	// use document.location.host so that custom ports are supported
	return document.location.protocol + "//" + document.location.host + document.location.pathname;
};

function implementOnlineSaving()
{
	// initialize loadedStore, loadedTitle, loadedMarkupBlocks
	var loadedStore, // {} of texts of tiddlers as they should be saved
	    loadedTitle,
	    loadedHTML = window.originalHTML || recreateOriginal(), //# add loading original if this fails
	    loadedMarkupBlocks = {},
	    markupBlocksMeta = {
	        MarkupPreHead:  { blockName:"PRE-HEAD" },
	        MarkupPostHead: { blockName:"POST-HEAD" },
	        MarkupPreBody:  { blockName:"PRE-BODY" },
	        MarkupPostBody: { blockName:"POST-SCRIPT" }
	    }, blockName;
	for(var tiddlerName in markupBlocksMeta)
	{
		blockName = markupBlocksMeta[tiddlerName].blockName;
		markupBlocksMeta[tiddlerName].start =   "<!--"+blockName+"-START-->\n";
		markupBlocksMeta[tiddlerName].end   = "\n<!--"+blockName+"-END-->";
	}

	window.getUpdatedMarkupBlock = function()
	{
		var blockValues = {};
		for(var tiddlerName in markupBlocksMeta)
		{
			// apadted from replaceChunk
			blockValues[tiddlerName] =
				convertUnicodeToFileFormat(store.getRecursiveTiddlerText(tiddlerName,""));
		}
		return blockValues;
	};

	window.refreshLoadedData = function()
	{
		// title
		loadedTitle = convertUnicodeToFileFormat(getPageTitle()).htmlEncode();

		// markup blocks
		var updatedBlocks = window.getUpdatedMarkupBlock();
		for(var tiddlerName in updatedBlocks)
			markupBlocksMeta[tiddlerName].value = updatedBlocks[tiddlerName];

		// store
		loadedStore = {};
		var saver = store.getSaver();
		store.forEachTiddler(function(title,tiddler){
			//loadedStore.addTiddler(jQuery.extend(true, {}, tiddler));
			if(!tiddler.doNotSave())
				loadedStore[title] = saver.externalizeTiddler(store,tiddler);
		});
//# a smarter but may be more fragile approach would be to use diffs calced by getChanges to refresh
	};

	window.getChanges = function() {
		var overallChagnes = {};

		var changedTiddlers = {};
		// hash by title of "deleted"/{added:externalizedText}/{changed:externalizedText}

		var saver = store.getSaver();
		store.forEachTiddler(function(title,tiddler){
			if(tiddler.doNotSave()) return;
			var currentExternalizedText = saver.externalizeTiddler(store,tiddler);
			if(!loadedStore[title]) {
				changedTiddlers[title] = { added:currentExternalizedText };
				return;
			}
			if(currentExternalizedText != loadedStore[title])
				changedTiddlers[title] = { changed:currentExternalizedText };
		});
		for(var title in loadedStore)
			if(!store.fetchTiddler(title))
			changedTiddlers[title] = "deleted";

//# find renamed tiddlers (added + deleted with same text), put 1 "renamed" instead of 1 "deleted" and 1 "added"

		for(var key in changedTiddlers) { // if any changes
			overallChagnes.tiddlers = changedTiddlers; break;
		}

		// check if stored page title was changed
		var currentTitle = convertUnicodeToFileFormat(getPageTitle()).htmlEncode();
		if(currentTitle != loadedTitle)
			overallChagnes.title = currentTitle;

		// check if markupBlocks were updated
		var updatedBlocks = window.getUpdatedMarkupBlock(), blockName;
		for(var tiddlerName in updatedBlocks)
			if(updatedBlocks[tiddlerName] != markupBlocksMeta[tiddlerName].value) {
				overallChagnes.markupBlocks = overallChagnes.markupBlocks || {};
				blockName = markupBlocksMeta[tiddlerName].blockName;
				overallChagnes.markupBlocks[blockName] = updatedBlocks[tiddlerName];
			}

		return overallChagnes;
	}

	window.saveOnlineChanges = function()
	{
		var dataToSend = JSON.stringify(window.getChanges());
		if(dataToSend == "{}")
			return;
		var currentPageRequestMatch = (/\?(?:[^&].+)*?(wiki=[^&]+)(&|$)/mg).exec(window.location.search),
		    currentPageRequest = currentPageRequestMatch ? currentPageRequestMatch[1] : "";
		var urlEncodedRequestBody = "saveChanges="+encodeURIComponent(dataToSend)+
			(currentPageRequest ? "&"+currentPageRequest : "")+
			(config.options.chkSaveBackups ? ("&backupid=" + (new Date().convertToYYYYMMDDHHMMSSMMM())) : "");
	
		httpReq("POST",getOriginalUrl(),function(success,params,responseText,url,xhr){
			if(success) {
				if(responseText == "saved")
					confirmMainSaved();
				else
					displayMessage("Error while saving. Server:\n"+responseText);
			} else
				displayMessage("Error while saving, failed to reach the server, status: "+xhr.status);
		}, null/*params for callback*/,null,urlEncodedRequestBody,"application/x-www-form-urlencoded");
//# "application/x-www-form-urlencoded; charset=UTF-8" is a default contentType, should we omit it?
	};

	// when successfully saved, update loadedStore
	TiddlyWiki.prototype.orig_noRefreshingLoaded_setDirty = store.setDirty;
	TiddlyWiki.prototype.setDirty = function(dirty){
		if(!dirty) window.refreshLoadedData();
		return store.orig_noRefreshingLoaded_setDirty.apply(this,arguments);
	};

	// since getPageTitle uses wikifyPlainText which requires formatter which is calced
	//  after all plugins are loaded, we calc it in advance...
	if(!formatter) {
		formatter = new Formatter(config.formatters);
		window.refreshLoadedData();
		formatter = null;
	}
	//  ...and remove it afterwards for backward compability
//# this probably should be fixed in the core, though (at least we can hijack getPageTitle)
} //implementOnlineSaving

function implementRequestProxying() {
	window.config.orig_noProxy_httpReq = httpReq; //# or use window.httpReq?
	httpReq = function(type,url,callback,params,headers,data,contentType,username,password,allowCache)
	{
		// in case of request to current MTS;
		// we don`t try to guess if urls are the same when the ~index.php bit is omitted/added
		// since we don`t know settings of the index file in the folder;
		// we don`t do this for requests to the same folder/subfolder
		// (that`s the point of the workingFolder fix)
		if(url == getOriginalUrl())
			return window.config.orig_noProxy_httpReq.apply(this,arguments);

		var proxy_url = getOriginalUrl(), // back to MTS
		    request_url = url,
		    proxy_content = "proxy_to=" + encodeURIComponent(request_url);

		// change agruments to make request to MTS` proxy instead:
		// just add request_url to the request body and send to MTS
//# what if its type was not application/x-www-form-urlencoded ?
		url = proxy_url;
		while(arguments.length < 6) // data is the 6th argument and may have been omitted
			[].push.call(arguments,undefined);
		arguments[5] = data ? (proxy_content + "&" + data) : proxy_content;
		return window.config.orig_noProxy_httpReq.apply(this,arguments);
	};
}

function isGranulatedSavingSupported() // TW v2.8.0 and above where recreateOriginal is finished and used
{
	return version.major > 2 || (version.major == 2 && version.minor >= 8);
}

// we need store and other stuff to be defined when we implementOnlineSaving
var noOnlineSavgin_loadPlugins = loadPlugins;
loadPlugins = function() {
	
	implementRequestProxying();
	
	if(isGranulatedSavingSupported())
		implementOnlineSaving();
	else
		; //# suggest to update TW (old "full" saving will be used)
	return noOnlineSavgin_loadPlugins.apply(this,arguments);
}
';

function loadOptions() {

	global $optionsFolder;
	$old_path = $optionsFolder . "/" . "options.txt";
	$new_path = $optionsFolder . "/" . "mts_options.json";
	if (file_exists($new_path))
		return json_decode(file_get_contents($new_path),true);
	if (file_exists($old_path))
		return unserialize(file_get_contents($old_path));
	return null;
}
function saveOptions($options) {

	global $optionsFolder;
	//file_put_contents($optionsFolder . "/" . "options.txt", serialize($options));
	// a fallback for PHP below 5.4.0 (see http://stackoverflow.com/questions/22208831/json-encode-expects-parameter-2-to-be-long-string-given)
	$pretty_print = (JSON_PRETTY_PRINT == "JSON_PRETTY_PRINT") ? 128 : JSON_PRETTY_PRINT;
	file_put_contents($optionsFolder . "/" . "mts_options.json", json_encode($options, $pretty_print));
}
function injectJsToWiki($wikiData) {
	
	global $injectedJsHelpers;
	
	// inject the new saving function before saveMain definition
	$x = strpos($wikiData, "function saveMain(");
	$wikiData = substr($wikiData, 0, $x) . $injectedJsHelpers . substr($wikiData, $x);

	// and the call to it inside saveChanges
	$sc = strpos($wikiData, "function saveChanges(");
	$sc2 = strpos($wikiData, "clearMessage", $sc);
	$wikiData = substr($wikiData, 0, $sc2) . "return saveOnlineChanges();" . substr($wikiData, $sc2);

	// patch the file to allow http saving
	$wikiData = preg_replace('/chkHttpReadOnly: true,/', 'chkHttpReadOnly: false,', $wikiData);
	
	return $wikiData;
}
function removeInjectedJsFromWiki($content) {
	
	//# global $injectedJsHelpers;
	
	$start = strpos($content, "function saveOnlineChanges("); //# first line of $injectedJsHelpers, should be articulated
	$end = strpos($content, "function saveMain("); //# calc using $injectedJsHelpers' length or, better, check match
	$content = substr($content, 0, $start) . substr($content, $end);
	
	$content = preg_replace('/return saveOnlineChanges\(\);/', '', $content);

	$content = preg_replace('/chkHttpReadOnly: false,/', 'chkHttpReadOnly: true,', $content);
	
	return $content;
}
function hasSupportedTwVersion($wikiFileText){
	
	preg_match('/version = {title: "TiddlyWiki", major: (\d+), minor: (\d+), revision: (\d+)/', $wikiFileText, $match);
	$version = intval($match[1]) * 10000 + intval($match[2]) * 100 + intval($match[3]);
	$versionstring = $match[1] . "." . $match[2] . "." . $match[3];
	if ($version < 20605 or $version > 20901)
		return false;
	return true;
}
function isTwLike($file_full_path_and_name) { // doesn't allow PureStore for now
	
	if(!is_file($file_full_path_and_name)) // no such file (may be folder, not file)
		return false;
	if(substr_compare($file_full_path_and_name, ".html", -5, 5) != 0) // not html
		return false;
	$content = file_get_contents($file_full_path_and_name);
	if(!hasSupportedTwVersion($content)) // not TW
		return false;
	return true;
}
function isInWokringFolder($file_name_in_current_workingFolder) { // file or folder
	
	global $workingFolder;
	if(!is_dir($workingFolder)) // workingFolder may be unavailable
		return false;
	$files_and_folders = scandir($workingFolder); // files' and folders' names in current directory
	return in_array($file_name_in_current_workingFolder, $files_and_folders);
}
function isTwInWorkingFolder($file_name_in_current_workingFolder) {
	// check whether it's a tw-like html from the current working folder

	global $workingFolder;
	if(!isInWokringFolder($file_name_in_current_workingFolder))
		return false;
	$full_path = $workingFolder . "/" . $file_name_in_current_workingFolder;
	if(!isTwLike($full_path))
		return false;
	
	return true;
}
function getListOfTwLikeHtmls() {

	global $workingFolder;
	$htmls = [];
	$files_and_folders = scandir($workingFolder); // files' and folders' names in current directory
	foreach ($files_and_folders as $i => $f) {
		$full_path = $workingFolder . "/" . $f;
		if(isTwLike($full_path))
			$htmls[] = $f;
	}
	return $htmls;
};
function showMtsPage($html,$title = '',$httpStatus = 200) {
	
	global $optionsLink, $wikisLink, $version;
	
	http_response_code($httpStatus);
	echo '<!-- ######################### MainTiddlyServer v'.$version.' ############################ -->';
	echo '<!DOCTYPE html><html><head>';
	echo	'<meta charset="UTF-8" />';
	echo	'<meta name="viewport" content="width=device-width, initial-scale=1" />';
	if($title)
		echo "<title>MainTiddlyServer – $title</title>";
	$colorOutside = '#777777';
	$colorBackground = 'white'; $colorForeground = 'black'; $colorLink = ''; $colorLinkVisited = '';
	$colorNavFooterBackground = 'white'; $colorNavFooterLink = 'black';
	/* possible color scheme:
	$colorBackground = $colorNavFooterLink = '#ffffdd';
	$colorForeground = $colorNavFooterBackground = '#0000bb';
	*/
	echo	'<style>
				@import url("https://fonts.googleapis.com/css?family=Roboto:400,700");
				body { font-family: "Roboto", sans-serif; font-size: 15px; }

				input, select, textarea { font-family: inherit; font-size: inherit; padding-left: 0.2em; }
				select { background: inherit; }
				input[type="text"] {  } /* keep disabled in mind */
				
				body {
					margin: 0;
					margin-left: calc(100vw - 100%); /* fixes the scrollbar jumping issue, see https://stackoverflow.com/q/6357870/ */
				}
				.wrapper {
					width: 40em;
					max-width: 100%;
					margin: 0 auto;
					
					min-height: 100vh;
					display: flex;
					flex-direction: column;
				}
				footer { margin-top: auto; } /* https://stackoverflow.com/a/47640893/ */
				
				.navigation {
					text-align: center;
				}
				.navigation__link {
					display: inline-block; padding: 1em 2em;
				}
				
				main {
					padding-left: 1em; padding-right: 1em;
					box-sizing: border-box;
				}
				
				footer {
					text-align: center;
					font-size: 0.8rem;
					padding-top: 1em;
					padding-bottom: 1em;
				}
				
				body { background-color: '.$colorOutside.'; }
				.wrapper {
					background-color: '.$colorBackground.';
					color: '.$colorForeground.';
				}
				/**/
				nav, footer { background-color: '.$colorNavFooterBackground.'; }
				nav a, footer a { color: '.$colorNavFooterLink.'; }
			 </style>';
	echo '</head><body><div class="wrapper">';
	//# set navigation__link_currently-opened class to the currently opened page + get rid of "Available TiddlyWikis:" on the wikis page
	echo '<nav class="navigation">';
	echo   '<a class="navigation__link" href="'. $wikisLink .'">wikis</a>';
	echo   '<a class="navigation__link" href="'. $optionsLink .'">options</a>';
	echo '</nav>';
	echo '<main>'. $html .'</main>';
	echo '<footer><a href="https://yakovl.github.io/MainTiddlyServer/" target="_blank">MainTiddlyServer v'.$version.'</a></footer>';
	echo '</div></body></html>';
}
function showOptionsPage(){
	
	global $options, $optionsLink;
	
	$output = '<style>
		
	</style>
	<script type="text/javascript">
		function check() {
			document.getElementById("un").disabled = !document.getElementById("spw").checked;
			document.getElementById("pw").disabled = !document.getElementById("spw").checked;
		}
	</script>';
	
	$output .= '<form name="input" action="?options" method="post">' .
				 '<input type="hidden" name="options">';
	
	// workingFolder: list $dataFolders' names, send to further save $options['workingFolderName']
	/*$folders = $options['dataFolders'];
	$selected = $options['workingFolderName'];
	$output .= '<p>Use this location: <select name="foldername">';
	foreach ($folders as $name => $path) {
		$output .= "<option value=\"$name\"" . ($name == $selected ? " selected" : "") . ">$name</option>\n";
	}
	$output .= '</select> ()</p>';*/
	//# add description: what is this location, where and how to add new ones
	//# process in $_POST['options']
	//# this should cause updating of the wikis dropdown.. or the latter should be removed from ?options
	
	// wiki
	$files = getListOfTwLikeHtmls();
	$output .= '<p>Use this wiki file: <select name="wikiname">';
	foreach ($files as $f) {
	
		if (preg_match("/[0-9]{6}\.[0-9]{10}/", $f)) // introduced in MicroTiddlyServer to avoid showing backups
			continue;
		$output .= "<option value=\"$f\"" . ($f == $options['wikiname'] ? " selected" : "") . ">$f</option>\n";
	}
	$output .= '</select></p>';
	
	// login/password
	$output .= '<div style="padding: 0 1em;">' .
	     //# align labels and inputs to one line between them
	     '<p><label><input onclick="check()" type="checkbox" name="setpassword" id="spw">Change or set a password</label></p>';
	if (!file_exists('.htaccess'))
		$output .= '<p style="color: red">You currently do not have a password protecting your wiki file. If somebody guesses its path, they could modify it to include malicious javascript that steals your cookies and potentially leads to further hacking on your entire web site. Please set a password below.</p>';
	$output .=   '<p><i>Use only letters (lower- and uppercase) and numbers</i><br>' .
	       '<label>Username: <input type="text" name="un" id="un" disabled="disabled"></label><br>' .
	       '<label>Password: <input type="text" name="pw" id="pw" disabled="disabled"></label></p>' .
	     '</div>';
	
	// memory limit
	$output .= "<p>PHP memory limit: <input type='text' name='memory_limit' value='" . $options['memory_limit'] . 
		"' style='width: 6em;'>" .
		" (increase if your TW is large and saving doesn't work, try values like 6 * size of your TW;" .
		" leave blank to restore default value)</p>";

	$output .= '<p><button type="submit">Save</button></p>';
	$output .= '</form>';

	showMtsPage($output,"Options");
}
function showWikisList(){
	
	global $baselink;
	
	// for screens large enough (in fact, for devices with keyboard),
	// visualize selection and allow navigation via keyboard
	$output = '<style>
			p, ul { margin: 0.5em 0; }
			.wikis-list { text-align: center; }
			.wikis-list__list { display: inline-block; }
			.wikis-list li { padding: 0 0.5em; text-align: left; }
			.keyboard-only { display: none; }
			@media screen and (min-width: 700px) {
				.selected { background-color: #ddddff; }
				:focus { outline: none; }
				.keyboard-only { display: block; }
			}
		</style>' . //# refine the min-device-width value (ps,ph)
	'<div class="wikis-list">'.
	 "<p>Available TiddlyWikis:</p>" .
	 '<ul class="wikis-list__list">';
	 $htmls = getListOfTwLikeHtmls();
	 foreach ($htmls as $i => $f)
		//# add helper to build ?wiki=.. links (escaping +, adding working folder etc), .oO routing
		$output .= '<li><a href="' . $baselink . '?wiki=' . str_replace('+','%2B',$f) . "\">$f</a></li>\n";
	 $output .= '</ul>' .
	'</div>'.
	"<p class='keyboard-only'>You can use keyboard to navigate (&uarr;/&darr;/home/end) between wikis and open them (enter).</p>" .
	'<script>;
	var items = document.getElementsByTagName("li"), selected,
	    select = function(index) {
			if(index < 0 || index >= items.length) return;
			if(selected !== undefined)
				items[selected].classList.remove("selected");
			selected = index;
			items[selected].classList.add("selected");
			items[selected].firstChild.focus(); // scroll into view
		};
	if(items.length)
		select(0);
	
	document.onkeydown = function(e) {
		switch(e.which) {
			case 38: // up
				select(selected-1); return false;
			case 40: // down
				select(selected+1); return false;
			case 36: // home
				select(0); return false;
			case 35: // end
				select(items.length-1); return false;
			case 13: // enter
				// follow the link
				window.location = items[selected].children[0].href;
		}
		//# make it scroll when the first/last item is selected and the key suggests we have to scroll further
	};
</script>';
	showMtsPage($output,"Wikis");
}
// serves TW "properly" but for correct saving requires that either saved options contain the location of the current TW
// or TW is served via ?wiki=wikiname.html request
function showTW($full_path = '') {
	
	// to use vars from outer scope
	global $version, $options, $optionsLink, $workingFolder;
	
	$wikiname = $options['wikiname'];
	$wikiPath = $full_path ? $full_path : ($workingFolder . "/" . $wikiname);
//# if ?wiki=.. is not set, change path to ?wiki=.. (http 30_ redirect?)
//  header('Location: '.$newURL); // $newURL should be absolute; 302 code is ok
//  die(); // for those who bypass the header, see http://thedailywtf.com/Articles/WellIntentioned-Destruction.aspx
	
	// if there's no such file, show that
	if (!file_exists($wikiPath) || !is_file($wikiPath)) {
	
		if (!$wikiname || !$workingFolder) //# check is_dir as well?
			return showOptionsPage();

		showMtsPage("Error: $wikiPath does not exist or is not a file<br>" .
			"Select a wiki file at <a href='$optionsLink'>$optionsLink</a>",'',404);
		return false;
	}
	$wikiData = file_get_contents($wikiPath);
	
	// if the version isn't supported, show that
	if (!hasSupportedTwVersion($wikiData)) {
	
		showMtsPage(
			"The TiddlyWiki file $wikiPath has the version $versionstring which isn't compatible with MainTiddlyServer<br>" .
			'You will have received a compatible wiki file with the name empty.html together with MainTiddlyServer which you could use<br>' .
			'Or you could check for an update<br>' .
			"Select a different wiki file at <a href='$optionsLink'>$optionsLink</a>"
		);
		return false;
	}

	$wikiData = injectJsToWiki($wikiData);
	
	echo '<!-- ######################### MainTiddlyServer v'.$version.' ############################ -->';
	print $wikiData;
	return true;
}
function showDocPage() {
	
}
function updateTW($wikiPath,$changes) { // TW-format-gnostic

	if($changes == new stdClass()) // no changes
		return 'no changes, nothing to save';
	
	// a helper
	function preg_offset($pattern,$text,$skip){
		preg_match($pattern,$text,$match,PREG_OFFSET_CAPTURE);
		if(sizeof($match))
			return $match[0][1] + ($skip ? strlen($match[0][0]) : 0);
		return -1;
	}

	// get wiki content
	$wikiText = file_get_contents($wikiPath);
	if($debug_mode) {
		$memoryUsageBeforeUpdate = memory_get_usage();
		$memoryPeakUsageBeforeUpdate = memory_get_peak_usage();
		file_put_contents('test_incremental_saving__was.txt',$wikiText);
	}
	
	// split html into parts before store, store itself and after store (using DOMDocument fails with TWc, see test_dom.php)
	$re_store_area_div = '/<[dD][iI][vV] id=["\']?storeArea["\']?>\n?/'; //<div id="storeArea">\n
	$posOpeningDiv = preg_offset($re_store_area_div,$wikiText,true); // strpos works faster
	 // this is seemingly different from posOpeningDiv in TW
	$re_store_area_end = '/\n?<\/[dD][iI][vV]>\n<!--POST-STOREAREA-->/'; // \n</div>\n<!--POST-STOREAREA-->
	$posClosingDiv = preg_offset($re_store_area_end,$wikiText,false);
	 // this may be different from posClosingDiv in TW
	$storePart       = substr($wikiText,$posOpeningDiv,$posClosingDiv - $posOpeningDiv);
	//^ first considerable load and peak rise in memory usage
	$beforeStorePart = substr($wikiText,0,$posOpeningDiv);
	$afterStorePart  = substr($wikiText,$posClosingDiv);
	//^ second considerable load and peak rise in memory usage
	unset($wikiText); // no longer needed, spare memory
//# if $beforeStorePart or $afterStorePart is empty, return an error message
	
	// extract tiddlers into $tiddlersMap (divs inside #storeArea, see updateOriginal)
	$re_stored_tiddler = '#<div [^>]+>\s*<pre>[^<]*?</pre>\s*</div>#';
	preg_match_all($re_stored_tiddler,$storePart,$tiddlersArray); //# can we use explode instead?
	unset($storePart); // no longer needed, spare memory
	// turn $tiddlersArray[0] into a map by tiddler title (extract title from title attribute)
	foreach($tiddlersArray[0] as $tiddlerText) {
		// get tiddler title (create DOM element and extract the title attribute)
		$doc = new DOMDocument(); $doc->LoadHTML('<html><body>'.$tiddlerText.'</body></html>');
		$tempElement = $doc->getElementsByTagName('div')->item(0);
		// tests showed: $tiddlerTitle had wrong encoding for cyrillcs until used utf8_decode (see utf-8/ISO-8859-1, http://php.net/manual/en/class.domdocument.php)
		 //# learn why
		$tiddlerTitle = utf8_decode($tempElement->getAttribute('title'));
		// push to the map
		$tiddlersMap[$tiddlerTitle] = $tiddlerText;
	}
	unset($tiddlersArray); // PHP is smart enough not to use additional memory for the new map
	// but when we unset $tiddlersMap to spare memory that only works if we get rid of $tiddlersArray too
	if($debug_mode) {
		file_put_contents('test_store_area_locating.txt','$tiddlersMap length: '.count($tiddlersMap).":\n\n".print_r($tiddlersMap,true));
	}
	
	// apply tiddler changes
	if($debug_mode) {
		file_put_contents('test_changes_parsing.txt',print_r($changes,true));
	}
	foreach($changes->tiddlers as $tiddlerTitle => $tiddlerChange) {
		if($tiddlerChange == "deleted") {
			unset($tiddlersMap[$tiddlerTitle]);
	//# use the -> syntax? (failed to implement that at once as well as ->{}): the []-syntax can cause problems: https://stackoverflow.com/a/25748033/
		} else if($tiddlerChange->added) {
			$tiddlersMap[$tiddlerTitle] = $tiddlerChange->added;
	//# learn the difference between $tiddlerChange->{"added"} (or ->added) and $tiddlerChange["added"]; ($tiddlerChange["added"] is empty when $tiddlerChange->added is not)
		} else if($tiddlerChange->changed) {
			$tiddlersMap[$tiddlerTitle] = $tiddlerChange->changed; // substituting
		} else if($tiddlerChange->renamed) {
			//# can renaming cause conflicts? should we mark it separately from "changed"?
			
			// if implemented, will improve traffic usage and "gittability" (renamed tiddlers won't be shifted to the end)
		}
	}
	if($debug_mode) {
		file_put_contents('test_store_area_locating.txt',print_r($tiddlersMap,true));
	}
	// pack updated tiddlers back into DOM + clear memory from the tiddlersMap
	$updatedStorePart = implode("\n",(array) $tiddlersMap); //works without type change part: (array)
	unset($tiddlersMap); // no longer needed, spare memory

	// update title if necessary
	if($changes->title)
		$beforeStorePart = preg_replace('#<title>.*?</title>#s', '<title> '.$changes->title.' </title>', $beforeStorePart);
	// we use <title> title </title> format (with extra spaces around) since it is used in TW; it doesn't seem to be important
	
	// update markup blocks
 	if($changes->markupBlocks)
		foreach($changes->markupBlocks as $blockName => $blockValue) {
			$start = "<!--$blockName-START-->\n";
			$end   = "\n<!--$blockName-END-->";
			$substitute = $start . $blockValue . $end;
			$blockPattern = "#$start.*?$end#s"; // s: . = any symbol
			if($blockName == "POST-SCRIPT")
				$afterStorePart =  preg_replace($blockPattern,$substitute,$afterStorePart);
			else
				$beforeStorePart = preg_replace($blockPattern,$substitute,$beforeStorePart);
		}

	if($debug_mode) {
		$memoryUsageMiddleOfUpdate = memory_get_usage();
		$memoryPeakUsageMiddleOfUpdate = memory_get_peak_usage();
	}
	// concatenate in an optimized manner (see https://stackoverflow.com/q/47947868/):
	$wikiText = "{$beforeStorePart}{$updatedStorePart}{$afterStorePart}";
// actually, we don't even need to concatenate these: we can use fwrite() and save those one-by one
	if($debug_mode) {
		file_put_contents('test_incremental_saving__became.txt',$wikiText);
		
		$memoryUsageAfterUpdate = memory_get_peak_usage();

		file_put_contents('test_memory_usage.txt',
			  "before update: ".$memoryUsageBeforeUpdate.
			"\npeak before: ".$memoryPeakUsageBeforeUpdate.
			"\nin process: ".$memoryUsageMiddleOfUpdate.
			"\npeak in process: ".$memoryPeakUsageMiddleOfUpdate.
			"\nafter: ".$memoryUsageAfterUpdate);
	}
	
	// save changed wiki
	file_put_contents($wikiPath,$wikiText);
	//# return errors if any
	return 0;
}
function getImageFromBase64AndSave($data,$path,$name)
{
	$imgBase64String = $data;
	$separatorPosition = strpos($imgBase64String,",");
	//# if($separatorPosition === false)
	
	$type = substr($imgBase64String, 0, $separatorPosition);
	preg_match("/data\:image\/(\w+)\;base64/",$type,$matches);
	//# if no match..
	$type = $matches[1];
	
	$imgBase64String = substr($imgBase64String, $separatorPosition+1);
	$imgString = base64_decode($imgBase64String);
	file_put_contents($path . $name . '.' . $type, $imgString);
	// using $type as file extensions is ok for png, jpeg;
	// for SVGs it will be svg+xml, but will there be SVGs pasted as base64?
};
function loadImageByUrlAndSave($url,$path,$name)
{
	$url = filter_var($url,FILTER_SANITIZE_URL);
	$img = file_get_contents($url);
	$type = "png";
	//# check for request errors (see $http_response_header, http://php.net/manual/en/reserved.variables.httpresponseheader.php),
	//# ensure we got an image, get its type automatically
	file_put_contents($path . $name . "." . $type,$img);
};
function getImageByUriAndSave($url,$path,$name)
{
	// check if $url is base64 or not
	preg_match("/^data\:/",$url,$isBase64);
	// make sure $path exists (create the folder if needed)
	if (!file_exists($path))
		mkdir($path, 0777, true);
//	if (!file_exists($path))
//		return ..;
//# if name is not given, create a random one (may be use timestamp)
	if($isBase64)
		getImageFromBase64AndSave($data,$path.'/',$name);
	else
		loadImageByUrlAndSave($url,$path.'/',$name);
//# return path to created image on success
};
//# function moveAttachedImage($old_path, $new_path)

// set folders used by server and load options:
$serverFolder  = ".";
$optionsFolder = $serverFolder;
$options = loadOptions();

// choose $workingFolder among $dataFolders
define(DEFAULT_DATAFOLDER_NAME, "main");
define(DEFAULT_DATAFOLDER_PATH, ".");
// available folders
$dataFolders = $options['dataFolders'];
if(!$dataFolders[DEFAULT_DATAFOLDER_NAME])
	$dataFolders[DEFAULT_DATAFOLDER_NAME] = DEFAULT_DATAFOLDER_PATH; //# disallow overwriting?
// folder choice:
if(!$options['workingFolderName'])
	$options['workingFolderName'] = DEFAULT_DATAFOLDER_NAME;
if(!array_key_exists($options['workingFolderName'],$dataFolders))
	$options['workingFolderName'] = DEFAULT_DATAFOLDER_NAME;
$workingFolder = $dataFolders[$options['workingFolderName']];

if($options['memory_limit'])
	ini_set('memory_limit',$options['memory_limit']);
else
	$options['memory_limit'] = ini_get('memory_limit');

// calc interface links
$port = $_SERVER['SERVER_PORT'];
$portSuffix = $port ? (":".$port) : "";
$baselink    = 'http://' . $_SERVER['SERVER_NAME'] . $portSuffix . $_SERVER['SCRIPT_NAME'];
$optionsLink = $baselink . '?options';
$wikisLink   = $baselink . '?wikis';

// If this is an AJAX request to save the file, do so, for incremental changes echo 'saved' on success and error on fail
if (isset($_POST['save']) || isset($_POST['saveChanges']))
{
	// decide which wiki should be changed
	$wikiPath = $workingFolder . "/";
	if(!$_POST['wiki'])
		$wikiPath .= $options['wikiname'];
	// support saving by ?save=yes&wiki=wikiname.html requests – inside current folder
	else if(isTwInWorkingFolder($_POST['wiki'])) {
		$wikiPath .= $_POST['wiki'];
	} else { // not sure if this will happen at all
		echo 'error: "'.$_POST['wiki'].'" is not a valid TiddlyWiki in the working folder';
		return;
	}

	// first, backup if required
	$backupId = preg_replace("/[^0-9\.]/", '', $_POST['backupid']);
	if($backupId)
		copy($wikiPath, $wikiPath . ".$backupId.html");

	// then save
//# check if wiki exists (it may have been moved or removed)
	if(isset($_POST['save'])) {
		$content = $_POST['content'];
		$content = removeInjectedJsFromWiki($content);
//# check if putting ↑ into 1 line reduces memory usage
		file_put_contents($wikiPath, $content);
	} else { // incremental saving from the saveChanges request
		//# intergate with TrackChangesPlugin, make this work properly (actually change and save), embed into MTS
		$changesJSON = $_POST['saveChanges'];
		$changes = json_decode($changesJSON);
		//# use json_decode($changes); and ... to extract actual changes
		$errors = updateTW($wikiPath,$changes);
		//# respond telling if we have succeeded
		echo $errors ? $errors : 'saved';
	}
}
else if (isset($_POST['options']))
{
	//# use $_POST['foldername']: check if is among dataFolders, set if is
	
	// Make sure the selected wiki file is really in our directory; set it
	if (!isInWokringFolder($_POST['wikiname']))
	//if (strpos(realpath($_POST['wikiname']), getcwd()) === FALSE)
	{
		// security: don't show real path, just the passed "wikiname"
		showMtsPage('<p>' . $_POST['wikiname'] . ' is not in the working directory</p>');
		exit;
	}
	$options['wikiname'] = $_POST['wikiname'];
	
	$newMemoryLimit = $_POST['memory_limit'];
	$options['memory_limit'] = $newMemoryLimit;
	if(!$newMemoryLimit || $newMemoryLimit == ini_get('memory_limit')) //# the second one works only after 2 saves
		unset($options['memory_limit']);
	
	saveOptions($options);
	$output = '<p>Active wiki set to ' . $options['wikiname'] . '</p>';

	if (isset($_POST['setpassword']))
	{
		$userName = preg_replace("/[^\w]/", "", $_POST['un']);
		$passWord = preg_replace("/[^\w]/", "", $_POST['pw']);
		if ($userName != $_POST['un'] || $passWord != $_POST['pw'] || strlen($userName) < 1 || strlen($passWord) < 1) {
		
			$output .= 'The username or password contained illegal characters<br>';
			$output .= 'Use only letters (lower- and uppercase) and numbers<br>';
		}
		else {
			// set .htaccess and .htpasswd (apache 2.2.17 and below)
			//# use apache_get_version() or $_SERVER['SERVER_SOFTWARE'] to tell that the password won't work when it is so
			$htaccess = '<Files ~ "^\.(htaccess|htpasswd)$">
Deny from all
</Files>
<FilesMatch "^(options\.txt|mts_options\.json)$">
Deny from all
</FilesMatch>
ErrorDocument 401 "401 - Wrong Password"
AuthName "Please enter your ID and password"
AuthType Basic
Require valid-user
AuthGroupFile /dev/null
AuthUserFile "' . getcwd() . '/.htpasswd"';
			$htpasswd = $userName . ':' . crypt($passWord, base64_encode($passWord));

			// tell the user the password protection is set
			$output .= "Username \"$userName\" and Password set<br>";
			$output .= 'If you forget your password you can delete the .htaccess file through an FTP client to regain access<br>';
			file_put_contents($serverFolder . "/" . ".htaccess", $htaccess);
			file_put_contents($serverFolder . "/" . ".htpasswd", $htpasswd);
		}
	}

	//# add helper to build ?wiki=.. links (escaping +, adding working folder etc), .oO routing
	$wikiLink = $baselink . '?wiki=' . str_replace('+','%2B',$options['wikiname']);
	$output .= "<p>To start editing your TiddlyWiki now, go to <a href='$wikiLink'>$wikiLink</a></p>";
	showMtsPage($output);
}
else if (isset($_GET['options'])) {
	showOptionsPage();
}
else if (isset($_GET['proxy_to']))
{
//# test, test, test, including urls without protocol (with :?//), with custom port
 // what if we fail to open an html because it's too big? what behaviour we'll get?

	// a helper to split full path into folder + file parts
	function getFolderAndFileNameFromPath($urlFullPath){
		$folderAndFileRegExp = '#^(.*/)([^/]*)$#';
		preg_match($folderAndFileRegExp,$urlFullPath,$match);
		return array(
			'folder' => $match ? $match[1] : '',
			'file'   => $match ? $match[2] : $urlFullPath
		);
	};
	// substitutes \ → /, resolves //, /./ and /__/../ implying an absolute path
	function resolvePath($path) { //# .oO is needed for relative ones? set \ instead of / if needed?

		// substitute \ with / before exploding (those may be mixed)
		$path = str_replace('\\','/',$path); // \ → /

		// get rid of . and .. path segments
		$segments = explode('/',$path);
		if($segments[0] == '..') // invalid path, actually
			return $path;

		$stack = []; // "folders stack" constituting path
		foreach($segments as $segment)
			switch($segment) {
				case '.': break;
				case '..':
					array_pop($stack);
				break;
				default:
					$stack[] = $segment;
			}
		$path = implode('/',$stack);
		
		// substitute multiple / with single /
		$path = preg_replace('#//+#','/',$path);
		
		return $path;
	}

	// parse mts url (request url)
	$mtsHost = $_SERVER['SERVER_NAME']; // $_SERVER['HTTP_HOST'] contains port as well when used on Android, more details at https://stackoverflow.com/a/2297421/
	$mtsPort = $_SERVER['SERVER_PORT'];
	$requestPathAndQuery = parse_url($_SERVER['REQUEST_URI']);
	$mtsPath = $requestPathAndQuery['path'];
	$requestUrl = "$mtsHost$_SERVER[REQUEST_URI]"; // no protocol part, but with full request
	$mtsFolderAndFile = getFolderAndFileNameFromPath($mtsPath);
	$mtsFolderUrl = $mtsFolderAndFile['folder'];
	$mtsFile = $mtsFolderAndFile['file'];
	//# when we introduce different working folders, relative addresses should be relative to workingFolder, not MTS

	// parse requested url
	$requestedUrl = $_GET['proxy_to']; // decoded via urldecode, so basically same as request_url in hijacked httpReq
	// parse_url → array with keys (some may be omitted): scheme, host, port, user, pass, path, query, fragment
	$requestedUrlParts = parse_url($requestedUrl);
	$requestedFolderAndFile = getFolderAndFileNameFromPath($requestedUrlParts['path']);
	$requestedFolder = $requestedFolderAndFile['folder'];
	// resolve ./ bits of path (replace /./ with /)
	$requestedFolder = preg_replace('#/(\./)+#','/',$requestedFolder);
	$requestedFolderResolved = resolvePath($requestedFolder); // localhost/../something won't be resolved
	//# %-decode using rawurldecode (test with myDomain/mtsFolder/relative%20path%20with%20spaces/). Will slashes be preserved at reverse operation?
	//  why not urldecode: see https://stackoverflow.com/q/996139/
	$requestedFile = $requestedFolderAndFile['file'];
	$requestedFileDecodedName = rawurldecode($requestedFile);
	//# when we introduce requests to different working folders, ...

	// check if the requested resource is in the same domain; requested via a relative address; is in the same folder
	/*
					scheme	host	port	user	pass	path+f	query	fragment
	  mts url parts	   -	  +		  +		 /?		 /?		  +  -	  +		   -
	  requestedUrl	   +	  +		 ?!		 /?		 /?		  +  +	  +		  (+)
	  same domain			  =		 ??
	  relative url			  =		  =						 ...
	  same folder			  =		  =						  =
	*/
	$portInRequested = $requestedUrlParts['port'];
	$isSameDomain = ($requestedUrlParts['host'] == $mtsHost) && (!$portInRequested || ($portInRequested == $mtsPort));
	$isSameFolder = $isSameDomain && ($requestedFolder == $mtsFolderUrl); //# test (can trailing / be omitted?)
	$isRelativePath = strpos($requestedFolder,$mtsFolderUrl) === 0;
	$isSubfolder = strpos($requestedFolderResolved,'..') === false && strpos($requestedFolderResolved,$mtsFolderUrl) === 0;
	$isRelativeAddress = $isSameDomain && $isRelativePath;
	
	//# extract headers (getallheaders? http_get_request_headers from PECL? see https://stackoverflow.com/a/541463/)
	//# extract body (http_get_request_body from PECL? see https://stackoverflow.com/q/7187631/)
	//  try also iterating $_SERVER described in the link
	$request_body = file_get_contents('php://input'); //# returns empty for application/x-www-form-urlencoded and multipart/form-data
	$request = print_r($_REQUEST,true);
	// doing this is close to rewriting the proxy.. well, this can be done
	 // we don't have many alternatives since we need to do some ~routing anyway (check working folder)
	
	//# check if address is ~in white-list~, process it if necessary (redirecting to MTS etc)
	$doProxy = !$isSameDomain;
	 // start with "in the same[=working] folder"
	//# get query with the proxy_to= part stripped (explode,~remove,implode)
	if($isSameFolder) {
		
		//# to support cyrillics and may be other symbols in Win (filename; filepath below), see https://gist.github.com/YakovL/a5425e7f4e116aee87fb121a3ab0b26d (fixFilenameEncoding)
		if(isTwInWorkingFolder($requestedFileDecodedName))
			showTW($workingFolder . "/" . $requestedFileDecodedName);
		//# grab and serve resources other than TWs? test pictures in included tiddlers
	} else if($isSubfolder) {
		
		// replace $mtsFolderUrl in $requestedFolderResolved with one slash
		$requestSubPath = substr_replace($requestedFolderResolved,'/',0,strlen($mtsFolderUrl));
		// realpath doesn't seem to be needed: mixed / and \ don't hurt
		//# check if the path can contain %20 (the $requestSubPath bit)
		if(isTwLike($workingFolder . $requestSubPath . $requestedFileDecodedName))
			showTW($workingFolder . $requestSubPath . $requestedFileDecodedName);
		//# grab and serve resources other than TWs?
	} else if($isRelativeAddress) {
		//# remove the previous 2 cases which are included in this one?
	
		// resolve the path
		$relativePath = substr_replace($requestedFolder,'/',0,strlen($mtsFolderUrl));
		$absolutePath = realpath($workingFolder) . $relativePath;
		$absolutePath = resolvePath($absolutePath); // \ → /, remove /../, /./, multiple / → single /

		// check if it's among dataFolders (consider them as white-list of allowed folders) or their subfolders
		$allowed = false;
		foreach($dataFolders as $allowedPath)
			if(strpos($absolutePath,str_replace('\\','/',$allowedPath)) === 0) // was: $absolutePath == str_replace('\\','/',$allowedPath)
			//# does this alter $dataFolders[] ?
				$allowed = true;

		// if it is a TW(-like) file, serve it
		if($allowed && isTwLike($absolutePath . $requestedFileDecodedName))
			showTW($absolutePath . $requestedFileDecodedName);
		
		//# grab and serve resources other than TWs?
	} //# else? (same domain but "extended relative path")
	
	// glue $requestUrl back from parts
	if($debug_mode) {
		$initiallyRequestedPath = $requestedUrlParts['path'];
//# log $initiallyRequestedPath
	}
	$requestedUrlParts['path'] = $requestedFolderAndFile['folder'] . $requestedFolderAndFile['file'];
	// glue requested url parts back
	if($debug_mode) {
		$initialRequestedUrl = $requestedUrl;
	}
	$requestedUrl = $requestedUrlParts['scheme'].'://'. $requestedUrlParts['host'].
		($requestedUrlParts['port'] ? (':'.$requestedUrlParts['port']) : '').
		$requestedUrlParts['path']. ($requestedUrlParts['query'] ? ('?'.$requestedUrlParts['query']) : '');
	 //# what if scheme is not defined? use user, pass if defined; fragment (hash) is probably not needed
	 //# better to use some tested methods (http_build_url from PECL>=0.21.0?) – may be copy implementation
	
	// pass the rest, get response, send back
	if($doProxy) {
		$curl_session = curl_init($requestedUrl);
		// return results by curl_exec to $proxiedRequestResponse instead of printing
		curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
		//# use curl_setopt to set session (login)
		//# ...
		//# learn CURLOPT_FOLLOWLOCATION; use CURLOPT_HEADER when needed
		$proxiedRequestResponse = curl_exec($curl_session);
		if($proxiedRequestResponse === false) {
			$request_error = curl_error($curl_session);
			//# deal with errors, use $request_error
		}
		// may also use curl_getinfo for additional info like times of different ~stages, sizes of different ~parts and others
		curl_close($curl_session);

		// respond back to TW
		//# set headers
		print $proxiedRequestResponse; // response body
	}
	
	// print debug info
	if($debug_mode) {
		$test_message  = '';
		$test_message .= 'request url is ' . $requestUrl . "\n";
		$test_message .= 'MTS host is ' . $mtsHost . "\n";
		$test_message .= 'MTS port is ' . $mtsPort . "\n";
		$test_message .= 'requestPathAndQuery is ' . print_r($requestPathAndQuery,true) . "\n";
		$test_message .= 'MTS path is ' . $mtsPath . "\n";
		$test_message .= 'MTS folder and file are ' . print_r($mtsFolderAndFile,true) . "\n";
		$test_message .= 'mts folder url is ' . $mtsFolderUrl . "\n";
		// $mtsFile
		$test_message .= "request body is\n\n" . $request_body . "\n\n";

		$test_message .= 'undecoded? initially requested url is ' . $initialRequestedUrl . "\n";
		$test_message .= 'initial request query is ' . $initialRequestQuery . "\n";
		$test_message .= 'requestedUrlParts are ' . print_r($requestedUrlParts,true) . "\n";
		$test_message .= 'initially requested file is ' . $initiallyRequestedFile . " (decoded: ".$requestedFileDecodedName.")\n";
		$test_message .= 'requested with resolved . , .. except for _: ' . $requestedFolderResolved . "\n";
		$test_message .= 'requestedFolderAndFile are ' . print_r($requestedFolderAndFile,true) . "\n";
		$test_message .= 'undecoded? processed requested url is ' . $requestedUrl . "\n";

		$test_message .= 'same domain: '. $isSameDomain . "\n";
		$test_message .= 'same folder: '. $isSameFolder . "\n";
		$test_message .= 'subfolder: '  . $isSubfolder  . "\n";
		$test_message .= '$isRelativePath is '. $isRelativePath ."\n";
		$test_message .= '$isRelativeAddress is '. $isRelativeAddress ."\n";
		$test_message .= 'all request params: ' . $request . "\n";

		$test_message .= "\n\n" . 'request error: ' . $request_error . "\n";
		$test_message .= "response:\n\n" . $proxiedRequestResponse . "\n";
		//$test_message .= '$_SERVER: ' . print_r($_SERVER,true);
		file_put_contents('test_proxy.txt',$test_message);		
	}
}
else if (isset($_GET['wikis'])) {

	// show a page with links to all the wikis in the folder
	showWikisList();
}
// open a wiki by url in request
else if (isset($_GET['wiki'])) {

	if(!is_dir($workingFolder)) {
		showMtsPage("<p>The server working folder is currently unavailable...</p>",'',404);
		//# make more helpful (what working folder is used? show at least ~name.. what to do?)
		return;
	}
	if(!isTwInWorkingFolder($_GET['wiki'])) {
		showMtsPage("<p>" . $_GET['wiki'] . " isn't a TiddlyWiki of supported version in the server working folder.</p>"
		   . "<p>Please visit <a href=\"?wikis\">the list of wikis</a> to pick an existing one.</p>",'',404);
		return;
	}
	showTW($workingFolder . "/" . $_GET['wiki']); // already checks if exists, .. but shows full path in case of error which is not nice
} else {
	if($options['wikiname'])
		showWikisList();
	else
		showOptionsPage();
	
//$data = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAIAAAD8GO2jAAABoklEQVRIie1WsW7CQAy9L6FfwE90QGyVGLLB0gGmTlElYOkSZWBhyhQx3wjKAh1IByBSoNF1uSXfAtfBlbmcE5QE2qHCypKX3HvPsa2YhSKOpFBXRyhiCr5/7phSyuW+5dhXykRSuNzXES/g892awY3L/Ua3lSvjBdw4WYRbjo2IF/DH1+fj8cjwMWgYMl7AAaTuKB5JAQg8ne/WSilGz7THg+V+Y6gOZ9PT6UTdGLjl2ICD/bMAsAO1fgC4DJYLOCSB9n8EvIDnUmNUwi3HRvtKKfbxtb9AXSMiKdC+0mtgBGRa/iri+UMBo6/psWa/k0sHeBFPpov0vq4nQHkyk6z3dT0BypOZZL2va38igydTZL1ZgajZ7+D1oJWU4kU8/6lNf13gPgcFPPc5qNBFSSrxV3yTSFK5OmzPAr3JqD0e3OTXlqSyNxlBJRhCkClNovxehNQ5XQRoo9sykii/FyF1I7vLMLQ/nE2hi/Ukyu9FoYif3l6oGwb28T2X+0YSlfYilDlPcpJK6s6oRNV9CWRAg60OW/reQltsakco4uV+8w0KliK3g6WazQAAAABJRU5ErkJggg==';
// keep in mind: http://stackoverflow.com/questions/16566460/png-image-being-cropped-when-saved-from-base64-decode
//$src  = 'https://www.gravatar.com/avatar/481e30b5dc2df70348919dee5e0b8b65?s=32&d=identicon&r=PG&f=1';
//getImageByUriAndSave($src,'test/','test_file');
}
?>