<?php
/* This document is copyright (C) 2010 by Wayne Wright, W5XD.
     Permission to copy, use, and even modify this document are granted, PROVIDED THAT:
     (1) You take full responsibility for anything that happens on any webserver due to
     the installation of this script.
     (2) You retain the link to writelog.com at the bottom of this page.

     Instructions.
    There are 6 files in the kit, and all need to be placed in one directory on your
    web server:
        wlLogReview.php (this file)
        ReceivedAudio.php
        wlQso.ico
        noaudio.wav     (File that is played if there is no audio for the QSO)
        index.htm       (Empty file to protect your ADIF and WAV files from being downloaded)
        wlConfig.php

    Only that last one, wlConfig.php, requires you to edit it. You must also place 
    additional files in the web server in a subdirectory:
        <file.adi>  Your ADIF file of the log you want to serve on the web.
        StartingTimes.txt   The file generated by WriteLog when you turn on continuous audio record.
        ReceivedAudio___.WAV    The audio files generated by WriteLog on audio record.

    see http://writelog.com/web-log-review
*/
    include ("wlConfig.php");

    class AdifScan
    {
    var $fp;
    function AdifScan($fname)
    {
        $this->fp = fopen($fname, "rb");
        if ($this->fp)
        {   // position to <EOH>
            while (!feof($this->fp))
            {
                $c = fgetc($this->fp);
                if ($c == "<") $str="";
                $str = $str . $c;
                if ($str == "<EOH>")
                    return;
            }
            fclose($fp);
            $fp = null;
        }
    }
    function openOK() { return $this->fp ? 1 : 0; }
    function close() {fclose($this->fp);}
    function nextRecord()
    {   // return the next QSO record from ADIF as an array
        // The array will have no entries when we reach eof
        $ret = array();
        $parseState = 0;
        $tagName = "";
        $valueLen = 0;
        $value = "";
        while (!feof($this->fp))
        {
            $c = fgetc($this->fp);
            switch ($parseState)
            {
            case 0: // looking for tag
                if ($c == "<") $tagName = "";
                elseif ($c == ":")
                {
                    $valueLen = 0;
                    $parseState++;
                    break;
                }
                elseif ($c == ">")
                {
                    if ($tagName == "EOR")  return $ret;
                }
                else    $tagName = $tagName . $c;
                break;
            case 1: // looking for value length
                if ($c == ">") 
                {
                    $parseState++;
                    break;
                }
                $valueLen *= 10;
                $valueLen += $c;
                break;
            case 2: // reading value
                if ($valueLen-- <= 0)
                {
                    $ret[$tagName] = $value;
                    $value = "";
                    $parseState = 0;
                    break;
                }
                $value = $value . $c;
                break;
            }
        }
        return $ret;
    }
    }

    // php Execution starts here....
    $queryString = $_REQUEST["callsign"];
    $skipMatches = $_REQUEST["skip"];
    $embedPlayers = $_REQUEST["embed"];
    $matchUsingRegularExpression = $_REQUEST["useRE"];
    $toSkip = $skipMatches;
    $matchingQsos = array();
    $adif = new AdifScan($subdirectoryName . $adifFileName);
    if (!$adif->openOK()) die;
    if ($queryString != "")
    while (count($matchingQsos) < $maxQsosFromQuery)
    {
        $vals = $adif->nextRecord();
        if (count($vals) == 0)
            break;
        $matches = false;
        $thisCall = $vals["CALL"];
        if ($matchUsingRegularExpression)
        {
            $matches = preg_match($queryString, $thisCall);
        }
        else
        {
            $matches = (strtoupper($queryString) == strtoupper($thisCall));
        }
        if ($matches && !($toSkip-- > 0))
            array_push($matchingQsos, $vals);
    }
    $adif->close();

?>

<html xmlns="http://www.w3.org/1999/xhtml" >
<head>
    <title>Log for station <?php echo $myCallSign; ?> Contest logging by WriteLog</title>
<?php
    $pbPositions = array();
    for ($i = 0; $i < count($matchingQsos); $i++)
    {
        $t = $matchingQsos[$i]["QSO_DATE"];
        $year = substr($t,0,4);
        $month = substr($t,4,2);
        $day = substr($t,6,2);
        $t = $matchingQsos[$i]["TIME_ON"];
        $hour = substr($t,0,2);
        $min = substr($t,2,2);
        $sec = substr($t,4,2);
        // convert to seconds since the beginning of the Unix epoch
        $playbackPos = mktime($hour,$min,$sec,$month,$day,$year,0);
        $pbPositions[$i] = $playbackPos;
    }
    $audioPresent = $serveLeftAndRightAudioSeparately ? file_exists($subdirectoryName . "StartingTimes1.txt") :
        file_exists($subdirectoryName . "StartingTimes.txt");
?>

