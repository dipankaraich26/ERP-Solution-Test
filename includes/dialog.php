<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set a modal message to show on next page load
function setModal($title, $content) {
    $_SESSION['modal'] = [
        'title' => $title,
        'content' => $content
    ];
}

// Display modal if set
function showModal() {
    if (isset($_SESSION['modal'])) {
        $title = $_SESSION['modal']['title'];
        $content = $_SESSION['modal']['content'];
        unset($_SESSION['modal']); // remove so it shows only once

        echo "
        <div id='phpModal' style='
            display: block;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        '>
            <div style='
                background-color: #fff;
                margin: 15% auto;
                padding: 20px;
                border-radius: 8px;
                width: 300px;
                text-align: center;
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            '>
                <h2>" . htmlspecialchars($title) . "</h2>
                <p>" . htmlspecialchars($content) . "</p>
                <button id='modalOk' style='
                    padding: 8px 20px;
                    margin-top: 15px;
                    border: none;
                    background-color: #007BFF;
                    color: white;
                    border-radius: 5px;
                    cursor: pointer;
                '>OK</button>
            </div>
        </div>

        <script>
            document.getElementById('modalOk').onclick = function() {
                document.getElementById('phpModal').style.display='none';
            }
        </script>
        ";
    }
}
?>