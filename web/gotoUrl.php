<?php 
require('api/dbconn.php');
require('api/functions.php');

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['HTTP_USER_AGENT'] === 'TelegramBot (like TwitterBot)') {
	header('HTTP/1.1 204 No Content For You My Cache Header Ignoring Friend');
	exit;
}

list($status, $type, $data, $expireAfterDownload) = tryGet($_GET['shortcode'], true);
if ($status !== false) {
	if ($type == 0) {
		header('HTTP/1.1 404 Not Found');
		print('This link does not exist (anymore). Perhaps you would like to <a href="./">shorten your own?</a>');
		exit;
	}
    else if ($type == 1) {
		if ($expireAfterDownload == "1") {
			api_set(getSecretByCode($_GET['shortcode']), "This link has already been downloaded.", false, true);
		}
		if (isset($_GET['preview'])) {
			header('Content-Type: text/plain');
			echo $data;
		}
		else {
			header('HTTP/1.1 307 Temporary Redirect');
			header('Location: ' . $data);
		}
		exit;
	}
	else if ($type == 2) {
		echo $data;

		if ($expireAfterDownload == "1") {
			api_set(getSecretByCode($_GET['shortcode']), "This link has already been downloaded.", false, true);
		}

		exit;
	}
	else if ($type == 3) {
		$fpath = $data[0];
		$fsize = filesize($fpath);
		$original_fname = $data[1];
		$escaped_original_fname = str_replace('"', '\\"', str_replace('\\', '\\\\', $original_fname));
		$ext = strtolower(pathinfo($original_fname, PATHINFO_EXTENSION));
		if (in_array($ext, ['jpg', 'jpeg', 'png', 'bmp', 'gif']) && ! isset($_GET['download'])) {
			// TODO SVG support with denied scripting
			header('Content-type: image/' . $ext);
		}
		else if (isset($_GET['preview']) && in_array($ext, ['json', 'js', 'pdf'])) {
			if ($ext === 'js') {
				$ext = 'javascript';
			}
			header('Content-disposition: inline; filename="' . $escaped_original_fname . '"');
			header('Content-type: application/' . $ext);
		}
		else if (isset($_GET['preview']) && in_array($ext, ['csv', 'css', 'xml'])) {
			header('Content-type: text/' . $ext);
		}
		else if (isset($_GET['preview']) && in_array($ext, ['mp4', 'mkv', 'avi', 'webm', 'ogv', 'mov'])) {
			header('Content-type: video/' . $ext);
		}
		else if (isset($_GET['preview']) && in_array($ext, ['mp3', 'ogg', 'wav', 'aac', 'opus'])) {
			header('Content-type: audio/' . $ext);
		}
		// can probably incorporate more from https://www.file-extensions.org/filetype/extension/name/text-files
		// or should we just detect if it consists of valid ascii/utf-8/utf-16 and, if so, set it to type=text encoding=detected?
		else if (isset($_GET['preview']) && in_array($ext, ['html', 'htm', 'html5', 'txt', 'log', 'nfo', 'asc', 'text', 'srt', 'sub', 'm3u', 'm3u8', 'tsv', 'cnf', 'conf', 'cfg', 'vim', 'vimrc', 'bashrc', 'sh', 'bash', 'ps1', 'bat', 'cmd', 'py', 'c', 'cpp', 'h', 'hpp', 'cs', 'java', 'lua', 'php', 'gml', 'coffee', 'pl', 'pas', 'go', 'rs', 'swift', 'rb', 'hs', 'sql', 'r', 'vbs', 'ts', 'kt', 'scala', 'dart', 'clj', 'nim', 'zig', 'mathml', 'md', 'markdown', 'ascii', 'reg', 'yml', 'yaml', 'dtd', 'xsd', 'smali', 'vcf', 'ics', 'icalendar', 'ical', 'ibf', 'sha1', 'sha256', 'shasum', 'sha256sum', 'sha152', 'sha512sum', 'md5'])) {
			// we don't want to execute html, so treat that as txt
			header('Content-type: text/plain');
		}
		else {
			if (isset($_GET['preview'])) {
				header('Content-type: text/plain');
				print("This link would be a download but the URL specified that you wanted to /view or /preview it only.\n");
				print('File size: ' . number_format($fsize, $decimals=0, $decimal_separator='.', $thousand_separator="'") . " bytes\n");
				print("File name: $original_fname");
				exit;
			}
			else {
				header('Content-type: application/octet-stream');
				header('Content-disposition: attachment; filename="' . $escaped_original_fname . '"');
			}
		}
		header('Content-Length: ' . $fsize);
		flush();

		// Since readfile() does not work (even with while(@ob_end_flush());)...
		set_time_limit(0);
		$fid = fopen($fpath, 'r');
		while ($data = fread($fid, 1024 * 1024)) {
			echo $data;
		}
		fclose($fid);

		if ($expireAfterDownload == "1") {
			api_set(getSecretByCode($_GET['shortcode']), "This link has already been downloaded.", false, true);
		}

		exit;
	}
	else {
		die('Error 194394');
	}
}

