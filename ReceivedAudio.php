<?php
/*
   This file copyright (C) 2010 by Wayne Wright, W5XD
     Permission to copy, use, and even modify this document are granted, PROVIDED THAT:
     (1) You take full responsibility for anything that happens on any webserver due to
     the installation of this script.
     (2) You NOT separate this file from its companion file, wlLogReview.php and its
     display of a link to WriteLog.com.
*/

class ReceivedAudio
{
/* Class that knows how to decompose Microsoft .WAV files and concatentate
** chunks of them together.
*/
    var $fileName;
    var $hdrSize;   // length of file in bytes (sort of)
    var $fmt;   // the full wave format structure
    var $waveFormat;    // must match for all instances of ReceivedAudio
    var $numChannels;   // " "
    var $sampleRate;    // " "
    var $byteRate;      // " "
    var $blockAlign;    // " "
    var $bitsPerSample; // " "
    var $extraFmt;      // " "
    var $timeStart;     // Unix time of beginning of sound
    var $timeEnd;       // Unix time of end--only after parseChunks
    var $approxLenSec;  // length as computed from $hdrSize
    var $firstData; // seek position of first 'data' chunk
    var $ok;
    var $chunksParsed;
    var $chunkPosArray;
    var $chunkSizeArray;
    var $actualTimeBeginArray;
    var $totalDataBytes;
    var $byteToSendStart;
    var $bytesToSend;
    var $byteRateAdjusted;  // user-specified adjustment for compression
    var $dbg;

    function ReceivedAudio($f, $t, $d, $byteRateAdjust)
    {
    /* in the constructor, confirm that this is a WAVE file
    ** that we can parse properly, and find the position of the
    ** first 'data' chunk.
    */
        $this->ok = 0;
        $this->dbg = $d;
        $this->chunksParsed = 0;
        $this->calcStart($t);
        $this->fileName = $f;
        if (!($fp = fopen($f, "rb")))
        {
            echo ("Failed to open " . $f);
            return;
        }
        $str = fread($fp, 4);
        if ($str == "RIFF") // all .WAV files must have this
        {
        $str = fread($fp, 4);
        for ($i = 3; $i >=0; $i--)
        {
               $this->hdrSize *= 256;
               $this->hdrSize += ord($str[$i]);
        }
        if ($this->dbg) echo ("hdrSize = " . $this->hdrSize . "<br/>");

        $str = fread($fp, 4);
        if ($str == "WAVE") // all .WAV files must have this
        {
        $str = fread($fp, 4);       $this->fmt = $str;
        if ($str == "fmt ") // all .WAV files must have this
        {
        $str = fread($fp, 4);       $this->fmt .= $str;
        for ($i = 3; $i >=0; $i--)
        {
                $chunkSize *= 256;
                $chunkSize += ord($str[$i]);
        }
        $str = fread($fp, 2);       $this->fmt .= $str;
        $this->waveFormat = ord($str[0]) + 256 * ord($str[1]);
        if ($this->dbg) echo ("File has format: " . $this->waveFormat . "<br/>");
        $str = fread($fp, 2);       $this->fmt .= $str;
        $this->numChannels = ord($str[1]);
        $this->numChannels *= 256;
        $this->numChannels += ord($str[0]);
        if ($this->dbg) echo ("File has " . $this->numChannels . " channels<br/>");
        $str = fread($fp, 4);       $this->fmt .= $str;
        for ($i = 3; $i >=0; $i--)
        {
                $this->sampleRate *= 256;
                $this->sampleRate += ord($str[$i]);
        }
        if ($this->dbg) echo ("File has " . $this->sampleRate . " sample rate<br/>");
        $str = fread($fp, 4);   $this->fmt .= $str;
        for ($i = 3; $i >=0; $i--)
        {
                $this->byteRate *= 256;
                $this->byteRate += ord($str[$i]);
        }
        if ($this->dbg) echo ("File has " . $this->byteRate . " byteRate<br/>");
        $this->byteRateAdjusted = $this->byteRate * $byteRateAdjust;
        $str = fread($fp, 2);   $this->fmt .= $str;
        $this->blockAlign = ord($str[0]) + 256 * ord($str[1]);
        if ($this->dbg) echo ("File has " . $this->blockAlign . " blockAlign<br/>");
        $str = fread($fp, 2);   $this->fmt .= $str;
        $this->bitsPerSample = ord($str[0]) + 256 * ord($str[1]);
        if ($this->dbg) echo ("File has " . $this->bitsPerSample . " bitsPerSample<br/>");
        if ($chunkSize > 16)
        {
            $this->extraFmt = fread($fp, $chunkSize-16); // keep whole fmt
            $this->fmt .= $this->extraFmt;
        }
        $this->firstData = ftell($fp);
        $str = fread($fp, 4);
        if ($str == "data")
        {
            $this->ok = 1;
            if ($this->dbg) echo ("File " . $f . " is OK<br/>");
        } else {if ($this->dbg) echo ("File " . $f . " error on data block<br/>");}
        } else {if ($this->dbg) echo ("File " . $f . " is not 'fmt' file<br/>");   }
        } else {if ($this->dbg) echo ("File " . $f . " is not a WAVE file<br/>"); }
        } else {if ($this->dbg) echo ("File " . $f . " is wrong format<br/>"); }
                
        fclose($fp);
        $this->approxLenSec = $this->hdrSize / $this->byteRateAdjusted;
        if ($this->dbg) echo ("approxLenSec = " . $this->approxLenSec . "<br/>");
    }

