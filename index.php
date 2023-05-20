<?php
// MainTiddlyServer
$version = '1.7.2';
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
	 ! simplify installation
	  - try http://www.clickteam.com/install-creator-2 for simplifying the installation process on Windows (look for alternatives, too: https://alternativeto.net/software/clickteam-install-creator/)
	   * Windows Store?
	  - learn how installation can be simplified for Unix-like OSes (packaging, stores)
	  - learn how installation can be simplified using Composer
	 - add upload TW option, download; new TW option (?wikis)
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
	.oO improve typographics/layout (same as on the site? commons css?), including color scheme (different one?)
	
	- go on implementing working with other folders (see after $workingFolder)
	 - process in $_POST['options'] section the choice of the workingFolder in the options interface
	  - next: make interface consistent (either update the wikis <select> of location choice or remove at all)
	 - retest usage with workingFolder switched to ph: debug current slow including in FF for microrepos
	 - allow including from other dataFolders (next: by w.f.'s aliases /implemented: by relative address)
	 - next: allow choosing workingFolder in interface, visit subfolders (for microrepos), ..

	- allow to switch off proxying (js hijacking)
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
	  . would be useful to have logs regarding what was requested (and which routing was used)
	 - remove conflicting dumps to test_store_area_locating.txt
	 * see https://www.loggly.com/ultimate-guide/php-logging-basics/ and http://www.phptherightway.com/ #errors_and_exceptions and #testing
	 * add logging of errors for the POST requests (and all requests themselves? use for sync editing?)
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
	password-protection to-dos:
	- make password field type="password" and add a duplicate field to check if those values coincide
	. until we stop relying on Apache:
	 - add an option to protect only the .php, options and .ht files with password (see TW for details)
	! try to find an external lib to avoid using .htaccess/apache
	  like may be https://github.com/delight-im/PHP-Auth or https://github.com/PHPAuth/PHPAuth
	 . to make password protection work on Windows, Android, via Apache 2.2.18 and above
	  ? there's no support of htaccess/Apache implementation on Android, right?
	 . crypt is a unix-only solution, non-reliable
	  * support password-protection for Apache 2.2.18 and above, see https://stackoverflow.com/q/41078702/
	    and https://stackoverflow.com/q/11815121/
	  * for implementation, see https://searchengines.guru/archive/index.php/t-234844.html (using htpasswd, 28.05.2008, 05:32) and http://httpd.apache.org/docs/current/misc/password_encryptions.html,
	    or Apache module that uses DB http://httpd.apache.org/docs/2.2/mod/mod_authn_dbd.html
	- implement non-\w containing passwords (or improve ~visibility of the notification) [either non-Apache or non-crypt solution]
	- fix: not all letters of password are used (since crypt uses only the first 8 ones) [either non-Apache or non-crypt solution]
	
	refactoring:
	- add lingo for server, move interface strings there and those in injected js – to TW's lingo
	  also store links to MTS site and repo in a similar central place
	- separate "model" and "controller" fully; then separate "controller" and "view"
	- get rid of ugly ids un and pw (should be different from name s at least)
	
	(forked from MTS v2.8.1.0, see https://groups.google.com/forum/#!topic/tiddlywiki/25LbvckJ3S8)
	changes from the original version:
	1.7.1
	+ introduced color theme and dark mode support (follows OS mode) to both MTS and docs pages
	1.7.0
	+ reduced injected JS to just one chunk, simplified injecting/removing on backend,
	  fixed removeInjectedJsFromWiki for upgrading TW: don't modify the file if injected bits are not found
	+ added support of TWs with CRLF linebreaks (for instance, git changes them so)
	+ made messages about unsupported TW versions more specific and helpful
	+ improved paddings in the list of ?wikis for touch devices
	+ fixed lack of message when non-granulated saving fails to reach server on the stage of loading original
	+ fixed conflicts of simultaneous proxied requests from different working folders
	+ added locking options/TW files when reading/writing to avoid conflicts
	+ refactored options into a singleton class
	+ update latest tested TW version to 2.9.3
	+ fixed store dirtiness when changes to save are empty
	+ introduced tiddlyBackend on front-end, encapsulated several methods into it from global scope,
	  exposed MTS version in it for feature detection
	+ implemented ?backupByName endpoint and decorated copyFile so that during TW upgrading the backup is really saved
	. started using contemporary JS bits (arrow functions, const/let)
	1.6.3
	+ introduce single wiki mode
	+ refactored various bits of code, setting memory_limit should now work consistently
	1.6.2
	+ refactored injected js to fix exotic issues and to support custom saving (encrypted etc),
	  governed by config.options.chkAvoidGranulatedSaving
	+ show error message when saving changes fails due to access failure
	1.6.1
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

$injectedJsHelpers = 'function isGranulatedSavingSupported() { // TW v2.8.0 and above where recreateOriginal is finished and used

	return version.major > 2 || (version.major == 2 && version.minor >= 8);
}

// make option visible through the <<options>> macro (but not among standart options)
if(config.options.chkAvoidGranulatedSaving === undefined) config.options.chkAvoidGranulatedSaving = false;

function shouldGranulatedSavingBeUsed() {

	return isGranulatedSavingSupported() && !config.options.chkAvoidGranulatedSaving;
}

function saveOnlineChanges() {

	if(shouldGranulatedSavingBeUsed())
		saveOnlineGranulatedChanges();
	else
		tiddlyBackend.saveTwSnapshot();
}
function saveOnlineGranulatedChanges() {}

// patch so that FireFox does not corrupt the content
//# to be tested with IE, Edge
convertUnicodeToFileFormat = function(s) { return config.browser.isIE ? convertUnicodeToHtmlEntities(s) : s; };

function getQueryParts() {

	var queryArray = window.location.search.substr(1).split("&"), queryMap = {};
	while(queryArray.length) {
		var nameAndValue = queryArray.pop().split("=");
		queryMap[nameAndValue[0]] = nameAndValue[1];
	}
	return queryMap;
}
function getCurrentTwRequestPart() {

	var queryMap = getQueryParts(),
	    twQueryParts = []; // keep only wiki= and folder=
	for(var key in { wiki:1, folder:1 })
		if(queryMap[key])
			twQueryParts.push(key +"="+ queryMap[key]);

	return twQueryParts.join("&");
	//# or just return the whole window.location.search ?
}
function getOriginalUrl() {
	// use document.location.host so that custom ports are supported
	return document.location.protocol + "//" + document.location.host + document.location.pathname;
};

function setupGranulatedSaving() {

	TiddlyWiki.prototype.rememberStoredState = function(title, markupBlocks, externalizedTiddlers) {
		// perhaps a more correct term would be "stored-tracking"
		
		if(title !== null)
			this.storedTitle = title;
		
		this.storedMarkupBlocks = this.storedMarkupBlocks || {};
		for(var tiddlerName in markupBlocks)
			this.storedMarkupBlocks[tiddlerName] = markupBlocks[tiddlerName];
		
		// {} of texts of tiddlers as they should be saved
		this.storedTiddlers = externalizedTiddlers;
	};
	//# may be remembering whole HTML [= window.originalHTML || recreateOriginal()] and a getter should be added
	//  for encrypted vault support; upgrading support?

	TiddlyWiki.prototype.markupBlocksMap = {

		MarkupPreHead:  "PRE-HEAD",
		MarkupPostHead: "POST-HEAD",
		MarkupPreBody:  "PRE-BODY",
		MarkupPostBody: "POST-SCRIPT"
	};

	TiddlyWiki.prototype.getExternalizedMarkupBlocks = function() {

		var blockValues = {};
		for(var tiddlerName in this.markupBlocksMap) {

			// apadted from replaceChunk
			blockValues[tiddlerName] =
				convertUnicodeToFileFormat(this.getRecursiveTiddlerText(tiddlerName, ""));
		}
		return blockValues;
	};
	TiddlyWiki.prototype.getExternalizedTitle = function() {

		// for now, we only support title updating for the main store
		return this !== store ? null : convertUnicodeToFileFormat(getPageTitle()).htmlEncode()
	};
	TiddlyWiki.prototype.getExternalizedTiddlers = function() {

		var externalizedTiddlers = {}, saver = this.getSaver();
		this.forEachTiddler(function(title, tiddler) {
			if(!tiddler.doNotSave())
				externalizedTiddlers[title] = saver.externalizeTiddler(this,tiddler);
		});
		return externalizedTiddlers;
	};
	
	TiddlyWiki.prototype.refreshStoredData = function() {
	
		this.rememberStoredState(
			// title, remember only for main store
			this.getExternalizedTitle(),
			// markup blocks, tiddlers
			this.getExternalizedMarkupBlocks(), this.getExternalizedTiddlers()
//# use diffs calced by getChanges to refresh .storedTiddlers instead?
		);
	};

	TiddlyWiki.prototype.getChanges = function() {

		var overallChagnes = {};

		// check if some tiddlers were updated
		var changedTiddlers = {};
		// hash by title of "deleted"/{added:externalizedText}/{changed:externalizedText}

		var saver = this.getSaver();
		this.forEachTiddler(function(title, tiddler) {

			if(tiddler.doNotSave()) return;
			var currentExternalizedText = saver.externalizeTiddler(this, tiddler);
			if(!this.storedTiddlers[title]) {
				changedTiddlers[title] = { added:currentExternalizedText };
				return;
			}
			if(currentExternalizedText != this.storedTiddlers[title])
				changedTiddlers[title] = { changed:currentExternalizedText };
		});
		for(var title in this.storedTiddlers)
			if(!this.fetchTiddler(title))
				changedTiddlers[title] = "deleted";

		//# find renamed tiddlers (added + deleted with same text),
		//  put 1 "renamed" instead of 1 "deleted" and 1 "added"?

		for(var key in changedTiddlers) { // if any changes
			overallChagnes.tiddlers = changedTiddlers; break;
		}

		// check if page title was changed
		var currentTitle = this.getExternalizedTitle();
		if(currentTitle != this.storedTitle)
			overallChagnes.title = currentTitle;

		// check if markupBlocks were updated
		var updatedBlocks = this.getExternalizedMarkupBlocks(), blockName;
		for(var tiddlerName in updatedBlocks)
			if(updatedBlocks[tiddlerName] != this.storedMarkupBlocks[tiddlerName]) {
				overallChagnes.markupBlocks = overallChagnes.markupBlocks || {};
				blockName = this.markupBlocksMap[tiddlerName];
				overallChagnes.markupBlocks[blockName] = updatedBlocks[tiddlerName];
			}

		return overallChagnes;
	}

	window.saveOnlineGranulatedChanges = function() {

		var dataToSend = JSON.stringify(store.getChanges());
		if(dataToSend == "{}") {
			store.setDirty(false);
			return;
		}

		tiddlyBackend.call({
			method: "POST",
			onSuccess: function(responseText) {
				if(responseText == "saved")
					tw.io.onSaveMainSuccess();
				else
					displayMessage("Error while saving. Server:\n" + responseText);
			},
			onProblem: function(status) {
				displayMessage("Error while saving, failed to reach the server, status: "+ status);
			},
			body: "saveChanges="+encodeURIComponent(dataToSend) +
				(config.options.chkSaveBackups ? ("&backupid=" + (new Date().convertToYYYYMMDDHHMMSSMMM())) : "")
		});
	};

	// when successfully saved, update .storedTiddlers etc
	TiddlyWiki.prototype.orig_noRefreshingLoaded_setDirty = store.setDirty;
	TiddlyWiki.prototype.setDirty = function(dirty) {
		if(!dirty) this.refreshStoredData();
		return this.orig_noRefreshingLoaded_setDirty.apply(this, arguments);
	};

	// since getPageTitle uses wikifyPlainText which requires formatter which is calced
	//  after all plugins are loaded, we calc it in advance...
	if(!formatter) {
		formatter = new Formatter(config.formatters);
		store.refreshStoredData();
		formatter = null;
	}
	//  ...and remove it afterwards for backward compability
//# this probably should be fixed in the core, though (at least we can hijack getPageTitle)

} //setupGranulatedSaving

function implementRequestProxying() {
	window.config.orig_noProxy_httpReq = httpReq; //# or use window.httpReq?
	httpReq = function(type, url, callback, params, headers, data, contentType, username, password, allowCache) {

		// in case of request to current MTS;
		// we don`t try to guess if urls are the same when the ~index.php bit is omitted/added
		// since we don`t know settings of the index file in the folder;
		// we don`t do this for requests to the same folder/subfolder
		// (that`s the point of the workingFolder fix)
		if(url == getOriginalUrl())
			return window.config.orig_noProxy_httpReq.apply(this, arguments);

		var proxy_url = getOriginalUrl(), // back to MTS
		    request_url = url,
		    currentTwRequestParts = getCurrentTwRequestPart().split("&"),
		    proxy_content = "proxy_to=" + encodeURIComponent(request_url);
		for(var i = 0; i < currentTwRequestParts.length; i++)
			if(currentTwRequestParts[i].indexOf("folder=") == 0)
				proxy_content += "&" + currentTwRequestParts[i];

		// change agruments to make request to MTS` proxy instead:
		// just add request_url to the request body and send to MTS
//# what if its type was not application/x-www-form-urlencoded ?
		url = proxy_url;
		while(arguments.length < 6) // data is the 6th argument and may have been omitted
			[].push.call(arguments, undefined);
		arguments[5] = data ? (proxy_content + "&" + data) : proxy_content;
		return window.config.orig_noProxy_httpReq.apply(this, arguments);
	};
}

window.tiddlyBackend = {
	version: {
		title: "MainTiddlyServer",
		asString: "' . $version . '"
	},
	init: function() {
		if(this.isInitialized) return;
		this.isInitialized = true;

		config.options.chkHttpReadOnly = false;

		// before TW 2.9.4
		if(!window.tw) window.tw = {
			io: {
				onSaveMainSuccess: function() {
					displayMessage(config.messages.mainSaved);
					store.setDirty(false);
				}
			}
		};

		implementRequestProxying();

		if(isGranulatedSavingSupported())
			setupGranulatedSaving();

		// override saving
		window.saveChanges = function(onlyIfDirty, tiddlers) {
			if(onlyIfDirty && !store.isDirty()) return;
			return saveOnlineChanges();
		};

		// decorate copyFile to make it work for backuping on upgrading (sync, returns boolean indicating whether succeeded)
		var nonBackuping_copyFile = window.copyFile;
		window.copyFile = function(destinationPath, sourcePath) {
			// check if was used for creating a backup (see config.macros.upgrade.onClickUpgrade)
			const backupPathReconstructed = getBackupPath(sourcePath, config.macros.upgrade.backupExtension)
			const maskTimestamp = (path) => path.replace(/\d/g, "*")
			if(sourcePath != getLocalPath(document.location.toString())
			   || maskTimestamp(destinationPath) != maskTimestamp(backupPathReconstructed))
				return nonBackuping_copyFile.apply(this, arguments);

			const backslash = "\\\\";
			const slashUsed = destinationPath.indexOf("/") == -1 ? backslash : "/";
			const pathParts = destinationPath.split(slashUsed);
			const fileName = pathParts[pathParts.length - 1];

			let success = false;
			tiddlyBackend.call({
				method: "POST",
				onSuccess: function(responseText) { success = responseText === "success" },
				body: "backupByName=" + encodeURIComponent(fileName) +
					"&backupFolder=" + encodeURIComponent(config.options.txtBackupFolder),
				isSync: true
			})

			return success;
		};
	},

	// auxiliary ("private") methods
	// params: { method?: "GET" | "POST" | ..., headers: { [name:string]: string }, body?: string (data form),
	// onSuccess?: (responseText ??) => void, onProblem?: (status ??)=>void, isSync?: boolean }
	call: function(params) {
		var method = (params.method || "GET").toUpperCase();
		var url = getOriginalUrl();
		var body = params.body || null;
		var headers = params.headers || {};
		var currentPageRequest = getCurrentTwRequestPart();
		if(method === "GET") {
			if(currentPageRequest) url += (url.indexOf("?") == -1 ? "?" : "&") + currentPageRequest;
		} else {
			body = !body ? currentPageRequest :
				body + (currentPageRequest ? "&" + currentPageRequest : "");
			headers["Content-Type"] = "application/x-www-form-urlencoded";
		}

		var xhr = new XMLHttpRequest();
		xhr.onreadystatechange = function() {
			if(this.readyState != 4) return;
			if(this.status == 200) {
				if(params.onSuccess) params.onSuccess(this.responseText);
			} else {
				if(params.onProblem) params.onProblem(this.status, this.responseText);
			}
		}
		xhr.open(method, url, !params.isSync);
		for(var name in headers) xhr.setRequestHeader(name, headers[name]);
		xhr.send(body);
	},
	loadOriginal: function(onSuccess) {
		// GET call with default params loads the TW itself
		this.call({
			onSuccess: onSuccess,
			onProblem: function(status) {
				displayMessage("Error while saving, failed to reach the server and load original, status: "+ status);
			}
		});
	},
	// original = HTML currently stored on backend
	updateAndSendMain: function (original, onSuccess) {
		// Skip any comment at the start of the file..
		const documentStart = original.indexOf("<!DOCTYPE");
		original = original.substring(documentStart);

		// ..get updated html..
		// url to display in the ~saving failed~ message
		const localPath = document.location.toString();
		// alerts on fail, so we don`t notify (again)
		const newHtml = updateOriginal(original, null, localPath);
		if(!newHtml) return;

		// ..and pass the new document to MTS for saving
		tiddlyBackend.call({
			method: "POST",
			onSuccess: function(responseText) {
				if(responseText == "saved") onSuccess();
				else displayMessage("Error while saving. Server:\n" + responseText);
			},
			onProblem: function(status, responseText) {
				displayMessage("Error while saving, failed to reach the server, status: "+ status +"; responseText:");
				// the only way to show it multiline, as for TW 2.9.3
				displayMessage(responseText);
			},
			body: "save=yes&content=" + encodeURIComponent(newHtml) +
				(config.options.chkSaveBackups ? ("&backupid=" + (new Date().convertToYYYYMMDDHHMMSSMMM())) : "")
		});
	},

	// "public" methods
	saveTwSnapshot: function() {
		this.loadOriginal(original => this.updateAndSendMain(original, tw.io.onSaveMainSuccess));
	}
}

// we need store and other stuff to be defined when we setupGranulatedSaving;
// chkHttpReadOnly should be set before calculating readOnly
var noOnlineSaving_invokeParamifier = invokeParamifier;
invokeParamifier = function(params, handler) {

	if(handler == "onload") {
		window.tiddlyBackend.init();
	}

	return noOnlineSaving_invokeParamifier.apply(this, arguments);
};
';

function lock_and_read_file($path) {
	//if(!file_exists($path)) return null; // or use  throw new Exception('.. file does not exist');  ?
	$file = fopen($path, "r");
	if(!$file) return null; // or use  throw new Exception('failed to open .. file');
	$locked = flock($file, LOCK_SH);
	 //* if(!$locked) throw new Exception('failed to lock .. file');
	$content = file_get_contents($path);
	flock($file, LOCK_UN); // needed in PHP after 5.3.2
	fclose($file);
	return $content;
}
function lock_and_write_file($path, $content) {
	return file_put_contents($path, $content, LOCK_EX);
}

define("DEFAULT_DATAFOLDER_NAME", "main");
define("DEFAULT_DATAFOLDER_PATH", ".");
class Options {
	protected static $optionsFolder;
	protected static $options;
	protected static $isChanged;
	public static function init($optionsFolder) {
		if(self::$optionsFolder !== null) return;
		self::$optionsFolder = $optionsFolder;
		self::$isChanged = false;
	}
	public static function load() {
		// first, read old options as a fallback
		$oldPath = self::$optionsFolder . "/" . "options.txt";
		$newPath = self::$optionsFolder . "/" . "mts_options.json";
		$path = file_exists($newPath) ? $newPath : $oldPath;

		$optionsText = lock_and_read_file($path);
		self::$options = $path == $newPath ? json_decode($optionsText, true) : unserialize($optionsText);

		// normalize
		if(!self::$options['dataFolders'])
			self::$options['dataFolders'] = [];
		if(!self::$options['dataFolders'][DEFAULT_DATAFOLDER_NAME])
			self::$options['dataFolders'][DEFAULT_DATAFOLDER_NAME] = DEFAULT_DATAFOLDER_PATH;
	}
	public static function get($optionName) {
		//# if($optionName == 'dataFolders') ... return copy of options['dataFolders'] so that they cannot be changed
		return self::$options[$optionName];
	}
	public static function set($optionName, $value, $unsetEmpty = false) {
		if($optionName == 'dataFolders') return;
		if($value != self::$options[$optionName])
			self::$isChanged = true;
		if(!$value && $unsetEmpty)
			unset(self::$options[$optionName]); // https://stackoverflow.com/a/25748033/3995261
		else
			self::$options[$optionName] = $value;
	}
	public static function chooseWorkingFolder($name) {
		if(self::$options['dataFolders'][$name]) {
			self::set('workingFolderName', $name);
		}
		$workingFolderName = self::get('workingFolderName');
		if(!$workingFolderName or !array_key_exists($workingFolderName, self::$options['dataFolders']))
			self::set('workingFolderName', DEFAULT_DATAFOLDER_NAME);
		return self::get('workingFolderName');
	}
	public static function getWorkingFolder() {
		return self::$options['dataFolders'][self::get('workingFolderName')];
	}
	public static function save() {
		if(!self::$isChanged) return;
		// a fallback for PHP below 5.4.0 (see http://stackoverflow.com/questions/22208831/json-encode-expects-parameter-2-to-be-long-string-given)
		$pretty_print = (JSON_PRETTY_PRINT == "JSON_PRETTY_PRINT") ? 128 : JSON_PRETTY_PRINT;
		$path = self::$optionsFolder . "/" . "mts_options.json";
		return lock_and_write_file($path, json_encode(self::$options, $pretty_print));
	}
}

function injectJsToWiki($wikiData) {

	global $injectedJsHelpers;

	// inject the new saving function before saveMain definition (make sure it's not inside storeArea)
	$endOfStoreArea = strpos($wikiData, "<!--POST-STOREAREA-->");
	$x = strpos($wikiData, "function saveMain(", $endOfStoreArea);
	$wikiData = substr($wikiData, 0, $x) . $injectedJsHelpers . substr($wikiData, $x);

	return $wikiData;
}
function removeInjectedJsFromWiki($content) {

	global $injectedJsHelpers;

	$endOfStoreArea = strpos($wikiData, "<!--POST-STOREAREA-->");
	// we imply that $injectedJsHelpers are either unchanged inside TW html or not present at all (may be so on upgrading)
	//# try to use  substr_replace  instead (compare times, memory usage)
	$start = strpos($content, $injectedJsHelpers, $endOfStoreArea);
	if($start === false) {
		return $content;
	}
	$end = $start + strlen($injectedJsHelpers);
	return substr($content, 0, $start) . substr($content, $end);
}
function getTwVersion($wikiFileText) {

	preg_match('/version = {\s*title: "TiddlyWiki", major: (\d+), minor: (\d+), revision: (\d+)/', $wikiFileText, $match);
	return $match;
}
define("EARLIEST_TESTED_VERSION", 20600);
define("LATEST_TESTED_VERSION", 20904);
function isSupportedTwVersion($versionParts) {

	if(!$versionParts)
		return false;
	$version = intval($versionParts[1]) * 10000 + intval($versionParts[2]) * 100 + intval($versionParts[3]);
	if ($version < EARLIEST_TESTED_VERSION or $version > LATEST_TESTED_VERSION)
		return false;
	return true;
}
function isNewerUntestedTwVersion($versionParts) {

	if(!$versionParts)
		return false;
	$version = intval($versionParts[1]) * 10000 + intval($versionParts[2]) * 100 + intval($versionParts[3]);
	if ($version > LATEST_TESTED_VERSION)
		return true;
	return false;
}
function hasSupportedTwVersion($wikiFileText) {

	$versionParts = getTwVersion($wikiFileText);
	return isSupportedTwVersion($versionParts);
}
function hasHtmlExtension($nameOrPath) {
	return substr_compare($nameOrPath, ".html", -5, 5) == 0;
}
function isTwLike($file_full_path_and_name) { // doesn't allow PureStore for now
	
	if(!hasHtmlExtension($file_full_path_and_name))
		return false;
	if(!is_file($file_full_path_and_name))
		return false;
	$content = lock_and_read_file($file_full_path_and_name);
	if(!hasSupportedTwVersion($content)) // not TW
		return false;
	return true;
}
function isInWokringFolder($file_name_in_current_workingFolder) { // file or folder
	
	$workingFolder = Options::getWorkingFolder();
	if(!is_dir($workingFolder)) // workingFolder may be unavailable
		return false;
	$filesAndFolders = scandir($workingFolder); // files' and folders' names in current directory
	return in_array($file_name_in_current_workingFolder, $filesAndFolders);
}
// checks whether it's a tw-like html from the current working folder
function isTwInWorkingFolder($file_name_in_current_workingFolder) {

	if(!isInWokringFolder($file_name_in_current_workingFolder))
		return false;
	$fullPath = Options::getWorkingFolder() . "/" . $file_name_in_current_workingFolder;
	if(!isTwLike($fullPath))
		return false;
	return true;
}
function getListOfTwLikeHtmls($folder) {

	$twLikeHtmls = [];
	if(!is_dir($folder)) return null;
	$filesAndFolders = scandir($folder);
	foreach ($filesAndFolders as $name) {
		$fullPath = $folder . "/" . $name;
		if(is_file($fullPath) && hasHtmlExtension($fullPath) && isTwLike($fullPath))
			$twLikeHtmls[] = $name;
	}
	return $twLikeHtmls;
};
function showMtsPage($html, $title = '', $httpStatus = 200) {

	global $optionsLink, $baselink, $wikisLink, $version;
	
	http_response_code($httpStatus);
	echo '<!-- ######################### MainTiddlyServer v'.$version.' ############################ -->';
	echo '<!DOCTYPE html><html><head>';
	echo	'<meta charset="UTF-8" />';
	echo	'<meta name="viewport" content="width=device-width, initial-scale=1" />';
	if($title)
		echo "<title>MainTiddlyServer – $title</title>";
	echo	'<style>
				@import url("https://fonts.googleapis.com/css?family=Roboto:400,700");
				body { font-family: "Roboto", sans-serif; font-size: 15px; }

				:root {
					--color-outside: #888577;
					--color-background: rgb(246, 234, 196);
					--color-foreground: black;
					/*--color-link: ;
					--color-link-visited: ;*/
					--color-nav-and-footer-background: black;
					--color-nav-and-footer-link: rgb(246, 234, 196);
					--color-selection: #b7b69f;
				}
				main a {
					color: inherit;
    				opacity: 0.6;
				}
				::selection {
					background: var(--color-selection);
				}
				@media (prefers-color-scheme: dark) {
					:root {
						--color-outside: #373630;
						--color-background: black;
						--color-foreground: rgb(150, 143, 120);
						--color-nav-and-footer-background: black;
						--color-nav-and-footer-link: rgb(150, 143, 120);
						--color-selection: rgba(150, 143, 120, 0.5);
					}

					html {
						color-scheme: dark;
					}
				}

				input, textarea, select {
					background: inherit;
					color: inherit;
					border: thin solid black;
				}
				option { background: var(--color-background); }
				/* the hover and selected ones are more complecated, see https://stackoverflow.com/q/10484053/3995261 and https://stackoverflow.com/q/8619406/3995261 */
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
				
				body { background-color: var(--color-outside); }
				.wrapper {
					background-color: var(--color-background);
					color: var(--color-foreground);
				}

				nav, footer { background-color: var(--color-nav-and-footer-background); }
				nav a, footer a { color: var(--color-nav-and-footer-link); }
			 </style>';
	echo '</head><body><div class="wrapper">';
	//# set navigation__link_currently-opened class to the currently opened page + get rid of "Available TiddlyWikis:" on the wikis page
	echo '<nav class="navigation">';
	echo   '<a class="navigation__link" href="'.(Options::get('single_wiki_mode') ? $baselink : $wikisLink).'">'.
			(Options::get('single_wiki_mode') ? 'wiki' : 'wikis').'</a>';
	echo   '<a class="navigation__link" href="'. $optionsLink .'">options</a>';
	echo '</nav>';
	echo '<main>'. $html .'</main>';
	echo '<footer><a href="https://yakovl.github.io/MainTiddlyServer/" target="_blank">MainTiddlyServer v'.$version.'</a></footer>';
	echo '</div></body></html>';
}
function showOptionsPage() {
	
	global $optionsLink;
	
	$output = '<style>
		.options-form__password-panel { padding: 0 1em; }
		.no-password-warning { color: red; }
		.memory-limit-input { width: 6em; }
		button {
			cursor: pointer;
			padding: 0.3em 0.6em;
		}
	</style>
	<script type="text/javascript">
		function togglePasswordSetting(isEnabled) {
			const passInputsArea = document.getElementsByClassName("options-form__password-inputs")[0];
			passInputsArea.style.display = isEnabled ? "" : "none";
		}
	</script>';
	
	$output .= '<form class="options-form" name="input" action="' . $optionsLink . '" method="post">' .
				 '<input type="hidden" name="options">';
	
	// workingFolder: list Options::get('dataFolders')'s names, send to further save Options 'workingFolderName'
	/*$folders = Options::get('dataFolders');
	$selected = Options::get('workingFolderName');
	$output .= '<p>Use this location: <select name="foldername">';
	foreach ($folders as $name => $path) {
		$output .= "<option value=\"$name\"" . ($name == $selected ? " selected" : "") . ">$name</option>\n";
	}
	$output .= '</select> ()</p>';*/
	//# add description: what is this location, where and how to add new ones
	//# process in $_POST['options']
	//# this should cause updating of the wikis dropdown.. or the latter should be removed from ?options
	
	// wiki
	$files = getListOfTwLikeHtmls(Options::getWorkingFolder());
	if(is_null($files)) {
		$output .= '<p><i>The chosen working folder is currently unavailable</i></p>';
	} else {
		$output .= '<p>Use this wiki file: <select name="wikiname">';
		foreach ($files as $fileName) {
		
			// avoid showing backups (legacy of MicroTiddlyServer)
			if (preg_match("/[0-9]{6}\.[0-9]{10}/", $fileName))
				continue;
			$output .= "<option value=\"$fileName\"" . ($fileName == Options::get('wikiname') ? " selected" : "") . ">$fileName</option>\n";
		}
		$output .= '</select></p>';	
	}
	$output .= '<p><label><input type="checkbox" '.(Options::get('single_wiki_mode') ? 'checked=checked' : '').
				'name="single_wiki_mode">Single wiki mode (redirect from wikis to wiki page, no ?wiki=.. in URL required)</label></p>';
	
	// login/password
	$output .=
	'<div class="options-form__password-panel">' .
	  '<p><label><input type="checkbox" name="setpassword" onclick="togglePasswordSetting(this.checked)">Change or set a password</label></p>';
	// gives false negatives (.htaccess may be without pass)
	$noPassSet = !file_exists('.htaccess');
	if($noPassSet) {
		$output .= '<p class="no-password-warning">You currently do not have a password protecting your wiki file.' .
			' If somebody guesses its path, they could modify it to include malicious javascript that steals your cookies ' .
			'and potentially leads to further hacking on your entire web site. Please set a password below.</p>';
	}
	$output .=
	  '<div class="options-form__password-inputs" style="display: none;">' .
	    '<p><i>Use only letters (lower- and uppercase) and numbers</i></p>' .
	    '<table><tbody>' .
	      '<tr><td><label for="un">Username:</label></td> <td><input type="text" name="un" id="un"></td></tr>' .
	      '<tr><td><label for="pw">Password:</label></td> <td><input type="text" name="pw" id="pw"></td></tr>' .
	    '</table></tbody>' .
	  '</div>'.
	'</div>';
	
	// memory limit
	$output .= "<p>PHP memory limit: <input type='text' name='memory_limit' value='" . Options::get('memory_limit') .
		"' class='memory-limit-input'>" .
		" (increase if your TW is large and saving doesn't work, try values like 6 *  the size of your TW;" .
		" leave blank to restore the default value)</p>";

	$output .= '<p><button type="submit">Save</button></p>';
	$output .= '</form>';

	showMtsPage($output, "Options");
}
function showWikisList() {

	// for screens large enough (in fact, for devices with keyboard),
	// visualize selection and allow navigation via keyboard
	$output = '<style>
			p, ul { margin: 0.5em 0; }
			.wikis-list { text-align: center; }
			.wikis-list__title { margin: 1.4em 0 0; }
			.wikis-list__list { text-align: left; display: inline-block; padding-left: 0; }
			.wikis-list__item { padding: 0.3em 0.5em; list-style: none; border-radius: 5px; }

			.hint { opacity: 0.4; text-align: center; }
			.keyboard-only { display: none; }
			/* rough detection of non-touch device */
			@media screen and (min-width: 700px) {
				.selected { background-color: var(--color-selection); }
				:focus { outline: none; }
				.keyboard-only { display: block; }
			}
			@media screen and (max-width: 700px) {
				.wikis-list__item { padding-top: 0.25em; padding-bottom: 0.25em; }
			}
		</style>' . //# refine the min-device-width value (ps,ph)
	'<div class="wikis-list">';

	$htmls = getListOfTwLikeHtmls(Options::getWorkingFolder());
	if(is_null($htmls)) {
		$output .= '<p class="wikis-list__title">The chosen working folder is not available</p>' . '</div>';
		showMtsPage($output, "Wikis: working folder unavailable");
		return;
	}

	$output .=
	 '<p class="wikis-list__title">Available TiddlyWikis:</p>' .
	 '<ul class="wikis-list__list">';
	 foreach ($htmls as $name)
		$output .= '<li class="wikis-list__item"><a href="' . getFullWikiLink($name) . "\">$name</a></li>\n";
	 $output .= '</ul>' .
	'</div>'.
	"<p class='keyboard-only hint'>You can use keyboard to select a wiki (&uarr;/&darr;/home/end) and to open it (enter).</p>" .
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
				select(selected - 1); return false;
			case 40: // down
				select(selected + 1); return false;
			case 36: // home
				select(0); return false;
			case 35: // end
				select(items.length - 1); return false;
			case 13: // enter
				// follow the link
				window.location = items[selected].children[0].href;
		}
		//# make it scroll when the first/last item is selected and the key suggests we have to scroll further
	};
