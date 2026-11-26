<?php 

//Proof of Concept Before Query

function sendMessage($Sender, $Message) {
  echo "<div class='messageContent'> 
            <div class='messengerFrame'>
                <div class='studentFrame'>
                <div id='studentFrame' class='identifierFrame'>
                    <p class='identifierTextStyle'>Student:</p>
                </div>
                <div class='studentName'>
                    <p class='messengerInfoText'>{$Sender}</p>
                </div>
                </div>

                <div class='dateFrame'>
                <div class='identifierFrame'>
                    <p class='identifierTextStyle'>Date:</p>
                </div>
                <div class='dateSent'>
                    <p class='messengerInfoText'>00/00/0000</p>
                </div>
                </div>

                <div class='timeFrame'>
                <div class='identifierFrame'>
                    <p class='identifierTextStyle'>Time:</p>
                </div>
                <div class='timeSent'>
                    <p class='messengerInfoText'>00:00am</p>
                </div>
                </div>

            </div>

            <div class='messengerMessage'>
                <p id='messageText'>{$Message}</p>
            </div>
            </div>";
}

?>