    // return nonzero if the two WAV files are compatible such
    // that it makes sense to concatentate 'data' chunks from
    // one file to the other.
    function compatible(ReceivedAudio $other)
    {
        if ($this->waveFormat != $other->waveFormat)
        {
            if ($this->dbg) echo ("waveFormat does not match<br/>");
            return 0;
        }
        if ($this->numChannels != $other->numChannels)
        {
            if ($this->dbg) echo ("numChannels does not match<br/>");
            return 0;
        }
        if ($this->sampleRate != $other->sampleRate)
        {
            if ($this->dbg) echo ("sampleRate does not match<br/>");
            return 0;
        }
        if ($this->byteRate != $other->byteRate)
        {
            if ($this->dbg) echo ("byteRate does not match<br/>");
            return 0;
        }
        if ($this->blockAlign != $other->blockAlign)
        {
            if ($this->dbg) echo ("blockAlign does not match<br/>");
            return 0;
        }
        if ($this->bitsPerSample != $other->bitsPerSample)
        {   
            if ($this->dbg) echo ("bitsPerSample does not match<br/>");
            return 0;
        }
        if ($this->extraFmt != $other->extraFmt)
        {
            if ($this->dbg) echo ("extraFmt does not match<br/>");
            return 0;
        }
        if ($this->dbg) echo ("Files are compatible<br/>");
        return 1;
    }
 
        private function calcStart($t)
    {   // Tease apart the string from StartingTimes.txt
        $year = substr($t,0,4);
        $month = substr($t,5,2);
        $day = substr($t,8,2);
        $hour = substr($t,11,2);
        $min = substr($t,14,2);
        $sec = substr($t,17,2);
        // convert to seconds since the beginning of the Unix epoch
        $this->timeStart = mktime($hour,$min,$sec,$month,$day,$year);
        if ($this->dbg) 
            echo ("yr mo da hr mn sec: ". $year . " " . $month . " " . $day . " " . $hour . " " .
                $min . " " . $sec . "<br/>" .
                "starttime: " . $this->timeStart . " <br/>"); 
    }

    function ok() {return $this->ok;}

    // build arrays representing where the chunks are in this file
    private function parseChunks()
    {
        if ($this->chunksParsed) return;
        if ($this->dbg) echo ("parseChunks on " . $this->fileName . "<br/>");
        if (!($fp = fopen($this->fileName, "rb")))
            return;
        $this->chunkPosArray = array();
        $this->chunkSizeArray = array();
        $this->actualTimeBeginArray = array();
        $this->totalDataBytes = 0;
        fseek($fp, $this->firstData, SEEK_SET);
        $timeBegin = $this->timeStart;
        while (!feof($fp))
        {
            $str = fread($fp, 4);
            if ($str == "") // not sure why feof does not pick this up
                break;
            if ($str != 'data')
            {
                if ($this->dbg)
                echo ("oops. should be data chunk.<br/>");
                break;
            }
            $str = fread($fp, 4);
            $chunkSize = 0;
            for ($i = 3; $i >=0; $i--)
            {
                        $chunkSize *= 256;
                        $chunkSize += ord($str[$i]);
            }
            array_push($this->chunkPosArray, ftell($fp));
            array_push($this->actualTimeBeginArray , $timeBegin);
            array_push($this->chunkSizeArray, $chunkSize);
            if ($this->dbg) echo ("chunkSize: " . $chunkSize . "<br/> timeBegin: " .
                $timeBegin . "</br");
            $this->totalDataBytes += $chunkSize;
            $timeBegin += $chunkSize / $this->byteRateAdjusted;
            fseek($fp, $chunkSize, SEEK_CUR);               
        }
        $this->chunksParsed = 1;
        $this->timeEnd = $timeBegin;
        fclose($fp);
        if ($this->dbg) 
        {
                echo ("chunks parsed: " . count($this->chunkSizeArray, 0) . "<br/>");
                echo ("timeEnd: " . $timeBegin . " <br/>");
                echo ("totalDataBytes: " . $this->totalDataBytes . "<br/>");
        }
    }