</script>';
	showMtsPage($output, "Wikis");
}
// serves TW "properly" but for correct saving requires that
// either saved options contain the location of the current TW
// or TW is served via ?wiki=wikiname.html request
function showTW($fullPath = '', $pathToShowOnError = '') {

	global $version, $optionsLink;
	
	$workingFolder = Options::getWorkingFolder();
	$wikiName = Options::get('wikiname');
	$wikiPath = $fullPath ? $fullPath : ($workingFolder . "/" . $wikiName);
	if(!$pathToShowOnError) $pathToShowOnError = $wikiName;
//# if ?wiki=.. is not set and it is not single_wiki_mode, change path to ?wiki=.. (http 30_ redirect?)
//  header('Location: '.$newURL); // $newURL should be absolute; 302 code is ok
//  die(); // for those who bypass the header, see http://thedailywtf.com/Articles/WellIntentioned-Destruction.aspx
	
	// if there's no such file, show that
	if (!file_exists($wikiPath) || !is_file($wikiPath)) {
	
		if (!$wikiName || !$workingFolder) //# check is_dir as well?
			return showOptionsPage();

		showMtsPage("<p>\"$pathToShowOnError\" does not exist or is not a file in the working folder.</p>" .
			"<p>Select a wiki file on the <a href='$optionsLink'>options page</a></p>", '', 404);
		return false;
	}
	$wikiData = lock_and_read_file($wikiPath);
	
	// if the version isn't supported, show that
	$versionParts = getTwVersion($wikiData);
	if (!isSupportedTwVersion($versionParts)) {

		$versionString = $versionParts ? ('the version "'. $versionParts[1] .".". $versionParts[2] .".". $versionParts[3] .'"')
			: 'an unknown version';
		$backupWarning = ", but be sure to backup your TW before using the patched MTS.";
		$selectDifferentSuggestion = "<p>You may also select a different TW file on the <a href='$optionsLink'>options page</a>.</p>";
		if(isNewerUntestedTwVersion($versionParts))
			showMtsPage(
				"<p>The TiddlyWiki file \"$pathToShowOnError\" has $versionString which isn't tested for " .
				"compatibility with MainTiddlyServer yet. Please try the latest version of MTS.</p>" .
				//# add link to the download page (/auto update MTS?)
				//# check newer version of MTS automatically
				"<p>If it's the latest MTS already, please create an issue " .
				"<a href='https://github.com/YakovL/MainTiddlyServer/issues'>in the MTS repo</a>. " .
				"If you really need to make this work quickly, you can open the MTS source and change the " .
				"LATEST_TESTED_VERSION constant$backupWarning</p>" .
				//# allow to do this via interface, perhaps right on this page, too
				$selectDifferentSuggestion, '', 403
			);
		else
			showMtsPage(
				"<p>The TiddlyWiki file \"$pathToShowOnError\" has $versionString which is probably incompatible with MainTiddlyServer.</p>" .
				($versionParts ? "<p>You may want to try to upgrade your TW. If you really need to work with " .
				 "the old version, you may open the MTS source and change the EARLIEST_TESTED_VERSION constant" .
				 //# allow to do this via interface, perhaps right on this page, too
				 "$backupWarning It is unknown whether this will work properly.</p>" :
					"<p>If that's an old version, please try to upgrade it.</p>") .
					//# add link to docs + to the issues/community forum
				$selectDifferentSuggestion, '', 403
			);
		return false;
	}

	$wikiData = injectJsToWiki($wikiData);
	
	echo '<!-- ######################### MainTiddlyServer v'.$version.' ############################ -->';
	print $wikiData;
	return true;
}
function showWikisOrWiki() {

	if(Options::get('single_wiki_mode'))
		showTW();
	else
		showWikisList();
}
function showDocPage() {
	
}
// reads TW, applies changes, saves back; returns 0 on success and error text otherwise (not quite: see //#)
function updateTW($wikiPath, $changes) { // TW-format-gnostic

	if($changes == new stdClass()) // no changes
		return 'no changes, nothing to save';

	// a helper
	function preg_offset($pattern, $text, $skip) {
		preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE);
		if(sizeof($match))
			return $match[0][1] + ($skip ? strlen($match[0][0]) : 0);
		return -1;
	}

	// get wiki content
	$wikiText = lock_and_read_file($wikiPath);
	if($debug_mode) {
		$memoryUsageBeforeUpdate = memory_get_usage();
		$memoryPeakUsageBeforeUpdate = memory_get_peak_usage();
		file_put_contents('test_incremental_saving__was.txt', $wikiText);
	}

	$LINEBREAK = '(?:\r?\n)';
	// split html into parts before store, store itself and after store (using DOMDocument fails with TWc, see test_dom.php)
	$re_store_area_div = '/<[dD][iI][vV] id=["\']?storeArea["\']?>'.$LINEBREAK.'?/'; //<div id="storeArea">\n
	$posOpeningDiv = preg_offset($re_store_area_div, $wikiText, true); // strpos works faster
	 // this is seemingly different from posOpeningDiv in TW
	$re_store_area_end = '/'.$LINEBREAK.'?<\/[dD][iI][vV]>'.$LINEBREAK.'<!--POST-STOREAREA-->/'; // \n</div>\n<!--POST-STOREAREA-->
	$posClosingDiv = preg_offset($re_store_area_end, $wikiText, false);
	 // this may be different from posClosingDiv in TW
	$storePart       = substr($wikiText, $posOpeningDiv, $posClosingDiv - $posOpeningDiv);
	//^ first considerable load and peak rise in memory usage
	$beforeStorePart = substr($wikiText, 0, $posOpeningDiv);
	$afterStorePart  = substr($wikiText, $posClosingDiv);
	//^ second considerable load and peak rise in memory usage
	unset($wikiText); // no longer needed, spare memory
