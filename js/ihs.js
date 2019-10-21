var shared = {
	// *
	getFile_fromUrl     : function(url, method){
		return new Promise(function(resolve, reject) {
			var finished = function(data) {
				resolve( this.response );
			};
			var error = function(data) {
				console.log("error:", this, data);
				reject({
					type: data.type,
					xhr: xhr
				});
			};
			var xhr = new XMLHttpRequest();
			xhr.addEventListener("load", finished);
			xhr.addEventListener("error", error);
			xhr.open(
				// "POST", // Type of method (GET/POST)
				// "GET",  // Type of method (GET/POST)
				method ,
				url        // Destination
			, true);
			xhr.send();
		});
	} ,
	// *
	activateProgressBar : function(turnItOn){
		// Activate the progress bar and screen darkener.
		if     (turnItOn === true ) {
			document.querySelector("#progressbarDiv").style.display = "block";
			document.querySelector("#entireBodyDiv") .style.opacity = "1";
			document.querySelector("#entireBodyDiv") .style.display = "block";
		}
		// De-activate the progress bar and screen darkener.
		else if(turnItOn === false) {
			document.querySelector("#progressbarDiv").style.display = "none";
			document.querySelector("#entireBodyDiv") .style.opacity = "0";
			setTimeout(function(){ document.querySelector("#entireBodyDiv").style.display="none"; }, 50);
		}
	}    ,
	// *
	serverRequest       : function(formData){
		// Make sure that a ._config key exists and that it has values.
		if (typeof formData._config              == "undefined") { formData._config = {}; }
		if (typeof formData._config.responseType == "undefined") { formData._config.responseType = "json"; }
		if (typeof formData._config.hasFiles     == "undefined") { formData._config.hasFiles = false; }
		if (typeof formData._config.filesHandle  == "undefined") { formData._config.filesHandle = null; }
		if (typeof formData._config.method       == "undefined") { formData._config.method = "POST"; }
		if (typeof formData._config.processor    == "undefined") { formData._config.processor = "index_p.php"; }

		return new Promise(function(resolve, reject) {
			var progress_showPercent = function() {};

			var progress_hidePercent = function() {};

			// Event handlers.
			var updateProgress = function(oEvent) {
				return;
				/*
				var text = "Progress:";
				if (oEvent.lengthComputable) {
					var percentComplete = oEvent.loaded / oEvent.total * 100;
					console.log(text, "percentComplete:", percentComplete, oEvent);
				}
				else {
					// Unable to compute progress information since the total size is unknown
					// console.log(text, "cannot determine", oEvent);
				}
				*/
			};
			var transferComplete = function(evt) {
				// The default responseType is text if it is not specified.
				// However, this function (serverRequest) defaults it to 'json' if it is not specified.
				var data = {};

				switch (this.responseType) {
					case 'text':
						{ data = this.responseText; break; }
					case 'arraybuffer':
						{ data = this.response; break; }
					case 'blob':
						{ data = this.response; break; }
					case 'json':
						{ data = this.response; break; }
					default:
						{ data = this.responseText; break; }
				}

				// Required for IE.
				if (formData._config.responseType == "json" && typeof data == "string") {
					data = JSON.parse(data);
				}

				// console.log("554",this, this.responseType);
				resolve(data);
			};
			var transferFailed = function(evt) {
				console.log("An error occurred during the transfer.");
				reject({
					'type': evt.type,
					'xhr': xhr,
					'evt': evt,
				});
			};
			var transferCanceled = function(evt) {
				console.log("The transfer has been canceled by the user.", evt);
			};
			var loadEnd = function(e) {
				// console.log("The transfer finished (although we don't know if it succeeded or not).", e);
				try { shared.activateProgressBar(false); }
				catch (e) {}
			};

			// Create the form.
			var fd = new FormData();
			var o = formData.o;
			// fd.append("o" , formData.o );

			// Add the keys and values.
			for (var prop in formData) {
				// Skip the "_config" key.
				if (prop == "_config") { continue; }
				if (prop == "_config") { continue; }
				// Append the key and value.
				fd.append(prop, formData[prop]);
			}

			// Are there files included?
			if (formData._config.hasFiles) {
				// console.log("Uploading this many files:", formData._config.filesHandle.files.length);
				for (var i = 0; i < formData._config.filesHandle.files.length; i++) {
					// console.log("On file " + (i + 1) + " of " + formData._config.filesHandle.files.length, "(" + formData._config.filesHandle.files[i].name + ")");
					fd.append(formData._config.filesHandle.files[i].name, formData._config.filesHandle.files[i]);
				}
			}

			var xhr = new XMLHttpRequest();

			xhr.addEventListener("progress", updateProgress);
			xhr.addEventListener("load", transferComplete);
			xhr.addEventListener("error", transferFailed);
			xhr.addEventListener("abort", transferCanceled);
			xhr.addEventListener("loadend", loadEnd);

			xhr.open(
				formData._config.method,
				formData._config.processor + "?o=" + o + "&r=" + (new Date()).getTime(),
				true
			);

			// If a responseType was specified then use it.
			if (formData._config && formData._config.responseType) {
				// switch( this.responseType ){
				// console.log(formData._config.responseType);
				switch (formData._config.responseType) {
					case 'text':
						{ xhr.responseType = "text"; break; }
					case 'arraybuffer':
						{ xhr.responseType = "arraybuffer"; break; }
					case 'blob':
						{ xhr.responseType = "blob"; break; }
					case 'json':
						{ xhr.responseType = "json"; break; }
					default:
						{ xhr.responseType = "json"; break; }
				}
			}
			// Otherwise, it is almost always 'json' so specify that.
			else {
				xhr.responseType = "json";
			}

			try { shared.activateProgressBar(true); }
			catch (e) {}

			xhr.send(fd);
		});

		// USAGE EXAMPLE:
		// You can skip the _config part in most cases unless you want to specify a value there that isn't the default.
		//	var formData = {
		//		"o"       : "test",
		//		"somekey"  : "some value"           ,
		//		"_config" : {
		//			"responseType" : "json",
		//			"hasFiles"     : false ,
		//			"filesHandle"  : null  , // document.querySelector('#emu_gameDb_builtInGames_choose');
		//			"method"       : "POST", // POST or GET
		//			"processor"    : "index_p.php"
		//		}
		//	};
		//	var prom1 = mc_inputs.funcs.serverRequest(formData);

	}    ,
};
function runScan(deleteFlaggedPosts){
	if(deleteFlaggedPosts){
		if(!confirm("runScan (WITH DELETE): Are you sure?\n\n THIS WILL DELETE ALL FLAGGED POSTS.")){ return; }
	}

	var formData = {
		"o"       : "ajax_runScan",
		"deleteFlaggedPosts"  : deleteFlaggedPosts           ,
		"_config" : {
			"responseType" : "json",
			"hasFiles"     : false ,
			"filesHandle"  : null  , // document.querySelector('#emu_gameDb_builtInGames_choose');
			"method"       : "POST", // POST or GET
			"processor"    : "api/ihs_p.php"
		}
	};
	var prom1 = shared.serverRequest(formData);
	prom1.then(
		function(res){
			// console.log("SUCCESS: RES:", res);
			displayData('all', res);
		}
		,
		function(res){
			console.log("FAILURE: RES:", res);
			alert("ERROR! See the dev console for details.");
		}
	);
}