    function bytesForInterval($start, $stop)
    {   /* return the number of bytes we will write of 'data' chunks
        ** for the given time interval 
        ** Save the exact start/stop byte positions as well */
        if ($this->dbg) echo ("bytesForInterval start: " . $start . 
            ", stop: " . $stop . " on " . $this->fileName . "<br/>");
        if (($start < $this->timeStart + $this->approxLenSec) &&
            ($stop > $this->timeStart))
        {   // there is probably an overlap
            $this->parseChunks();
            // there might NOT have been overlap
            if ($start >= $this->timeEnd) return 0;
            
            $begin = $start;
            if ($begin < $this->timeStart)
                $begin = $this->timeStart;
            $end = $stop;
            if ($end > $this->timeEnd)
                $end = $this->timeEnd;
            $byteBegin = ($begin - $this->timeStart) * $this->byteRateAdjusted;
            $byteEnd = ($end - $this->timeStart) * $this->byteRateAdjusted;
            $byteBegin = (int) $byteBegin;
            $byteEnd = (int) $byteEnd;
            $byteBegin /= $this->blockAlign; // round to nearest block
            $byteEnd /= $this->blockAlign;
            $byteBegin = (int)$byteBegin;
            $byteEnd  = (int)$byteEnd+1;    // send one more block off the end
            $byteBegin *= $this->blockAlign;
            $byteEnd *= $this->blockAlign;
            if ($byteBegin < 0)
                $byteBegin = 0;
            if ($byteEnd > $this->totalDataBytes)
                $byteEnd = $this->totalDataBytes;
            $this->byteToSendStart = $byteBegin;
            $this->bytesToSend = $byteEnd - $byteBegin;
            if ($this->dbg) echo ("byteBegin " . $byteBegin . " byteEnd " . 
                $byteEnd . "<br/>");
            return $this->bytesToSend; // exact number of bytes we will send
        }
        else return 0;
    }

    function ContentLength($bytesOfAudio)
    {
        // Content is all of what is computed in writeWaveHeader, plus 8 more bytes
        // for the "RIFF" and the count following
        return $bytesOfAudio + 12 + strlen($this->fmt) + 8;
    }
    
    function writeWaveHeader($totalBytesForIntervals)
    {   /* write the required header for a .WAV file given
        ** we know $totalBytesForIntervals 'data' chunks will be written */
        if ($this->dbg)
        {
            echo ("Pretending to write header for " . $totalBytesForIntervals . "<br/>");
            return;
        }

        $chunkSize = $totalBytesForIntervals + 12 + strlen($this->fmt);
        $str = "";
        for ($i = 0; $i < 4; $i++)
        {
            $str .= chr($chunkSize%256); $chunkSize /= 256;
        }
        
        echo ("RIFF");
        echo ($str);
        echo ("WAVE"); // 4 of the 12
        echo ($this->fmt);
        echo ("data");  // 8 of the 12
        $str = "";
        $chunkSize = $totalBytesForIntervals;
        for ($i = 0; $i < 4; $i++)
        {
            $str .= chr($chunkSize%256); $chunkSize /= 256;
        }
        echo ($str);        // 12 of the 12 
    }