//# return error msg if $beforeStorePart or $afterStorePart is empty (~wrong format/not a TW, .. not found)

	// extract tiddlers into $tiddlersMap (divs inside #storeArea, see updateOriginal)
	$re_stored_tiddler = '#<div [^>]+>\s*<pre>[^<]*?</pre>\s*</div>#';
	preg_match_all($re_stored_tiddler, $storePart, $tiddlersArray); //# can we use explode instead?
	unset($storePart); // no longer needed, spare memory
	// turn $tiddlersArray[0] into a map by tiddler title (extract title from title attribute)
	foreach($tiddlersArray[0] as $tiddlerText) {
		// get tiddler title (create DOM element and extract the title attribute)
//# return error msg if DOMDocument is not available (php-xml module required), try extension_loaded('xml')
		$doc = new DOMDocument(); $doc->LoadHTML('<html><body>'.$tiddlerText.'</body></html>');
		$tempElement = $doc->getElementsByTagName('div')->item(0);
		// fix encoding (see https://stackoverflow.com/q/8218230/ , utf-8/ISO-8859-1, http://php.net/manual/en/class.domdocument.php)
		$tiddlerTitle = utf8_decode($tempElement->getAttribute('title'));
		// push to the map
		$tiddlersMap[$tiddlerTitle] = $tiddlerText;
	}
	unset($tiddlersArray); // PHP is smart enough not to use additional memory for the new map
	// but when we unset $tiddlersMap to spare memory that only works if we get rid of $tiddlersArray too
	if($debug_mode) {
		file_put_contents('test_store_area_locating.txt', '$tiddlersMap length: '.count($tiddlersMap).":\n\n".print_r($tiddlersMap, true));
	}

	// apply tiddler changes
	if($debug_mode) {
		file_put_contents('test_changes_parsing.txt', print_r($changes, true));
	}
	foreach($changes->tiddlers as $tiddlerTitle => $tiddlerChange) {
		if($tiddlerChange == "deleted") {
			unset($tiddlersMap[$tiddlerTitle]);
		} else if($tiddlerChange->added) {
			$tiddlersMap[$tiddlerTitle] = $tiddlerChange->added;
		} else if($tiddlerChange->changed) {
			$tiddlersMap[$tiddlerTitle] = $tiddlerChange->changed; // substituting
		} else if($tiddlerChange->renamed) {
			//# can renaming cause conflicts? should we mark it separately from "changed"?
			
			// if implemented, will improve traffic usage and "gittability" (renamed tiddlers won't be shifted to the end)
		}
	}
	if($debug_mode) {
		file_put_contents('test_store_area_locating.txt', print_r($tiddlersMap, true));
	}
	// pack updated tiddlers back into DOM + clear memory from the tiddlersMap
	$updatedStorePart = implode("\n", (array)$tiddlersMap); //works without type change (array)
	unset($tiddlersMap); // no longer needed, spare memory

	// update title if necessary
	if($changes->title)
		$beforeStorePart = preg_replace('#<title>.*?</title>#s', '<title> '.$changes->title.' </title>', $beforeStorePart);
	// we use <title> title </title> format (with extra spaces around) since it is used in TW; it doesn't seem to be important
	
	// update markup blocks
 	if($changes->markupBlocks)
		foreach($changes->markupBlocks as $blockName => $blockValue) {
			$start = "<!--$blockName-START-->";
			$end   = "<!--$blockName-END-->";
			$substitute = $start ."\n". $blockValue ."\n". $end;
			$blockPattern = "#$start$LINEBREAK.*?$LINEBREAK$end#s"; // "s" flag: . = any symbol
			if($blockName == "POST-SCRIPT")
				$afterStorePart =  preg_replace($blockPattern, $substitute, $afterStorePart);
			else
				$beforeStorePart = preg_replace($blockPattern, $substitute, $beforeStorePart);
		}

	if($debug_mode) {
		$memoryUsageMiddleOfUpdate = memory_get_usage();
		$memoryPeakUsageMiddleOfUpdate = memory_get_peak_usage();
	}
	// concatenate in an optimized manner (see https://stackoverflow.com/q/47947868/):
	$wikiText = "{$beforeStorePart}{$updatedStorePart}{$afterStorePart}";
