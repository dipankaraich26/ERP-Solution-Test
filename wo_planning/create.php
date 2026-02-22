<?php
// WO Planning no longer creates plans independently.
// Plans are created exclusively through PPP (Production & Procurement Planning).
header("Location: /procurement/create.php");
exit;
