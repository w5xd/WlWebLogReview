WlWebLogReview
==============

This is a PHP application that can serve audio snippets from recording contest
logging WAV files from contests logged with WriteLog (http://writelog.com).
Instructions are provided for how to process your WAV files to put on your
webserver, and where to put the ADIF files.


<h3>Files you must upload to your PHP server</h3>
In addition to all the files from this repository, you must also upload these files created by WriteLog from the .wl file:
<ul>
	<li>In WriteLog, do a File Save As... and chose ADIF format</li>
	<li>From WriteLog's AudioRecording directory, you must upload the following files:
<ul>
	<li>StartingTimes.txt</li>
	<li>all the ReceivedAudio<em>nnn</em>.WAV files</li>
</ul>
</li>
</ul>
Here is the directory structure you must create on your PHP server and the files in it (from this repo):
<ul>
	<li>index.htm <em>empty file</em></li>
	<li>noaudio.wav </li>
	<li>ReceivedAudio.php </li>
	<li>wlConfig.php <em>edited as below</em></li>
	<li>wlLogReview.php </li>
	<li>wlQso.ico </li>
	<li>chageMe<em> make up an obscure directory name and create it in your web server. Upload these data files to that subdirectory:
</em>
<ul>
	<li>StartingTimes.txt <em>as created by WriteLog in its AudioReview directory</em></li>
	<li>ReceivedAudio<strong><em>nnn</em></strong>.WAV <em>ditto. Note: All the .WAV files named in </em>StartingTimes.txt<em> must be uploaded else <strong>none</strong> of them will work. If you want to test without all the .WAV files, then edit StartingTimes.txt to refer only to the uploaded ones.
</em></li>
	<li>&lt;log&gt;.adi<em> as created by WriteLog using File Save As...
</em></li>
</ul>
</li>
</ul>
The URL for web users to access your server will be:

http://<em>&lt;web-server's-ip-address&gt;</em>//<strong>wlLogReview.php</strong>

If you prefer another name to <strong>wlLogReview.php</strong>, you may change it using mainPageName in wlConfig.php; see below.<strong>
</strong>
<h3>Editing wlConfig.php</h3>
<pre>
<span style="font-family: courier new;">&lt;?php
$myCallSign = "W5XD";           // For displaying on the page.
$adifFileName = "changeMe.adi";   // Name of the ADIF file you put on your PHP server
$maxQsosFromQuery = 100;        // Maximum QSOs it will put on a page of results
$enableEmbedPlayers=false;      // Enables the Show Media Player button on the page
$enableMatchUsingRegularExpression = true; // Advanced feature
$serveLeftAndRightAudioSeparately = true;  // Advanced feature.
$byteRateAdjust = 1.0; // Compressed audio (formats other than PCM) may not report exactly
$secondsBefore = 30;            // Seconds of audio to play before the QSO log time
$secondsAfter = 10;             // Seconds of audio to play after the QSO log time
// Anything you want on the page may be put as HTML in $userHtml
$userHtml = "&lt;p&gt;User HTML goes here&lt;/p&gt;";</span><span style="font-family: courier new;"> $mainPageName = "wlLogReview.php"; // change this only if you rename the main page
$subdirectoryName = "changeMe/"; // put your adi, txt, and wav files in a subdirectory to prevent browser access
$debugAudio = false;    // Advanced feature
/* WARNING DO NOT ADD BLANK LINES ANYWHERE IN THIS FILE */ ?&gt;</span>
</pre>

$<em>myCallSign </em>is how you want your call displayed on the web page.

$<em>adifFileName </em>is the name of the ADIF file containing the log you want to post.

$<em>maxQsosFromQuery </em>is the maximum number of QSOs the page will display in reponse to a query of your ADIF file. If the number of matching QSOs exceeds this, the results page has an added "Skip" form which enables the user to go through the matching QSOs in batches of $maxQsosFromQuery.

$<em>enableEmbedPlayers </em>set to <strong>false </strong>returns results pages with no embedded media players. Turning this to <strong>true</strong> adds a "Show Media Players" button to the query form. The embedded media players give a very nice visual effect and are very convenient, but they have the performance penalty that their mere presence on the page causes the immediate download of <em>all </em>the corresponding audio snippets as a result of the query. If you are browsing a large log with lots of maxQsosFromQuery, then this can be a large bandwidth load.

$<em>enableMatchUsingRegularExpression </em>set to <strong>true</strong> enables the "Search by RE" button on the query form. This is an advanced feature that allows returning pages with QSOs from many stations, but it uses the mind-numbingly complex UNIX regular expression syntax. With this set to <strong>false</strong> the query is for just one callsign and only calls that exactly match will be returned (uppercase and lower case all match).

$<em>serveLeftAndRightAudioSeparately </em>is an advanced topic that requires its own discussion. See below.

<em>$byteRateAdjust</em> is normally left at 1.0. Some compressed .WAV files, however, do not report their byte rate exactly in their header, which can cause the audio snippets to be displaced in time for QSOs logged toward the end of a .WAV file. If you did not turn on compression in WriteLog, then you won't need to adjust this (and you will also have .WAV files that are 155MB per hour.)  Here is a procedure to set this parameter for the case where you have compressed files:
<ul>
	<li>Look at your StartingTimes.txt and choose a .WAV file that is nearly 60 minutes long, judging by the difference between its start and the start of the next .WAV file. Note the start time of the <em>next</em> .WAV file, and then subtract, a short interval, about 5 minutes, to make sure you are looking for a QSO at the end of that .WAV file.</li>
	<li>Look in your log (or .adi file) for a QSO logged at about that 5 minutes-before-the-end of the .WAV file.</li>
	<li>Enter that call into your new web page, wlLogReview.php and playback the audio. If you hear the audio for the QSO you looked up, then all is well and you may leave $byteRateAdjust at 1.0</li>
	<li>If you hear the audio for a QSO <em>before</em> the one you looked up,then $<em>byteRateAdjust </em>needs to be set just higher than 1.0. The value 1.001 will shift the snippet 3.6 seconds later for QSOs logged at the end of a .WAV file.</li>
	<li>If you hear the audio for a QSO <em>after</em> the one you looked up, then $byteRateAdjust needs to be set just lower than 1.0. The value .999 will shift the snipped 3.6 seconds earlier for QSOs logged at the end of a .WAV file.</li>
</ul>
$<em>secondsBefore </em>and $<em>secondsAfter </em>set the length of the audio clip returned in the query. These are the offsets, in seconds, from the time of the QSO in the ADIF file. Since contest operation normally results in logging the QSO after the exchange is both sent and received, the $secondsBefore should normally be bigger than $secondsAfter. Negative values are allowed for these, which can be needed to handle the problem of a clock difference when recording audio on a different PC than was used to log the QSOs. Remember in that case that to shift the entire snippet by an offset, you must add the offset to one of these and subtract from the other (for example, to keep the snippet the same length and move it 30 seconds earlier, you must add 30 to $secondsBefore and subtract 30 from $secondsAfter.)

$<em>userHtml </em>you may set to HTML (or plain text if you like) that you want displayed with the query results.

$<em>mainPageName </em>should not be changed unless you have some reason to change the name of the provided wlLogReview.php file. $mainPageName must be the name of the file that used to be wlLogReview.php.

<em>$subdirectoryName</em> must always be changed. Choose some directory name that internet users will not likely guess. If you set $subdirectoryName="", then anyone can download your StartingTimes.txt file, and the ReceivedAudio<em>nnn</em>.WAV files in their entirety, thus bogging down your web server. And anyone that can guess the name of your ADI file can download your entire log. To prevent this, choose an obscure name for a directory, and place your StartingTimes.txt file, your .adi file, and your .WAV files in that subdirectory.

$<em>debugAudio </em>set to <strong>true</strong> causes the audio snippet calculations routines to return a formatted html page instead of the audio itself. If your audio snippets do not work and you can't tell why, you might try turning this on and see if the extra diagnostics returned tell you anything helpful. This really is a debugging tool only.

Here is what the screen looks like with all the features turned on.

<img src="http://writelog.com/images/WebLogReviewExample.png" alt="WebLogReviewExample.png" />

Here is what it looks like with all the features turned off.
<img src="http://writelog.com/images/WebLogReviewExample.png" alt="WebLogReviewExample.png" />
<h3>WAV File Formats</h3>
The scripts have only been tested on the original WAV format  produced by WriteLog while continuously recording audio to file with WriteLog's compression turned off. However, the PHP code is designed to be general enough to create audio snippets from any .WAV file that adheres to the Microsoft standard for .WAV files. The PCM format files created by WriteLog are uncompressed and quite large compared to what is possible with compression. You may convert WriteLog's .WAV files to a compressed format and give them a try to save disk space on your server.
<h3>$serveLeftAndRightAudioSeparately</h3>
With this feature set to the default <strong>false</strong> setting, the audio snippets served from the page are stereo. The user will hear any two-radio operation just as it occurred during the contest. If instead you would rather web users only be able to hear the audio snippet corresponding to the radio used to log the QSO with them, then you may turn this feature to <strong>true</strong>, but you are not finished setting up your server yet! The PHP scripts provided do not separate the original stereo channels to mono. You have to create separate left and right .WAV yourself with an audio editing tool. Here is the list of things you must do to serve snippets with left and right channels separated:
<ul>
	<li>This list is <em><strong>only</strong></em> for the case where you want to separate the stereo files created by WriteLog so that your web log review plays only the left or right channel based on which radio made the QSO.</li>
	<li>Before the contest, you must setup WriteLog using the "Setup / Log which Radio Makes the QSO".  This puts the left/right radio information in the ADIF file. If you didn't do this before the contest, then you may stop now, because none of the rest of this will work without that information from the original log.</li>
	<li>Create two subdirectories in WriteLog's AudioRecording directory that contains your stereo .WAV files, and the StartingTimes.txt file.</li>
	<li>Name one of the two subdirectories "Left" and the other subdirectory "Right".</li>
	<li>Use an audio editing tool (I use <a href="http://goldwave.com">goldwave</a>, for example) to read ReceivedAudio001.WAV and write out two files. Write a monophonic file of the left channel to subdirectory "Left" using the same file name, ReceivedAudio001.WAV, and then write the right channel to the same named file in subdirectory "Right".</li>
	<li>Repeat for all the original ReceivedAudio<em><strong>nnn</strong></em>.WAV files in the AudioRecording directory.</li>
	<li>With $serverLeftAndRightAudioSeparately turned to <strong>true</strong>, you must provide two separate StartingTimes files. Copy the original StartingTimes.txt file to two separate files: StartingTimes1.txt and StartingTimes2.txt.  Edit these files according to the next step. These new files will remain in the AudioRecording directory--not in the L or R subdirectories. The "1" and the "2" correspond to the values in the "r" column that appear in WriteLog's log display window.</li>
	<li>The QSOs that WriteLog flagged as "1" and "2" will correspond to left or right channels of audio, but which is which depends whether WriteLog was setup its upper Entry Windows as left or right. The upper window is radio "1" and corresponds to StartingTimes1.txt.</li>
	<li>Edit StartingTimes1.txt by putting "Left/" or "Right/" in front of each file name.
<ul>
	<li>Contents of StartingTimes.txt before:<code>
ReceivedAudio001.WAV 2010-07-21 01:53:11
ReceivedAudio002.WAV 2010-07-21 01:54:12
ReceivedAudio003.WAV 2010-07-21 01:55:33
</code></li>
	<li>After:<code>
Left/ReceivedAudio001.WAV 2010-07-21 01:53:11
Left/ReceivedAudio002.WAV 2010-07-21 01:54:12
Left/ReceivedAudio003.WAV 2010-07-21 01:55:33
</code></li>
</ul>
</li>
	<li>Edit StartingTimes2.txt similarly, but start with Right/ instead of Left/, or vice versa</li>
	<li>To summarize, here is what files you have in your directories, and which ones you need to upload to your PHP server.
<ul>
	<li>changeMe(obscure subdirectory name on your server)
<ul>
	<li>StartingTimes.txt--<em>original as created by WriteLog. Not needed on the server in this configuration.</em></li>
	<li>StartingTimes1.txt--<em>edited to indicate whether it is Left or Right--upload to PHP server</em></li>
	<li>StartingTimes2.txt-- <em>ditto</em></li>
	<li>ReceivedAudio001.WAV--<em>original as created by WriteLog. Not needed on the server in this configuration.</em></li>
	<li>...remaing .WAV files--<em>ditto</em></li>
	<li>Left (directory) --<em> create same named directory on the PHP server</em>
<ul>
	<li>ReceivedAudio001.WAV--<em>upload to the PHP server</em></li>
	<li>...remaining .WAV files--<em>upload to the PHP server</em></li>
</ul>
</li>
	<li>Right (directory)--<em> create same named directory on the PHP server</em>
<ul>
	<li>ReceivedAudio001.WAV--<em>upload to the PHP server</em></li>
	<li>...remaining .WAV files--ditto</li>
</ul>
</li>
</ul>
</li>
</ul>
</li>
	<li>To repeat. This list is only for the case of separating the left and right channels from the two radios</li>
</ul>