function prompt_new_spammyWord(){
	let word = prompt("What is the word?");
	let category = prompt("What is the category");

	if     (!word)    { alert("ERROR: Invalid word!"); }
	else if(!category){ alert("ERROR: Invalid category!"); }
	else{
		new_spammyWord(word, category);
	}
}
function new_spammyWord(word, category){
	var formData = {
		"o"       : "ajax_new_spammyWord",
		"word"    : word,
		"category": category,
		"_config" : {
			"responseType" : "json",
			"hasFiles"     : false ,
			"filesHandle"  : null  , // document.querySelector('#emu_gameDb_builtInGames_choose');
			"method"       : "POST", // POST or GET
			"processor"    : "api/ihs_p.php"
		}
	};
	var prom1 = shared.serverRequest(formData);
	prom1.then(
		function(res){
			console.log("SUCCESS: RES:", res);
			runScan(0);
		}
		,
		function(res){
			console.log("FAILURE: RES:", res);
			alert("ERROR! See the dev console for details.");
		}
	);
}
function delete_spammyWord(id, word, category){
	var formData = {
		"o"       : "ajax_delete_spammyWord",
		"id"      : id,
		"_config" : {
			"responseType" : "json",
			"hasFiles"     : false ,
			"filesHandle"  : null  , // document.querySelector('#emu_gameDb_builtInGames_choose');
			"method"       : "POST", // POST or GET
			"processor"    : "api/ihs_p.php"
		}
	};
	var prom1 = shared.serverRequest(formData);
	prom1.then(
		function(res){
			// console.log("SUCCESS: RES:", res);
			runScan(0);
		}
		,
		function(res){
			console.log("FAILURE: RES:", res);
			alert("ERROR! See the dev console for details.");
		}
	);
}
function update_spammyWord(id, elem){
	word     = elem.closest('tr').querySelector(".input_word").value ;
	category = elem.closest('tr').querySelector(".input_category").value ;

	var formData = {
		"o"       : "ajax_update_spammyWord",
		"id"      : id,
		"word"    : word,
		"category": category,
		"_config" : {
			"responseType" : "json",
			"hasFiles"     : false ,
			"filesHandle"  : null  , // document.querySelector('#emu_gameDb_builtInGames_choose');
			"method"       : "POST", // POST or GET
			"processor"    : "api/ihs_p.php"
		}
	};
	var prom1 = shared.serverRequest(formData);
	prom1.then(
		function(res){
			// console.log("SUCCESS: RES:", res);
			runScan(0);
		}
		,
		function(res){
			console.log("FAILURE: RES:", res);
			alert("ERROR! See the dev console for details.");
		}
	);
}