</head>
<body background='http://writelog.com/images/background.png'>
   <table style='background-color: #ffffff;text-align: center' border='1' ><tr><td>
    <h1>Contest log for <?php echo $myCallSign; ?></h1> 
    <?php echo ($userHtml); ?>
    <?php if (count($matchingQsos)) { ?>
    
    <table rules='cols' frame='border' >
    <caption style='font-size: 16pt'><strong>Matching QSOs</strong></caption>
    <colgroup width='10'/>    <!--icon-->
    <colgroup width='105'/>   <!--DATE-->
    <colgroup width='60'/>    <!--TIME-->
    <colgroup width='60'/>    <!--FREQ-->
    <colgroup width='50'/>    <!--MOD-->
    <colgroup width='100'/>   <!--CALL-->
    <thead style="color: #008800; text-align: center; font-family:Arial">
    <tr>
        <td></td><td>DATE</td><td>TIME</td><td>FREQ</td><td>MOD</td><td>CALL</td>
        <?php if ($audioPresent) echo "<td>Audio</td>" ?>
        </tr>
        </thead>
    <tbody style="font-family: Courier New;">
<?php
    for ($i = 0; $i < count($matchingQsos); $i++)
    {
        $vals = $matchingQsos[$i];
        if (count($vals) == 0)
            break;
?>
        <tr><td><img src='wlQso.ico' alt=''/></td><td>
<?php
        $date1 = $vals["QSO_DATE"];
        $date = substr($date1,0,4)."-".substr($date1,4,2)."-".substr($date1,6,2);
        echo ($date."</td><td>");
        echo (substr($vals["TIME_ON"],0,4)."</td><td>");
        $freq = $vals["FREQ"] * 1000;
        $freq = (int)($freq + 0.5);
        echo ($freq);
        echo ("</td><td>" . $matchingQsos[$i]["MODE"]);
        echo ("</td><td>".$vals["CALL"]."</td>");
        $urlParams = "position=" . $pbPositions[$i];
        if ($audioPresent)
        {
            if ($serveLeftAndRightAudioSeparately)
                $urlParams .= "&channel=" . (int)($matchingQsos[$i]["APP_WRITELOG_r"]);
            if ($embedPlayers) 
            {
                echo ("<td>");
                echo ("<embed src='ReceivedAudio.php?" . 
                    $urlParams . "' height=10 autostart=false autoplay=false");
                echo ("/></td>"); 
            }
            // compute the download file name
            $dlFileName = $myCallSign . "-" . $matchingQsos[$i]["CALL"];
            for (;;)
            {   // remove characters not valid in a file name
                $valid = strspn($dlFileName, "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-");
                if ($valid == strlen($dlFileName))
                    break;
                // replace any invalid file name character with a dash
                $dlFileName = substr_replace($dlFileName, "-", $valid, 1);
            }
            $dlFileName = "QSO-" . $dlFileName;
            $dlFileName .= ".wav";
            $urlParams = "dlFileName=" . $dlFileName . "&" . $urlParams;
    ?>

        <td><a href='ReceivedAudio.php?download=1&<?php echo($urlParams); ?>'>Download</a></td>
    <?php
        }
    ?>
        </tr>
<?php
    }
    echo "</tbody></table>";
    } // if (count($matchingQsos)
?>
    <table border='1' >
    <caption style='font-size: 16pt'><strong>Search for QSOs</strong></caption>
    <tr><td><form action='<?php echo $mainPageName;?>' method='post'> 
    Callsign: <input type="text" name="callsign" value='<?php echo($queryString); ?>'/><br/>
<?php
    if ($enableEmbedPlayers)
        echo("<input type='checkbox' name='embed'". ($embedPlayers ? "checked=true" : "").
                " /> Show media players <br/>\n");
    if ($enableMatchUsingRegularExpression)
        echo("<input type='checkbox' name='useRE'". ($matchUsingRegularExpression ? "checked=true" : "").
                " /> Search by RE <br/>\n");
    if ((count($matchingQsos) >= $maxQsosFromQuery) && $matchUsingRegularExpression)
        echo("Matches to skip: <input type='text' name='skip' maxlength='5' size='5' value='" . 
        ($skipMatches + $maxQsosFromQuery) . "' /><br/>\n");
?>
    <input type='submit' value='Lookup in log'/>
    </form> </td></tr>
        <?php if ($enableEmbedPlayers || $enableMatchUsingRegularExpression) { ?>
        <tr>
            <td>
                <span style="font-size: 10pt">
                    <?php 
            if ($enableEmbedPlayers) { ?>
                    <p>
                        <strong>Show media players</strong> embeds a media player in your 
                         result page for every matching QSO, which makes it
                        easy to playback the audio for that QSO. But every media player in the 
                        resulting page also immediately
                        downloads the corresponding QSO audio, which can strain your internet 
                        connection for a while.
                    </p>
                    <?php } 
            if ($enableMatchUsingRegularExpression) { ?>
                    <p>
                        <strong>Search by RE</strong> means the "Callsign" you enter is a 
                        UNIX regular expression.
                        Have a look at 
                        <a href="http://php.net/manual/en/function.preg-match.php">the documention</a> 
                        for details.
                        For a simple example, this string will return all the QSOs in the log:
                        <strong>~.*~</strong>
                    </p>
                    <?php } ?>
                </span>

            </td>
        </tr>
        <?php } ?>
    </table>


    <?php    /*If you modify this file, you must keep the <h3> heading and the <p> following.   */ ?>
    <h3>Contest logging by</h3>
    <p><a href='http://writelog.com'><img src='http://writelog.com/images/WL_logo.jpg' alt='http://www.writelog.com'
                width="360" height="40" border="0" /></a></p>
    </td></tr></table>
</body>
</html>
