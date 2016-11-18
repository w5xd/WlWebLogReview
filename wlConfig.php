<?php   
    $myCallSign = "W5XD";           // For displaying on the page.
    $adifFileName = "changeMe.adi";   // Name of the ADIF file you put on your PHP server
    $maxQsosFromQuery = 100;        // Maximum QSOs it will put on a page of results
    $enableEmbedPlayers = false; // false means no "Show Media Player" button on the page,
    $enableMatchUsingRegularExpression = false; // Advanced feature
    $serveLeftAndRightAudioSeparately = false;  // Advanced feature. See ReadMe
    $byteRateAdjust = 1.0;        // Compressed audio (formats other than PCM) sometimes don't report exactly 
    $secondsBefore = 30;            // Seconds of audio to play before the QSO log time
    $secondsAfter =  10;             // Seconds of audio to play after the QSO log time
    // Anything you want on the page may be put as HTML in $userHtml
    $userHtml = "<p>User HTML goes here</p>";
    $mainPageName = "wlLogReview.php"; // change this only if you rename the main page
    $subdirectoryName = "changeMe/";       // put your adi, txt, and wav files in a subdirectory to prevent browser access
    $debugAudio = false;    // Advanced feature 
    /* WARNING DO NOT ADD BLANK LINES ANYWHERE IN THIS FILE */ ?>