function populateInfoTables(data){
	// Populate the spammy words table.
	//

	// console.log("populateInfoTables", data.spammyWords_table);

	// Populate the known spammers table.
	var spammyWords_table    = document.querySelector("#spammyWords_table");
	for(let i = spammyWords_table.rows.length - 1; i > 0; i--){ spammyWords_table.deleteRow(i); }
	var fragTable=document.createDocumentFragment();

	for(let i=0; i<data.spammyWords_table.length; i+=1){
		var thisrow = data.spammyWords_table[i];

		var temp_tr   = document.createElement("tr");
		temp_tr.setAttribute('json', JSON.stringify(thisrow,null,0));

		// var temp_td1  = document.createElement("td"); temp_tr.appendChild(temp_td1);
		// var temp_td2  = document.createElement("td"); temp_tr.appendChild(temp_td2);
		var temp_td3  = document.createElement("td"); temp_tr.appendChild(temp_td3);
		var temp_td4  = document.createElement("td"); temp_tr.appendChild(temp_td4);
		var temp_td5  = document.createElement("td"); temp_tr.appendChild(temp_td5);

		// temp_td1.innerHTML = thisrow['id'];       ; // id
		// temp_td2.innerHTML = thisrow['tstamp'];   ; // tstamp
		temp_td3.innerHTML = "<input class='input_word'     type='text' value='"+thisrow['word']+"'>";     ; // word
		temp_td4.innerHTML = "<input class='input_category' type='text' value='"+thisrow['category']+"'>";     ; // category

		let id       = "\""+thisrow['id']      +"\"" ;
		let word     = "\""+thisrow['word']    +"\"" ;
		let category = "\""+thisrow['category']+"\"" ;

		 // BUTTONS
		temp_td5.innerHTML = ""+
			"<input type='button' onclick='delete_spammyWord("+id+", "+word+", "+category+");' value='delete'>" +
			"<input type='button' onclick='update_spammyWord("+id+", this);' value='update'>" +
			"";

		fragTable.appendChild(temp_tr);
	}

	spammyWords_table.appendChild(fragTable);

	// Populate the known spammers textarea.
	var knownspammers    = document.querySelector("#knownspammers");
	knownspammers.value  = data.knownSpamAccounts.reverse().join('\n');

}