$basedir = './';
if (isset($_GET['preview']) || isset($_GET['download'])){
	$basedir = '../';
}

?>
<style>
	body {
		font-size: 17px;
		padding-top: 72px; /* who ever reads the first line of corner text? */
	}
</style>
<strong>This link exists but is empty.</strong> Perhaps the sender is still uploading the file.<br>
<br>
This page automatically loads when content becomes available.<br>
<br>
<span id='lastcheck'></span>
<noscript><meta http-equiv=refresh content=4></noscript>

<script>
	t = 350;
	
	function checkForUpdate() {
		document.getElementById('lastcheck').innerHTML += " <img src='<?php print($basedir); ?>res/img/loading.gif' alt='loading animation' title='Loading...'>";
		aGET('<?php print($basedir); ?>api/v1/check/<?php print(htmlspecialchars($_GET['shortcode'], ENT_COMPAT | ENT_HTML401 | ENT_QUOTES, 'UTF-8')); ?>', function(data) {
			if (data == '1') {
				document.querySelector('body').innerHTML = 'Contents detected, page is being reloaded. If you still see this message, a download should probably have appeared.';
				location.reload();
			}
			else {
				setTimeout(checkForUpdate, t *= 1.1);
				document.getElementById('lastcheck').innerText = 'Last check: ' + new Date().toLocaleTimeString() + '.' + (t > 5000 ? (' Next check will be in ' + Math.round(t/100)/10 + ' second(s).') : '');
			}
		});
	}
	
	function aGET(uri, callback) {
		var req = new XMLHttpRequest();
		req.open("GET", uri, true);
		req.send(null);
		req.onreadystatechange = function() {
			if (req.readyState == 4)
				callback(req.responseText);
		}
	}
	
	checkForUpdate();
</script>


<style type="text/css">
.css1{
position:absolute;top:0px;left:0px;
width:16px;height:16px;
font-family:Arial,sans-serif;
font-size:16px;
text-align:center;
font-weight:bold;
}
.css2{
position:absolute;top:0px;left:0px;
width:10px;height:10px;
font-family:Arial,sans-serif;
font-size:10px;
text-align:center;
}
</style>