// actually, we don't even need to concatenate these: we can use fwrite() and save those one-by one
	if($debug_mode) {
		file_put_contents('test_incremental_saving__became.txt', $wikiText);

		$memoryUsageAfterUpdate = memory_get_peak_usage();

		file_put_contents('test_memory_usage.txt',
			  "before update: ".$memoryUsageBeforeUpdate.
			"\npeak before: ".$memoryPeakUsageBeforeUpdate.
			"\nin process: ".$memoryUsageMiddleOfUpdate.
			"\npeak in process: ".$memoryPeakUsageMiddleOfUpdate.
			"\nafter: ".$memoryUsageAfterUpdate);
	}

	// save changed wiki
	$saved = lock_and_write_file($wikiPath, $wikiText);
	if(!$saved)
		return  "MainTiddlyServer failed to save updated TiddlyWiki.\n".
			"Please make sure the containing folder is accessible for writing and the TiddlyWiki can be (over)written.\n".
			"Usually this requires that those have owner/group of \"www-data\" and access mode is 7** (e.g. 744) for folder and 6** for TW.";
	return 0;
}
function getImageFromBase64AndSave($data, $path, $name)
{
	$imgBase64String = $data;
	$separatorPosition = strpos($imgBase64String, ",");
	//# if($separatorPosition === false)
	
	$type = substr($imgBase64String, 0, $separatorPosition);
	preg_match("/data\:image\/(\w+)\;base64/", $type, $matches);
	//# if no match..
	$type = $matches[1];
	
	$imgBase64String = substr($imgBase64String, $separatorPosition + 1);
	$imgString = base64_decode($imgBase64String);
	$saved = file_put_contents($path . $name . '.' . $type, $imgString);
	// using $type as file extensions is ok for png, jpeg;
	// for SVGs it will be svg+xml, but will there be SVGs pasted as base64?
};
function loadImageByUrlAndSave($url, $path, $name)
{
	$url = filter_var($url, FILTER_SANITIZE_URL);
	$img = file_get_contents($url);
	$type = "png";
	//# check for request errors (see $http_response_header, http://php.net/manual/en/reserved.variables.httpresponseheader.php),
	//# ensure we got an image, get its type automatically
	$saved = file_put_contents($path . $name . "." . $type, $img);
};
function getImageByUriAndSave($url, $path, $name)
{
	// check if $url is base64 or not
	preg_match("/^data\:/", $url, $isBase64);
	// make sure $path exists (create the folder if needed)
	if (!file_exists($path))
		mkdir($path, 0777, true);
//	if (!file_exists($path))
//		return ..;
//# if name is not given, create a random one (may be use timestamp)
	if($isBase64)
		getImageFromBase64AndSave($data, $path.'/', $name);
	else
		loadImageByUrlAndSave($url, $path.'/', $name);
//# return path to created image on success
};
//# function moveAttachedImage($old_path, $new_path)