function displayData(type, data){
	var deletionCounts    = document.querySelector("#deletionCounts");

	// Populate the info tables.
	populateInfoTables(data);

	var cmdline_output    = document.querySelector("#cmdline_output");
	cmdline_output.value  = data.cmdline_output;

	var untrusted_topics = document.querySelector("#untrusted_topics");
	var trusted_topics   = document.querySelector("#trusted_topics")  ;
	var deleted_topics   = document.querySelector("#deleted_topics")  ;
	var recentDeletions  = document.querySelector("#recentDeletions") ;

	//
	function genTable_untrusted(table, data){
		var fragTable=document.createDocumentFragment();
		for(let i = table.rows.length - 1; i > 0; i--){ table.deleteRow(i); }
		for(let i=0; i<data.length; i+=1){

			var thisrow = data[i];

			var temp_tr   = document.createElement("tr");
			temp_tr.setAttribute('json', JSON.stringify(thisrow,null,0));

			// TD: INFO
			var temp_td1  = document.createElement("td"); temp_tr.appendChild(temp_td1);
			// TD: ACTIONS
			var temp_td2  = document.createElement("td"); temp_tr.appendChild(temp_td2);

			temp_td1.innerHTML+= "TOPIC: " + thisrow.topic + "<br>";
			temp_td1.innerHTML+= 'LAST AUTHOR: <a href="http://uzebox.org/forums/'+thisrow.lastPosterURL+'" target="_blank">'+thisrow.lastPostAuthor+'</a>' + ", ";
			temp_td1.innerHTML+= 'ORG AUTHOR: <a href="http://uzebox.org/forums/'+thisrow.authorURL+'" target="_blank">'+thisrow.author+'</a>' + "<br>";
			temp_td1.innerHTML+= 'POST DATE: ' + thisrow.lastPostDate + '' +'<br>' ;
			temp_td1.innerHTML+= '' +
				'LAST IP: ' +thisrow.postip + '' + ', ' +
				'<a href="'+thisrow.whoisip         +'" target="_blank">WhoisIp.ovh</a>' + ', ' +
				'<a href="'+thisrow.stopforumspamURL+'" target="_blank">stopforumspam.com</a>' + '<br>' +
				'';
			temp_td1.innerHTML+= 'POST LINK: <a href="http://uzebox.org/forums/'+thisrow.lastPostURL+'" target="_blank">link</a>' + '<br>';
			temp_td1.innerHTML+= "" + "FLAGGED: "+
				(thisrow.deletionReason != ''
					? 'YES: ' + thisrow.deletionReason + "<br>"
					: 'NO' + "<br>"
				)
			;

			if(thisrow.spammywordsCNT){
				temp_td1.innerHTML+="<br>*** <b><u>SPAMMY WORDS:</u></b> " +
					'('+thisrow.spammywordsCNT+') '+
					(thisrow.spammywords.split(",").join('\n') ) + "<br>" ;
			}
			else{
				// temp_td1.innerHTML+='('+thisrow.spammywordsCNT+')';
			}

			/* options      */
			temp_td2.innerHTML=
				  "<button class=\"option_buttons\" onclick=\"deleteOnePost( this.closest('tr').getAttribute('json') );\">Delete</button>"
				+ "<button class=\"option_buttons\" onclick=\"addKnownSpammer( this.closest('tr').getAttribute('json') );\">Spammer</button>"
				+ "<button class=\"option_buttons\" onclick=\"addTrustedUser( this.closest('tr').getAttribute('json') );\">Trusted</button>"
			;

			/* delete flag  */

			fragTable.appendChild(temp_tr);
		}
		table.appendChild(fragTable);
}

	function genTable_trusted  (table, data){
		var fragTable=document.createDocumentFragment();
		for(let i = table.rows.length - 1; i > 0; i--){ table.deleteRow(i); }
		for(let i=0; i<data.length; i+=1){
			var thisrow = data[i];

			var temp_tr   = document.createElement("tr");
			temp_tr.setAttribute('json', JSON.stringify(thisrow,null,0));

			let link = 'http://uzebox.org/forums/'+thisrow.lastPostURL ;

			// topicoptions
			var temp_td1  = document.createElement("td"); temp_tr.appendChild(temp_td1);

			// org_auth
			var temp_td2  = document.createElement("td"); temp_tr.appendChild(temp_td2);
			// last_auth
			var temp_td3  = document.createElement("td"); temp_tr.appendChild(temp_td3);
			// link
			// var temp_td4  = document.createElement("td"); temp_tr.appendChild(temp_td4);

			/* topic        */
			temp_td1.innerHTML='<a href="'+link+'" target="_blank">'+thisrow.topic+'</a>';''

			/* org_auth     */
			temp_td2.innerHTML='<a href="http://uzebox.org/forums/'+thisrow.authorURL+'" target="_blank">'+thisrow.author+'</a>';

			/* last_auth    */
			temp_td3.innerHTML='<a href="http://uzebox.org/forums/'+thisrow.lastPosterURL+'" target="_blank">'+thisrow.lastPostAuthor+'</a>';

			/* link         */
			// temp_td4.innerHTML= '<a href="'+link+'" target="_blank">link</a>';

			// Is this unread?
			if(thisrow.unread){
				temp_td1.innerHTML += " <span style='font-weight:bold;font-size: 125%;color:red;'>[UNREAD]</span> " ;
			}

			fragTable.appendChild(temp_tr);
		}
		table.appendChild(fragTable);
	}
	function genTable_deleted  (table, data){
		var fragTable=document.createDocumentFragment();
		for(let i = table.rows.length - 1; i > 0; i--){ table.deleteRow(i); }
		for(let i=0; i<data.length; i+=1){
			var thisrow = data[i];
			// console.log(thisrow);

			var temp_tr   = document.createElement("tr");

			var temp_td1  = document.createElement("td"); temp_tr.appendChild(temp_td1);
			var temp_td2  = document.createElement("td"); temp_tr.appendChild(temp_td2);
			var temp_td3  = document.createElement("td"); temp_tr.appendChild(temp_td3);
			var temp_td4  = document.createElement("td"); temp_tr.appendChild(temp_td4);
			var temp_td5  = document.createElement("td"); temp_tr.appendChild(temp_td5);
			var temp_td6  = document.createElement("td"); temp_tr.appendChild(temp_td6);
			var temp_td7  = document.createElement("td"); temp_tr.appendChild(temp_td7);

			// Last Post Date (UTC-6)
			temp_td1.innerHTML=thisrow.lastPostDate;
			// Last Author
			temp_td2.innerHTML=thisrow.lastPostAuthor;
			// IP
			temp_td3.innerHTML=thisrow.postip;
			// Deletion Reason
			temp_td4.innerHTML=thisrow.deletionReason;
			// Forum Name
			temp_td5.innerHTML=thisrow.forumname;
			// Topic
			temp_td6.innerHTML=thisrow.topic;
			// Original Author
			temp_td7.innerHTML=thisrow.author;

			fragTable.appendChild(temp_tr);
		}
		table.appendChild(fragTable);
	}
	function genTable_recentdeletions  (table, data){
		var fragTable=document.createDocumentFragment();
		for(let i = table.rows.length - 1; i > 0; i--){ table.deleteRow(i); }
		for(let i=0; i<data.length; i+=1){

			// Forum Name
			// Topic
			// Original Author
			// Last Author
			// IP
			// Deletion Reason
			// Deletion Date

			var thisrow = data[i];
			// console.log(thisrow);

			var temp_tr   = document.createElement("tr");

			var temp_td1  = document.createElement("td"); temp_tr.appendChild(temp_td1);
			var temp_td2  = document.createElement("td"); temp_tr.appendChild(temp_td2);
			var temp_td3  = document.createElement("td"); temp_tr.appendChild(temp_td3);
			var temp_td4  = document.createElement("td"); temp_tr.appendChild(temp_td4);
			var temp_td5  = document.createElement("td"); temp_tr.appendChild(temp_td5);
			var temp_td6  = document.createElement("td"); temp_tr.appendChild(temp_td6);
			var temp_td7  = document.createElement("td"); temp_tr.appendChild(temp_td7);

			/* Forum Name      */ temp_td1.innerHTML=thisrow.forumname;
			/* Topic           */ temp_td2.innerHTML=thisrow.topic;
			/* Original Author */ temp_td3.innerHTML=thisrow.author;
			/* Last Author     */ temp_td4.innerHTML=thisrow.lastPostAuthor;
			/* IP              */ temp_td5.innerHTML=thisrow.postip;
			/* Deletion Reason */ temp_td6.innerHTML=thisrow.deletionReason;
			/* Deletion Date   */ temp_td7.innerHTML=thisrow.deletionDate;

			fragTable.appendChild(temp_tr);
		}
		table.appendChild(fragTable);
	}

	function genTable_deletionCounts(table, data){
		var fragTable=document.createDocumentFragment();
		for(let i = table.rows.length - 1; i > 0; i--){ table.deleteRow(i); }
		for(let i=0; i<data.length; i+=1){
			// console.log("thisrow:", thisrow);
			var thisrow = data[i];
			var temp_tr   = document.createElement("tr");
			var temp_td1  = document.createElement("td"); temp_tr.appendChild(temp_td1);
			var temp_td2  = document.createElement("td"); temp_tr.appendChild(temp_td2);
			var temp_td3  = document.createElement("td"); temp_tr.appendChild(temp_td3);

			// LASTPOST
			// deletionCount
			// topiclastauthorusername

			temp_td1.innerHTML=thisrow.LASTPOST;
			temp_td2.innerHTML=thisrow.deletionCount;
			temp_td3.innerHTML=thisrow.topiclastauthorusername;

			fragTable.appendChild(temp_tr);
		}
		table.appendChild(fragTable);
	}

	if(type == 'all'){
		// displayData('untrusted'      , data);
		// displayData('trusted'        , data);
		// displayData('deleted'        , data);
		// displayData('recentdeletions', data);
		// displayData('knownspammers'  , data);
		genTable_untrusted       ( untrusted_topics , data.res.untrustedTopics );
		genTable_trusted         ( trusted_topics   , data.res.trustedTopics   );
		genTable_deleted         ( deleted_topics   , data.res.deleted         );
		genTable_recentdeletions ( recentDeletions  , data.res.recentDeletions );
		genTable_deletionCounts  ( deletionCounts   , data.LatestDeletionCounts );
	}
	else{
		switch(type){
			case 'untrusted'       : { genTable_untrusted       ( untrusted_topics, data.res.untrustedTopics ); break; }
			case 'trusted'         : { genTable_trusted         ( trusted_topics  , data.res.trustedTopics   ); break; }
			case 'deleted'         : { genTable_deleted         ( deleted_topics  , data.res.deleted         ); break; }
			case 'recentdeletions' : { genTable_recentdeletions ( recentDeletions , data.res.deleted         ); break; }
			default : { return; }
		}
	}

}
function deleteOnePost(json){
	// NOTE: Expects JSON in text form (not a JSON object.)

	if(!confirm("deleteOnePost: Are you sure?\n\n THIS ONE POST WILL BE DELETED.")){ return; }

	var formData = {
		"o"          : "ajax_deletePost" ,
		"thisRecord"         : json ,
		"deleteFlaggedPosts" : 0    ,
		"deletionReason"     : 'SPAM POST'   ,
		"_config" : {
			"responseType" : "json",
			"hasFiles"     : false ,
			"filesHandle"  : null  , // document.querySelector('#emu_gameDb_builtInGames_choose');
			"method"       : "POST", // POST or GET
			"processor"    : "api/ihs_p.php"
		}
	};
	var prom1 = shared.serverRequest(formData);

	prom1.then(
		function(res){
			console.log("deleteOnePost: SUCCESS: RES:", res);
			displayData('all', res);
		}
		,
		function(res){
			console.log("FAILURE: RES:", res);
			alert("ERROR! See the dev console for details.");
		}
	);
}
function addKnownSpammer(json){
	// console.log(json); return;

	// NOTE: Expects JSON in text form (not a JSON object.)

	// if(!confirm("addKnownSpammer: Are you sure?\n\n THIS WILL ALSO DELETE THEIR POST(S).")){ return; }
	if(!confirm("addKnownSpammer: Are you sure?\n\n THIS WILL ADD THE USERNAME TO THE DB.\n\nYOU STILL NEED TO RUN A DELETION.")){ return; }

	// Normalize multiple.
	// deleteFlaggedPosts = deleteFlaggedPosts==true ? 1 : 0 ;

	var formData = {
		"o"          : "ajax_addKnownSpammer" ,
		"thisRecord" : json              ,
		"_config" : {
			"responseType" : "json",
			"hasFiles"     : false ,
			"filesHandle"  : null  , // document.querySelector('#emu_gameDb_builtInGames_choose');
			"method"       : "POST", // POST or GET
			"processor"    : "api/ihs_p.php"
		}
	};
	var prom1 = shared.serverRequest(formData);

	prom1.then(
		function(res){
			console.log("addKnownSpammer: SUCCESS: RES:", res);
			// displayData('all', res);
		}
		,
		function(res){
			console.log("FAILURE: RES:", res);
			alert("ERROR! See the dev console for details.");
		}
	);
}
function addTrustedUser(json){
	// NOTE: Expects JSON in text form (not a JSON object.)

	if(!confirm("addTrustedUser: Are you sure?\n\n THE USER'S POSTS WILL BE SAFE FROM DELETION.")){ return; }

	var formData = {
		"o"          : "ajax_addTrustedUser" ,
		"thisRecord" : json              ,
		"deleteFlaggedPosts"  : 0           ,
		"_config" : {
			"responseType" : "json",
			"hasFiles"     : false ,
			"filesHandle"  : null  , // document.querySelector('#emu_gameDb_builtInGames_choose');
			"method"       : "POST", // POST or GET
			"processor"    : "api/ihs_p.php"
		}
	};
	var prom1 = shared.serverRequest(formData);

	prom1.then(
		function(res){
			console.log("addTrustedUser: SUCCESS: RES:", res);
			displayData('all', res);
		}
		,
		function(res){
			console.log("FAILURE: RES:", res);
			alert("ERROR! See the dev console for details.");
		}
	);
}

