<?php

namespace App;

require_once __DIR__ . './../vendor/autoload.php';

use Aws\S3\S3Client;
use GuzzleHttp\Client;
use Optimus\Utils\IdGenerator;
use SQLite3;

$db = new SQLite3('mydb.db');
$db->exec("CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY,
    server TEXT NOT NULL,
    file TEXT NOT NULL,
    size TEXT NOT NULL,
    statusCode TEXT NOT NULL,
    time TEXT NOT NULL,
    createdAt TEXT DEFAULT CURRENT_TIMESTAMP
)");

if (isset($_POST["submit"]) && isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['type'] == 'video/mp4') {
    $idGenerator = new IdGenerator();
    $httpClient = new Client();

    $extension = pathinfo($_FILES['fileToUpload']['name'], PATHINFO_EXTENSION);
    $uuid = $idGenerator->generate();
    $fileKey = $extension ? $uuid . '.' . $extension : $uuid;

    $s3Client = new S3Client(
        [
            'version' => 'latest',
            'region' => $_ENV["S3_CLIENT_REGION"],
            'credentials' => [
                'key' => $_ENV["S3_CLIENT_KEY"],
                'secret' => $_ENV["S3_CLIENT_SECRET"],
            ],
        ]
    );

    $cmd = $s3Client->getCommand('PutObject', [
        'Bucket' => 'budsies-staging-qa-photos',
        'Key' => $fileKey,
    ]);

    $s3TimeStart = microtime(true);

    $presignedRequest = $s3Client->createPresignedRequest($cmd, "+120 seconds");

    $s3Response = $httpClient->request(
        'PUT',
        $presignedRequest->getUri(),
        [
            'body' => file_get_contents($_FILES['fileToUpload']['tmp_name']),
            'headers' => [
                'Content-Type' => 'video/mp4',
            ],
        ]
    );

    $s3TimeEnd = microtime(true);

    $s3UploadExecuteTime = $s3TimeEnd - $s3TimeStart;

    $requestTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];

    $stmt = $db->prepare('INSERT INTO logs (
            server, file, size, statusCode, time
        ) VALUES (
            :server, :file, :size, :statusCode, :time
        )');
    $stmt->bindValue(':server', 'S3');
    $stmt->bindValue(':file', (string)$_FILES['fileToUpload']['name']);
    $stmt->bindValue(':size', (string)$_FILES['fileToUpload']['size']);
    $stmt->bindValue(':statusCode', (string)$s3Response->getStatusCode());
    $stmt->bindValue(':time', (string)round($s3UploadExecuteTime, 2));
    $stmt->execute();

    $stmt = $db->prepare('INSERT INTO logs (
            server, file, size, statusCode, time
        ) VALUES (
            :server, :file, :size, :statusCode, :time
        )');
    $stmt->bindValue(':server', 'Budsies');
    $stmt->bindValue(':file', (string)$_FILES['fileToUpload']['name']);
    $stmt->bindValue(':size', (string)$_FILES['fileToUpload']['size']);
    $stmt->bindValue(':statusCode', (string)200);
    $stmt->bindValue(':time', (string)round($requestTime - $s3UploadExecuteTime, 2));
    $stmt->execute();
}

?>

<!DOCTYPE html>
<html>

<body>

    <form action="test.php" method="post" enctype="multipart/form-data">
        Select mp4 video to upload:
        <input type="file" name="fileToUpload" id="fileToUpload" required accept="video/mp4">
        <input type="submit" value="Upload" name="submit">
    </form>

    <?php
        echo "
        <br>
        <table>
            <tr>
                <th>Server</th>
                <th>File name</th>
                <th>File size</th>
                <th>Result Code</th>
                <th>Time in seconds</th>
                <th>Uploaded At</th>
            </tr>";

        $result = $db->query('SELECT * FROM logs ORDER BY id DESC');

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            echo "
            <tr>
                <td>{$row['server']}</td>
                <td>{$row['file']}</td>
                <td>{$row['size']}</td>
                <td>{$row['statusCode']}</td>
                <td>{$row['time']}</td>
                <td>{$row['createdAt']}</td>
            </tr>
            ";
        }

        echo "</table>";
    ?>

</body>

</html>