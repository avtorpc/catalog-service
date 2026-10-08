<?php
/** Run inside the catalog container; never download certificates to the host. */
declare(strict_types=1);
function ensure(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$dir='/opt/gigachat';
ensure(is_readable($dir.'/root.crt') && is_readable($dir.'/sub.crt') && is_readable($dir.'/ca-bundle.pem'),'Container-local certificate bundle missing');
ensure(!preg_match('/-----END CERTIFICATE-----[^\r\n]/',file_get_contents($dir.'/ca-bundle.pem')),'Malformed PEM bundle boundaries');
$root=openssl_x509_parse(file_get_contents($dir.'/root.crt'));
ensure(str_contains($root['subject']['CN']??'', 'Russian Trusted Root'),'Unexpected root CA');
ensure(getenv('GIGACHAT_CA_FILE') === $dir.'/ca-bundle.pem','Wrong client CA path');
foreach(['OPENAI_API_KEY','OPENAI_MODEL','OPENAI_API_URL','SSL_CERT_FILE','SSL_CERT_DIR','CURL_CA_BUNDLE'] as $name) ensure(!getenv($name),'Unexpected global/provider setting: '.$name);
ensure(!ini_get('openssl.cafile') && !ini_get('curl.cainfo'),'Global PHP trust was changed');
preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s',file_get_contents('/etc/ssl/certs/ca-certificates.crt'),$matches);
foreach($matches[0] as $pem){$cert=openssl_x509_parse($pem);ensure(!str_contains($cert['subject']['CN']??'', 'Russian Trusted'),'Russian CA was added to the container system store');}
echo "PASS: certificates only in catalog image; custom CA is client-scoped; system/PHP trust unchanged; OpenAI environment absent\n";