function ajax_ipUserCounts(){
	var formData = {
		"o"          : "ajax_ipUserCounts" ,
		"_config" : {
			"responseType" : "json",
			"hasFiles"     : false ,
			"filesHandle"  : null  , // document.querySelector('#emu_gameDb_builtInGames_choose');
			"method"       : "POST", // POST or GET
			"processor"    : "api/ihs_p.php"
		}
	};
	var prom1 = shared.serverRequest(formData);

	prom1.then(
		function(res){
			console.log("ajax_ipUserCounts: SUCCESS: RES:", res);
			// displayData('all', res);

		}
		,
		function(res){
			console.log("FAILURE: RES:", res);
			alert("ERROR! See the dev console for details.");
		}
	);
}
function ajax_sql_data_backups(){
	var formData = {
		"o"          : "ajax_sql_data_backups" ,
		"_config" : {
			"responseType" : "json",
			"hasFiles"     : false ,
			"filesHandle"  : null  , // document.querySelector('#emu_gameDb_builtInGames_choose');
			"method"       : "POST", // POST or GET
			"processor"    : "api/ihs_p.php"
		}
	};
	var prom1 = shared.serverRequest(formData);

	prom1.then(
		function(res){
			console.log("ajax_sql_data_backups: SUCCESS: RES:", res);
			alert("Backup file has been created/updated.");

		}
		,
		function(res){
			console.log("FAILURE: RES:", res);
			alert("ERROR! See the dev console for details.");
		}
	);
}

// viewChange('controls');
// viewChange('manage');

function viewChange(view){
	let controls = document.querySelector("#controls");
	let manage   = document.querySelector("#manage");
	let info     = document.querySelector("#info");
	let sections = document.querySelectorAll(".sections");
	let newView = undefined;

	switch(view){
		case "controls":{ newView = controls; break; }
		case "manage"  :{ newView = manage  ; break; }
		case "info"    :{ newView = info    ; break; }

		default : { return; break; }
	}

	// Hide all views.
	sections.forEach(function(d){
		d.classList.remove("show");
	});

	// Show the selected view.
	newView.classList.add("show");
}

window.onload = function(){
	window.onload = null ;
	runScan(0);

	document.querySelector("#runScan_noDelete").addEventListener('click', function(){
		runScan(0);
	}, false);
	document.querySelector("#runScan_Delete").addEventListener('click', function(){
		runScan(1);
	}, false);
};