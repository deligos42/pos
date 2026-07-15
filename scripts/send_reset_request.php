<?php
$html = @file_get_contents('forgot.html');
if (!$html) {
    echo "forgot.html not found\n";
    exit(1);
}
if (!preg_match('/name="csrf_token" value="([0-9a-f]+)"/s', $html, $m)) {
    echo "token not found\n";
    exit(1);
}
$token = $m[1];
$url = 'https://pos-production-1378.up.railway.app/forgot_password.php';
$data = http_build_query(['csrf_token'=>$token,'email'=>'waswawilgos42@gmail.com']);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookies.txt');
curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookies.txt');
$res = curl_exec($ch);
if ($res === false) {
    echo 'curl error: '.curl_error($ch)."\n";
    exit(1);
}
curl_close($ch);
echo $res;