// set folders used by server and load options:
$serverFolder  = ".";
$optionsFolder = $serverFolder;
Options::init($optionsFolder);
Options::load();

// choose workingFolder among dataFolders (on any request):
$requestedFolderName = !empty($_REQUEST['folder']) ? $_REQUEST['folder'] : '';
if(Options::chooseWorkingFolder($requestedFolderName) != $requestedFolder)
	; //# notify user somehow!
$workingFolder = Options::getWorkingFolder();

$system_memory_limit = ini_get('memory_limit');
$memory_limit = Options::get('memory_limit');
if($memory_limit)
	ini_set('memory_limit', $memory_limit);

// calc interface links
$port = $_SERVER['SERVER_PORT'];
$portSuffix = $port ? (":".$port) : "";
$baselink    = 'http://' . $_SERVER['SERVER_NAME'] . $portSuffix . $_SERVER['SCRIPT_NAME'];
$optionsLink = $baselink . '?options';
$wikisLink   = $baselink . '?wikis';
function getFullWikiLink($nameOrPath) {
	global $baselink;
	//# deal with '#' in filename (substitute with %23)
	if(Options::get('single_wiki_mode'))
		return $baselink;
	$link = $baselink . '?';
	if(Options::get('workingFolderName') && count((array)Options::get('dataFolders')) > 1)
		$link .= 'folder=' . Options::get('workingFolderName') . '&';
	return $link . 'wiki=' . str_replace('+', '%2B', $nameOrPath);
}