    function writeDataChunks()
    {
        /* write all the data chunks we have corresponding to the interval*/
        if ($this->dbg)
            echo ("writeDataChunks " . $this->bytesToSend . " which is " .
                $this->bytesToSend/$this->byteRateAdjusted . " sec <br/>");
        $fp = fopen($this->fileName, "rb");
        if (!$fp) die;
        $i = 0;
        while ($this->byteToSendStart  >= $this->chunkSizeArray[$i])
            $this->byteToSendStart -= $this->chunkSizeArray[$i++];
        while ($this->bytesToSend)
        {
            $seekPos = $this->chunkPosArray[$i] + $this->byteToSendStart;
            if ($this->dbg)
                echo ("fseek to " . $seekPos . " <br/>");
            fseek($fp, $seekPos, SEEK_SET);
            $toSend = $this->bytesToSend;
            $maxSend = $this->chunkSizeArray[$i] - $this->byteToSendStart;
            if ($toSend > $maxSend)
                $toSend = $maxSend;
            $str = fread($fp, $toSend);
            if ($this->dbg)
                echo ("read " . $toSend . " and got " . strlen($str) . " <br/>");
            else
                echo ($str);
            $this->bytesToSend -= $toSend;
            $this->byteToSendStart = 0;
            $i++;
        }
        fclose($fp);
        if ($this->dbg) echo ("finished writing from " . $this->fileName . "<br/>");
    }
}   // end of class ReceivedAudio

    include ("wlConfig.php");

// php execution starts here
    $reqPlaybackPos = $_REQUEST["position"];
    $downloadNotPlay = $_REQUEST["download"];
    $channelToPlay = (int)$_REQUEST["channel"];
    $dlFileName = $_REQUEST["dlFileName"];
    if ($dlFileName == "") $dlFileName = "received.wav";
    if ($debugAudio)
    {   // debugging--send an html page rather than send audio content
    ?>
        <html>
        <head>
        <title>WAV file debug</title>
        </head>
        <body>
    <?php
    }
    $StartingTimesFileName = $subdirectoryName . "StartingTimes";
    if ($debugAudio) echo ("channelToPlay: " . $channelToPlay . "<br/>");
    if ($channelToPlay > 0)
        $StartingTimesFileName .= $channelToPlay;
    $StartingTimesFileName .= ".txt";
    if ($debugAudio) echo ("StartingTimes filename: " . $StartingTimesFileName . "<br/>");
    $fp = fopen($StartingTimesFileName, "r");
    if (!$fp) 
    {
        if ($debugAudio) echo ($StartingTimesFileName . " not found.<br/>");
        die;
    }
    $contributors = array();
    $bytesOfAudio = 0;
    while (!feof($fp))
    {
        $str = fgets($fp);
        $spacePos = stripos($str, " ");
        if ($spacePos)
        {
            $fname = substr($str,0,$spacePos);
            $time = substr($str,$spacePos+1);
            if ($debugAudio) echo ("<br/>Opening file " . $fname . " time= " . $time . "<br/>");

            $ra = new ReceivedAudio($subdirectoryName . $fname, $time, $debugAudio, $byteRateAdjust);
            $bytesForInterval = $ra->bytesForInterval($reqPlaybackPos - $secondsBefore,
                            $reqPlaybackPos + $secondsAfter);
            if ($debugAudio) echo ("File contributes: " . $bytesForInterval . " bytes<br/>");
            if ($bytesForInterval)
            {
                $bytesOfAudio += $bytesForInterval;
                if (count($contributors)>0)
                { 
                    if (!$ra->compatible($contributors[0]))
                        die;
                }
                array_push($contributors, $ra);
            }
            else if (count($contributors))
                break;
        }
    }
    if (!$debugAudio) 
    {
        header("Content-type:audio/wav");
        // tell browser to write to file instead of play
        if ($downloadNotPlay)
            header("Content-Disposition:attachment;filename=" . $dlFileName);
    }
    if (count($contributors))
    {
        if ($debugAudio) echo ("<br/>There are " . count($contributors) . " files<br/>");
        else header("Content-length:" .
             $contributors[0]->ContentLength($bytesOfAudio));
        $contributors[0]->writeWaveHeader($bytesOfAudio);
        for ($i = 0; $i < count($contributors); $i++)
            $contributors[$i]->writeDataChunks();
    }
    else
    {
        if ($debugAudio) 
            echo ("No audio for this qso<br/>");
        else    
        {
            readfile("noaudio.wav");
        }
    }

    if ($debugAudio)
    {
        ?>
        </body>
        </html>
        <?php
    }
?>

