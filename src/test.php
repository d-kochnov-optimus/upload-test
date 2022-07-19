<?php

namespace App;

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

if (isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['type'] == 'video/mp4' && isset($_POST['s3UploadTime']) && isset($_POST['budsiesUploadTime'])) {
    $stmt = $db->prepare('INSERT INTO logs (
        server, file, size, statusCode, time
    ) VALUES (
        :server, :file, :size, :statusCode, :time
    )');
    $stmt->bindValue(':server', 'S3');
    $stmt->bindValue(':file', (string)$_FILES['fileToUpload']['name']);
    $stmt->bindValue(':size', (string)$_FILES['fileToUpload']['size']);
    $stmt->bindValue(':statusCode', (string)200);
    $stmt->bindValue(':time', (string)round((float)$_POST['s3UploadTime'], 2));
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
    $stmt->bindValue(':time', (string)round((float)$_POST['budsiesUploadTime'], 2));
    $stmt->execute();
}

?>

<!DOCTYPE html>
<html>

<body>

    <p>
        Select mp4 video to upload:
        <input type="file" name="fileToUpload" id="fileToUpload" required accept="video/mp4">
        <button id="upload-button" onclick="uploadFile()"> Upload </button>
    </p>

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

    <script src="https://sdk.amazonaws.com/js/aws-sdk-2.1178.0.min.js"></script>
    <script>
        AWS.config.update({
            region: '<?php echo $_ENV["S3_CLIENT_REGION"]; ?>',
            credentials: new AWS.Credentials('<?php echo $_ENV["S3_CLIENT_KEY"]; ?>', '<?php echo $_ENV["S3_CLIENT_SECRET"]; ?>')
        });

        var s3 = new AWS.S3({
            apiVersion: '2006-03-01',
            params: { Bucket: 'budsies-staging-qa-photos' }
        });

        async function uploadFile() {
            let formData = new FormData();
            formData.append("fileToUpload", fileToUpload.files[0]);
            let budsiesStartTime = Date.now();
            await fetch('/test.php', {
                method: "POST",
                body: formData
            });
            let budsiesEndTime = Date.now();

            let s3StartTime, s3EndTime = 0;
            var reader = new FileReader();
            reader.onload = function() {
                s3StartTime = Date.now();
                s3.putObject({
                    Body: reader.result,
                    Bucket: "budsies-staging-qa-photos",
                    Key: uuidv4() + '.mp4'
                }, async function(err, data) {
                    s3EndTime = Date.now();
                    if (err) {console.log(err, err.stack); return;}
                    else console.log(data); // successful response

                    let resultData = new FormData();
                    resultData.append("fileToUpload", fileToUpload.files[0]);
                    resultData.append("s3UploadTime", (s3EndTime - s3StartTime) / 1000);
                    resultData.append("budsiesUploadTime", (budsiesEndTime - budsiesStartTime) / 1000);
                    await fetch('/test.php', {
                        method: "POST",
                        body: resultData
                    });

                    alert('The file has been uploaded successfully.');

                    window.location.reload();
                });
            };
            fileToSave = reader.readAsBinaryString(fileToUpload.files[0]);
        }

        function uuidv4() {
            return ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(/[018]/g, c =>
                (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
            );
        }
    </script>

</body>

</html>