// If this is an AJAX request to save the file, do so, for incremental changes respond 'saved' on success and error on fail
if (isset($_POST['save']) || isset($_POST['saveChanges']))
{
	$nameOfTwToUpdate = $_POST['wiki'] ? $_POST['wiki'] : Options::get('wikiname');
	if(!isTwInWorkingFolder($nameOfTwToUpdate)) {
		http_response_code(404);
		echo "error: \"$nameOfTwToUpdate\" is not a valid TiddlyWiki in the working folder";
		return;
	}
	$wikiPath = $workingFolder . "/" . $nameOfTwToUpdate;

	// first, backup if required
	$backupId = preg_replace("/[^0-9\.]/", '', $_POST['backupid']);
	if($backupId) copy($wikiPath, "$wikiPath.$backupId.html");

	// then save
	if(isset($_POST['save'])) {
		$content = removeInjectedJsFromWiki($_POST['content']);
//# .oO can removeInjectedJsFromWiki fail?
		$saved = lock_and_write_file($wikiPath, $content);
		echo $saved ? 'saved' :
			"MainTiddlyServer failed to save updated TiddlyWiki.\n".
			"Please make sure the containing folder is accessible for writing and the TiddlyWiki can be (over)written.\n".
			"Usually this requires that those have owner/group of \"www-data\" and access mode is 7** (e.g. 744) for folder and 6** for TW.";
	} else { // incremental saving from the saveChanges request
		$changesJSON = $_POST['saveChanges'];
		$changes = json_decode($changesJSON);
		$errors = updateTW($wikiPath, $changes);
		$successStatus = $errors ? $errors : 'saved';
		echo $successStatus;
	}
}
// For a backup request, respond with 'success' or a string explaining the problem
else if (isset($_POST['backupByName']))
{
	$twToBackupFileName = $_REQUEST['wiki'] ? $_REQUEST['wiki'] : Options::get('wikiname');
	if(!isTwInWorkingFolder($twToBackupFileName)) {
		http_response_code(404);
		exit("error: \"$twToBackupFileName\" is not a valid TiddlyWiki in the working folder");
	}
	$twToBackup = $workingFolder . "/" . $twToBackupFileName;

	// remove chars other than -_.a-zA-Z0-9 from file name
	$backupFileName = preg_replace("/[^\w-_\.]/", '', $_POST['backupByName']);
	$requestedBackupSubfolder = $_POST['backupFolder'];
	$defaultTwBackupFolder = "backups";
	// only allow current folder and direct subfolders
	if(!$requestedBackupSubfolder) {
		$backupSubfolder = $defaultTwBackupFolder;
	} else {
		if(preg_match("/^[\w\.]+$/", $requestedBackupSubfolder) && $requestedBackupSubfolder != '..') {
			$backupSubfolder = $requestedBackupSubfolder;
		} else {
			http_response_code(403);
			exit("error: $requestedBackupSubfolder is not allowed as a backup subfolder");
		}
	}
	$backupFolder = $workingFolder . "/" . $backupSubfolder;
	if(!file_exists($backupFolder)) mkdir($backupFolder, 0777, true);
	$backupPath = $backupFolder . "/" . $backupFileName;
	// $backupPath file exists, will overwrite

	set_error_handler(function ($errno, $errstr, $errfile, $errline) {
		http_response_code(500);
		exit("error when trying to make a backup copy: $errstr (errno $errno)");
	});
	$success = copy($twToBackup, $backupPath);
	restore_error_handler();

	// unsure if we can in fact end up here with falsy $success
	echo $success ? 'success' : 'creating backup failed';
}
else if (isset($_POST['options']))
{
	function setOption($name, $unsetEmpty = false) {
		Options::set($name, $_POST[$name], $unsetEmpty);
	}

	// $_REQUEST['folder'] is processed "globally" (see above)

	// Make sure the selected wiki file is really in our directory; set it
	if (!isInWokringFolder($_POST['wikiname']))
	//if (strpos(realpath($_POST['wikiname']), getcwd()) === FALSE)
	{
		// security: don't show real path, just the passed "wikiname"
		showMtsPage('<p>' . $_POST['wikiname'] . ' is not in the working directory</p>');
		exit;
	}
	setOption('wikiname');

	setOption('single_wiki_mode', true);
	
	setOption('memory_limit', true);
	if($_POST['memory_limit'] == $system_memory_limit)
		Options::set('memory_limit', '', true);
	
	$saved = Options::save();
	$output = '<p>Active wiki set to ' . Options::get('wikiname') . '</p>';

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
			$htaccess = '
			<Files ~ "^\.(htaccess|htpasswd)$">
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

			file_put_contents($serverFolder . "/" . ".htaccess", $htaccess);
			file_put_contents($serverFolder . "/" . ".htpasswd", $htpasswd);
			// tell the user the password protection is set
			$output .= "Username \"$userName\" and Password set<br>";
			$output .= 'If you forget your password you can delete the .htaccess file through an FTP client to regain access<br>';
		}
	}

	$wikiLink = getFullWikiLink(Options::get('wikiname'));
	$output .= "<p>To start editing your TiddlyWiki now, go to <a href='$wikiLink'>$wikiLink</a></p>";
	showMtsPage($output);
}
else if (isset($_GET['options'])) {
	showOptionsPage();
}
else if (isset($_REQUEST['proxy_to']))
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
	$requestedUrl = $_REQUEST['proxy_to']; // decoded via urldecode, so basically same as request_url in hijacked httpReq
	// parse_url → array with keys (some may be omitted): scheme, host, port, user, pass, path, query, fragment
	$requestedUrlParts = parse_url($requestedUrl);
	$requestedFolderAndFile = getFolderAndFileNameFromPath($requestedUrlParts['path']);
	$requestedFolder = $requestedFolderAndFile['folder'];
	// resolve ./ bits of path (replace /./ with /)
	$requestedFolder = preg_replace('#/(\./)+#', '/', $requestedFolder);
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
	$isRelativePath = strpos($requestedFolder, $mtsFolderUrl) === 0;
	$isSubfolder = strpos($requestedFolderResolved, '..') === false && strpos($requestedFolderResolved, $mtsFolderUrl) === 0;
	$isRelativeAddress = $isSameDomain && $isRelativePath;
	
	//# extract headers (getallheaders? http_get_request_headers from PECL? see https://stackoverflow.com/a/541463/)
	//# extract body (http_get_request_body from PECL? see https://stackoverflow.com/q/7187631/)
	//  try also iterating $_SERVER described in the link
	$request_body = file_get_contents('php://input'); //# returns empty for application/x-www-form-urlencoded and multipart/form-data
	$request = print_r($_REQUEST, true);
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
		$requestSubPath = substr_replace($requestedFolderResolved, '/', 0, strlen($mtsFolderUrl));
		// realpath doesn't seem to be needed: mixed / and \ don't hurt
		//# check if the path can contain %20 (the $requestSubPath bit)
		if(isTwLike($workingFolder . $requestSubPath . $requestedFileDecodedName))
			showTW($workingFolder . $requestSubPath . $requestedFileDecodedName);
		//# grab and serve resources other than TWs?
	} else if($isRelativeAddress) {
		//# remove the previous 2 cases which are included in this one?
	
		// resolve the path
		$relativePath = substr_replace($requestedFolder, '/', 0, strlen($mtsFolderUrl));
		$absolutePath = realpath($workingFolder) . $relativePath;
		$absolutePath = resolvePath($absolutePath); // \ → /, remove /../, /./, multiple / → single /

		// check if it's among dataFolders (consider them as white-list of allowed folders) or their subfolders
		$allowed = false;
		foreach(Options::get('dataFolders') as $allowedPath)
			if(strpos($absolutePath, str_replace('\\', '/', $allowedPath)) === 0) // was: $absolutePath == str_replace('\\', '/', $allowedPath)
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
	$proxy_debug_file = 'proxy_debug_info.txt';
	if($doProxy) {
		$curl_session = curl_init($requestedUrl);
		// return results by curl_exec to $proxiedRequestResponse instead of printing
		curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
		//# use curl_setopt to set session (login)
		//# ...
		//# learn CURLOPT_FOLLOWLOCATION; use CURLOPT_HEADER when needed
		$proxiedRequestResponse = curl_exec($curl_session);
		$failedToLoad = $proxiedRequestResponse === false;
		if($failedToLoad) {
			$request_error = curl_error($curl_session);
			http_response_code(500);
			$debug_mode = true;
		}
		// may also use curl_getinfo for additional info like times of different ~stages, sizes of different ~parts and others
		curl_close($curl_session);

		// respond back to TW
		//# set headers
		// response body
		print !$failedToLoad ? $proxiedRequestResponse :
			"MTS proxy failed to get requested data, details can be found in $proxy_debug_file";
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
		file_put_contents($proxy_debug_file, $test_message);
	}
}
else if (isset($_GET['wikis'])) {

	showWikisOrWiki();
}
// open a wiki by url in request
else if (isset($_GET['wiki'])) {

	if(!is_dir($workingFolder)) {
		showMtsPage("<p>The server working folder is currently unavailable...</p>", '', 404);
		//# make more helpful (what working folder is used? show at least ~name.. what to do?)
		return;
	}
	showTW($workingFolder . "/" . $_GET['wiki'], $_GET['wiki']);
} else {
	if(Options::get('wikiname'))
		showWikisOrWiki();
	else
		showOptionsPage();
}
?>