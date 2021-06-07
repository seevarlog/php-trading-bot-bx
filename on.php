<?php
$output = exec("ps aux | grep bybit | wc -l");



$ff = $output >= 3 ? "실행중" : "오류";


echo <<<HTML
<html lang="ko">
<body>

<span style="font: 30px">{$ff}</span>
</body>
</html>
HTML;