<!-- Mouse Follow Clock from Rainbow Arch -->
<!-- This script and many more from : -->
<!-- http://rainbow.arch.scriptmania.com -->
<script language="JavaScript">
if (document.getElementById&&!document.layers){

// *** Clock colours
dCol='#00aa00';   //date colour.
fCol='#000000';   //face colour.
sCol='#000000';   //seconds colour.
mCol='#00aa00';   //minutes colour.
hCol='#00aa00';   //hours colour.

// *** Controls
del=0.8;  //Follow mouse speed.
ref=40;   //Run speed (timeout).

//  Alter nothing below!  Alignments will be lost!
var ieType=(typeof window.innerWidth != 'number');
var docComp=(document.compatMode);
var docMod=(docComp && docComp.indexOf("CSS") != -1);
var ieRef=(ieType && docMod)?document.documentElement:document.body;
theDays=new Array("SUNDAY","MONDAY","TUESDAY","WEDNESDAY","THURSDAY","FRIDAY","SATURDAY");
theMonths=new Array("JANUARY","FEBRUARY","MARCH","APRIL","MAY","JUNE","JULY","AUGUST","SEPTEMBER","OCTOBER","NOVEMBER","DECEMBER");
date=new Date();
day=date.getDate();
year=date.getYear();
if (year < 2000) year=year+1900; 
tmpdate=" "+theDays[date.getDay()]+" "+day+" "+theMonths[date.getMonth()]+" "+year;
D=tmpdate.split("");
N='3 4 5 6 7 8 9 10 11 12 1 2';
N=N.split(" ");
F=N.length;
H='...';
H=H.split("");
M='....';
M=M.split("");
S='.....';
S=S.split("");
siz=40;
eqf=360/F;
eqd=360/D.length;
han=siz/5.5;
ofy=-7;
ofx=-3;
ofst=70;
tmr=null;
vis=true;
mouseY=-10000;
mouseX=-10000;
dy=new Array();
dx=new Array();
zy=new Array();
zx=new Array();
tmps=new Array();
tmpm=new Array(); 
tmph=new Array();
tmpf=new Array(); 
tmpd=new Array();
var sum=parseInt(D.length+F+H.length+M.length+S.length)+1;
for (i=0; i < sum; i++){
dy[i]=0;
dx[i]=0;
zy[i]=0;
zx[i]=0;
}

algn=new Array();
for (i=0; i < D.length; i++){
algn[i]=(parseInt(D[i]) || D[i]==0)?10:9;
document.write('<div id="_date'+i+'" class="css2" style="font-size:'+algn[i]+'px;color:'+dCol+'">'+D[i]+'<\/div>');
tmpd[i]=document.getElementById("_date"+i).style;
}
for (i=0; i < F; i++){
document.write('<div id="_face'+i+'" class="css2" style="color:'+fCol+'">'+N[i]+'<\/div>');
tmpf[i]=document.getElementById("_face"+i).style; 
}
for (i=0; i < H.length; i++){
document.write('<div id="_hours'+i+'" class="css1" style="color:'+hCol+'">'+H[i]+'<\/div>');
tmph[i]=document.getElementById("_hours"+i).style;
}
for (i=0; i < M.length; i++){
document.write('<div id="_minutes'+i+'" class="css1" style="color:'+mCol+'">'+M[i]+'<\/div>');
tmpm[i]=document.getElementById("_minutes"+i).style; 
}
for (i=0; i < S.length; i++){
document.write('<div id="_seconds'+i+'" class="css1" style="color:'+sCol+'">'+S[i]+'<\/div>');
tmps[i]=document.getElementById("_seconds"+i).style;         
}

function onoff(){
if (vis){ 
 vis=false;
 document.getElementById("control").value="Clock On";
 }
else{ 
 vis=true;
 document.getElementById("control").value="Clock Off";
 Delay();
 }
kill();
}

function kill(){
if (vis) 
 document.onmousemove=mouse;
else 
 document.onmousemove=null;
} 

function mouse(e){
var msy = (!ieType)?window.pageYOffset:0;
if (!e) e = window.event;    
 if (typeof e.pageY == 'number'){
  mouseY = e.pageY + ofst - msy;
  mouseX = e.pageX + ofst;
 }
 else{
  mouseY = e.clientY + ofst - msy;
  mouseX = e.clientX + ofst;
 }
if (!vis) kill();
}
function mouse2(e) {
	var fake_event = {
		pageX: e.changedTouches[0].pageX,
		pageY: e.changedTouches[0].pageY,
	};
	mouse(fake_event);
}
document.onmousemove=mouse;
document.ontouchmove=mouse2;

function winDims(){
winH=(ieType)?ieRef.clientHeight:window.innerHeight; 
winW=(ieType)?ieRef.clientWidth:window.innerWidth;
}
winDims();
window.onresize=new Function("winDims()");

function ClockAndAssign(){
time = new Date();
secs = time.getSeconds();
sec = Math.PI * (secs-15) / 30;
mins = time.getMinutes();
min = Math.PI * (mins-15) / 30;
hrs = time.getHours();
hr = Math.PI * (hrs-3) / 6 + Math.PI * parseInt(time.getMinutes()) / 360;

for (i=0; i < S.length; i++){
 tmps[i].top=dy[D.length+F+H.length+M.length+i]+ofy+(i*han)*Math.sin(sec)+scrollY+"px";
 tmps[i].left=dx[D.length+F+H.length+M.length+i]+ofx+(i*han)*Math.cos(sec)+"px";
 }
for (i=0; i < M.length; i++){
 tmpm[i].top=dy[D.length+F+H.length+i]+ofy+(i*han)*Math.sin(min)+scrollY+"px";
 tmpm[i].left=dx[D.length+F+H.length+i]+ofx+(i*han)*Math.cos(min)+"px";
 }
for (i=0; i < H.length; i++){
 tmph[i].top=dy[D.length+F+i]+ofy+(i*han)*Math.sin(hr)+scrollY+"px";
 tmph[i].left=dx[D.length+F+i]+ofx+(i*han)*Math.cos(hr)+"px";
 }
for (i=0; i < F; i++){
 tmpf[i].top=dy[D.length+i]+siz*Math.sin(i*eqf*Math.PI/180)+scrollY+"px";
 tmpf[i].left=dx[D.length+i]+siz*Math.cos(i*eqf*Math.PI/180)+"px";
 }
for (i=0; i < D.length; i++){
 tmpd[i].top=dy[i]+siz*1.5*Math.sin(-sec+i*eqd*Math.PI/180)+scrollY+"px";
 tmpd[i].left=dx[i]+siz*1.5*Math.cos(-sec+i*eqd*Math.PI/180)+"px";
 }
if (!vis)clearTimeout(tmr);
}

buffW=(ieType)?80:90;
function Delay(){
scrollY=(ieType)?ieRef.scrollTop:window.pageYOffset;
if (!vis){
 dy[0]=-100;
 dx[0]=-100;
}
else{
 zy[0]=Math.round(dy[0]+=((mouseY)-dy[0])*del);
 zx[0]=Math.round(dx[0]+=((mouseX)-dx[0])*del);
}
for (i=1; i < sum; i++){
 if (!vis){
  dy[i]=-100;
  dx[i]=-100;
 }
 else{
  zy[i]=Math.round(dy[i]+=(zy[i-1]-dy[i])*del);
  zx[i]=Math.round(dx[i]+=(zx[i-1]-dx[i])*del);
 }
if (dy[i-1] >= winH-80) dy[i-1]=winH-80;
if (dx[i-1] >= winW-buffW) dx[i-1]=winW-buffW;
}

tmr=setTimeout('Delay()',ref);
ClockAndAssign();
}
window.onload=Delay;
}
</script>

